<?php

declare(strict_types=1);

// Drives the real MutationTest::hasFinished() of the patched tree with a child that runs past its
// limit, and reads how the mutant is scored. Run by tests/check.sh with the patched tree's
// autoloader already loaded. Exits 0 when every case holds, 1 otherwise. A limit is at least 5 s,
// so the cases take about 25 s together.
// See UAMS-Web/patches-pest-plugin-mutate#30.

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

/** How the mutant is scored when its child runs $code and writes a junit report only if $code does: the result's name, or "refused". */
$scored = static function (string $code): string {
    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration(0.0);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);

    $path = __FILE__;
    $mutation = new Mutation(new SplFileInfo($path, '', basename($path)), 'id', 'mutator', 1, 1, '', $path);
    $configuration = new Configuration(false, [], [], [], [], false, 1, false, null, false, false, false, null, false, false);

    $test = new MutationTest($mutation);
    $covered = [realpath($path) => [1 => ['\Tests\ExampleTest::test_it']]];
    $test->start($covered, $configuration, [PHP_BINARY, '-r', $code, '--']);

    try {
        for ($i = 0; $i < 400 && ! $test->hasFinished(); $i++) {
            usleep(50_000);
        }
    } catch (RuntimeException) {
        return 'refused';
    }

    return $test->result()->name;
};

$marker = sys_get_temp_dir().'/retry-check-'.getmypid();
@unlink($marker);

// Slow only on the first attempt (the marker does not exist yet), then it fails a test as a kill does: scored by the second attempt.
$expect('timed out once, then killed on the second attempt', 'Tested', $scored(
    '$m = '.var_export($marker, true).'; if (! file_exists($m)) { touch($m); sleep(30); } echo "  Tests:    1 failed\n"; exit(1);'
));
@unlink($marker);

// Slow on the first attempt, then passes: the mutant escaped, which the second attempt decides.
$expect('timed out once, then passed on the second attempt', 'Untested', $scored(
    '$m = '.var_export($marker, true).'; if (! file_exists($m)) { touch($m); sleep(30); } foreach ($argv as $a) { if (str_starts_with($a, "--log-junit=")) { file_put_contents(substr($a, 12), "<testsuites><testsuite><testcase name=\\"t\\"/></testsuite></testsuites>"); } } exit(0);'
));
@unlink($marker);

// Slow on every attempt: a timeout, as before.
$expect('timed out twice', 'Timeout', $scored('sleep(30);'));

exit($failures === 0 ? 0 : 1);
