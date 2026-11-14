<?php

declare(strict_types=1);

// Drives the real MutationTest::hasFinished() of the patched tree with a child that exits as a
// crashed or an empty one does, and reads how the mutant is scored. Run by tests/check.sh with
// the patched tree's autoloader already loaded. Exits 0 when every case holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#27.

use Pest\Mutate\Mutation;
use Pest\Mutate\MutationTest;
use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Support\Container;
use Symfony\Component\Finder\SplFileInfo;

$failures = 0;

$expect = static function (string $what, string $want, string $got) use (&$failures): void {
    $ok = $got === $want;
    $failures += $ok ? 0 : 1;
    fwrite(STDOUT, sprintf("%s  %s: expected %s, got %s\n", $ok ? 'ok  ' : 'FAIL', $what, $want, $got));
};

/** How the mutant is scored when its child runs $code and writes no junit report: the result's name, or "refused". */
$scored = static function (string $code): string {
    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration(3.9);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);

    $path = __FILE__;
    $mutation = new Mutation(new SplFileInfo($path, '', basename($path)), 'id', 'mutator', 1, 1, '', $path);
    $configuration = new Configuration(false, [], [], [], [], false, 1, false, null, false, false, false, null, false, false);

    $test = new MutationTest($mutation);
    $covered = [realpath($path) => [1 => ['\Tests\ExampleTest::test_it']]];
    $test->start($covered, $configuration, [PHP_BINARY, '-r', $code, '--']);

    try {
        for ($i = 0; $i < 200 && ! $test->hasFinished(); $i++) {
            usleep(50_000);
        }
    } catch (RuntimeException) {
        return 'refused';
    }

    return $test->result()->name;
};

// A child that crashes after printing a failed test: a kill (`Tested`), not an aborted run.
$expect('crash that printed a failed test (scored a kill)', 'Tested', $scored('echo "  \033[90mTests:\033[39m    \033[31;1m1 failed\033[39;22m, 122 pending\n"; exit(1);'));
// A child that fails without printing a failed test ran no test: refused.
$expect('failure that printed no failed test', 'refused', $scored('echo "boot failure\n"; exit(1);'));
// A child killed by a fatal error (memory exhausted by the mutant) prints no summary, only PHPUnit's
// premature-end line naming the test it was running: a kill. See #34.
$expect('fatal error naming the test it was running (scored a kill)', 'Tested', $scored('fwrite(STDERR, "Fatal error: Allowed memory size of 1073741824 bytes exhausted (tried to allocate 671088640 bytes) in /x.php on line 493\nFatal error: Premature end of PHP process when running P\\Tests\\Unit\\FooTest::__pest_evaluable_it_x@dataset \"a\" with data (1).\n"); exit(255);'));
// A child that dies before any test starts (a missing dataset while collecting tests) names no test: refused.
$expect('fatal error before any test started', 'refused', $scored('fwrite(STDERR, "Fatal error: Uncaught Pest\\Exceptions\\DatasetMissing: A test with the name `x` does not have a dataset named `y`.\n"); exit(255);'));
// A child that exits 0 with no report ran no test: refused.
$expect('success that wrote no report', 'refused', $scored('exit(0);'));

exit($failures === 0 ? 0 : 1);
