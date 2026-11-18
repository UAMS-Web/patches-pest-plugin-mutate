<?php

declare(strict_types=1);

// Drives the real MutationGenerator of the patched tree the way --covered-only does, with the covered lines
// of a class as linesToMutate, and counts the mutants generated for a class constant's initializer. Run by
// tests/check.sh with the patched tree's autoloader already loaded. Exits 0 when every case holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#47.

use Pest\Mutate\Mutators\Removal\RemoveArrayItem;
use Pest\Mutate\Support\MutationGenerator;
use Pest\Support\Container;
use Pest\Mutate\Cache\NullStore;
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

exit($failures === 0 ? 0 : 1);
