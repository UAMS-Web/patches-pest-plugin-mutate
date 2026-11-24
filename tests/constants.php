<?php

declare(strict_types=1);

// Drives the real MutationGenerator of the patched tree the way --covered-only does, with the covered lines
// of a class as linesToMutate, and counts the mutants generated for a class constant's initializer. Run by
// tests/check.sh with the patched tree's autoloader already loaded. Exits 0 when every case holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#47.

use Pest\Mutate\Cache\NullStore;
use Pest\Mutate\Mutation;
use Pest\Mutate\MutationTest;
use Pest\Mutate\Mutators\Removal\RemoveArrayItem;
use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Symfony\Component\Finder\SplFileInfo;

$failures = 0;

// What --no-cache binds, so a generated population is never read back from an earlier run.
Container::getInstance()->add(Psr\SimpleCache\CacheInterface::class, new NullStore);

$expect = static function (string $what, int $want, int $got) use (&$failures): void {
    $ok = $got === $want;
    $failures += $ok ? 0 : 1;
    fwrite(STDOUT, sprintf("%s  %s: expected %d, got %d\n", $ok ? 'ok  ' : 'FAIL', $what, $want, $got));
};

// Line 3 is the constant array (two items, so two RemoveArrayItem mutants); line 6 is the only line a test covers.
// The method holds no array, so every RemoveArrayItem mutant of this file is a constant mutant.
$source = <<<'PHP'
<?php
class ConstantsFixture {
    public const SECTIONS = ['a', 'b'];

    public function run(): int {
        return 1;
    }
}
PHP;
$path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'constants-'.bin2hex(random_bytes(4)).'.php';
file_put_contents($path, $source);

$generated = static fn (array $linesToMutate): int => count((new MutationGenerator)->generate(new SplFileInfo($path, '', basename($path)), [RemoveArrayItem::class], $linesToMutate));

// With --covered-only a covered class must still get its constant mutants; an uncovered class must get none; and with no
// line filter the population is the same two.
$expect('covered class, constant array', 2, $generated([6]));
$expect('class with no covered line', 0, $generated([100]));
$expect('no line filter (population unchanged)', 2, $generated([]));

unlink($path);

// A constant mutant keeps its own line for printing and storage, and only the lookup of covering tests widens to the class. The
// class carries an attribute, so its start line (the attribute's) is the line an uncovered attribute argument would also report.
$attributed = <<<'PHP'
<?php
#[Attribute]
class AttributedConstants {
    public const SECTIONS = ['a', 'b'];

    public function run(): int {
        return 1;
    }
}
PHP;
$attributedPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'constants-attr-'.bin2hex(random_bytes(4)).'.php';
file_put_contents($attributedPath, $attributed);
$mutants = (new MutationGenerator)->generate(new SplFileInfo($attributedPath, '', basename($attributedPath)), [RemoveArrayItem::class], [6]);
$expect('attributed class: constant mutants generated', 2, count($mutants));
$expect('attributed class: a constant mutant keeps its own start line', 4, $mutants[0]->startLine);
$expect('attributed class: coverage lookup starts at the class (the attribute line)', 2, (int) $mutants[0]->coverageStartLine);
$expect('attributed class: the serialized form carries the coverage range', 2, (int) unserialize(serialize($mutants[0]))->coverageStartLine);

// The tests covering the class are the ones the mutant runs: with only line 6 covered, start() must find a test and not report the
// mutant uncovered, which is what dropping the lookup range from MutationTest::start() would do.
$started = static function (Mutation $mutation): int {
    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration(3.9);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);
    $configuration = new Configuration(false, [], [], [], [], false, 1, false, null, false, false, false, null, false, false);
    $test = new MutationTest($mutation);
    $covered = [realpath($mutation->file->getRealPath()) => [6 => ['\Tests\ExampleTest::test_it']]];
    $began = $test->start($covered, $configuration, [PHP_BINARY, '-r', 'exit(0);', '--']);
    for ($i = 0; $began && $i < 200; $i++) {
        try {
            if ($test->hasFinished()) {
                break;
            }
        } catch (Throwable) {
            break;
        }
        usleep(50_000);
    }

    return $began ? 1 : 0;
};
$expect('constant mutant finds the tests covering its class', 1, $started($mutants[0]));
unlink($attributedPath);

exit($failures === 0 ? 0 : 1);
