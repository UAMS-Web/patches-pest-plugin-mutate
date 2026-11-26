<?php

declare(strict_types=1);

// Drives the real MutationGenerator and MutationTest of the patched tree over functions whose parameter defaults are
// literals at different depths, and checks which lines a mutant of a default is selected by. Run by tests/check.sh with
// the patched tree's autoloader already loaded. Exits 0 when every case holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#123.

use Pest\Mutate\Cache\NullStore;
use Pest\Mutate\Mutation;
use Pest\Mutate\MutationTest;
use Pest\Mutate\Mutators\Number\DecrementInteger;
use Pest\Mutate\Mutators\Number\IncrementInteger;
use Pest\Mutate\Mutators\Removal\RemoveArrayItem;
use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Symfony\Component\Finder\SplFileInfo;

$failures = 0;

// What --no-cache binds, so a generated population is never read back from an earlier run.
Container::getInstance()->add(Psr\SimpleCache\CacheInterface::class, new NullStore);

$expect = static function (string $what, mixed $want, mixed $got) use (&$failures): void {
    $ok = $got === $want;
    $failures += $ok ? 0 : 1;
    fwrite(STDOUT, sprintf("%s  %s: expected %s, got %s\n", $ok ? 'ok  ' : 'FAIL', $what, var_export($want, true), var_export($got, true)));
};

// Each signature line carries no executable code, so stock leaves a default outside every covered line. The function bodies are
// lines 5, 12, 19, 26 and 33; each parameter default is on the line two above the body.
$source = <<<'PHP'
<?php
function bare(
    int $x = 5
): int {
    return $x;
}
function negative(
    int $x = -5
): int {
    return $x;
}
function listed(
    array $a = [1, 2]
): int {
    return count($a);
}
function summed(
    int $x = 3 + 1
): int {
    return $x;
}
function never_called(
    int $x = 7
): int {
    return $x;
}
function with_body(
    int $x = 0
): int {
    return $x + 1;
}
PHP;
$path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'defaults-'.bin2hex(random_bytes(4)).'.php';
file_put_contents($path, $source);

$mutators = [IncrementInteger::class, DecrementInteger::class, RemoveArrayItem::class];
$mutants = (new MutationGenerator)->generate(new SplFileInfo($path, '', basename($path)), $mutators, []);

// The functions' default lines and the last line of each function.
$ends = [3 => 6, 8 => 11, 13 => 16, 18 => 21, 23 => 26, 28 => 31];
$byLine = [];
foreach ($mutants as $mutant) {
    $byLine[$mutant->startLine][] = $mutant;
}

foreach ([3 => 'bare', 8 => 'negative', 13 => 'listed', 18 => 'summed', 23 => 'never_called', 28 => 'with_body'] as $line => $name) {
    $found = $byLine[$line] ?? [];
    $expect("$name: default mutants generated", true, $found !== []);
    $reach = array_unique(array_map(static fn (Mutation $m): int => $m->endLine, $found));
    $expect("$name: every default mutant reaches the end of the function", [$ends[$line]], array_values($reach));
}

// A mutant of a literal in a function body keeps its own line.
$body = $byLine[30] ?? [];
$expect('body literal: mutants generated', true, $body !== []);
$expect('body literal: the range is its own line', [30], array_values(array_unique(array_map(static fn (Mutation $m): int => $m->endLine, $body))));

$started = static function (Mutation $mutation, array $covered): bool {
    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration(3.9);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);
    $configuration = new Configuration(false, [], [], [], [], false, 1, false, null, false, false, false, null, false, false);
    $test = new MutationTest($mutation);
    $began = $test->start([realpath($mutation->file->getRealPath()) => $covered], $configuration, [PHP_BINARY, '-r', 'exit(0);', '--']);
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

    return $began;
};

// Only the line inside each function body is covered, as a test that calls the function would cover it.
foreach ([3 => 5, 8 => 10, 13 => 15, 18 => 20, 28 => 30] as $line => $bodyLine) {
    $name = ['bare', 'negative', 'listed', 'summed', 'with_body'][array_search($line, [3, 8, 13, 18, 28], true)];
    $expect("$name: a default selects the test that calls the function", true, $started($byLine[$line][0], [$bodyLine => ['\Tests\ExampleTest::test_it']]));
}

// A function nothing calls has no covered line, so its default stays uncovered.
$expect('never_called: a default stays uncovered', false, $started($byLine[23][0], [5 => ['\Tests\ExampleTest::test_it']]));

unlink($path);

exit($failures === 0 ? 0 : 1);
