<?php

declare(strict_types=1);

// Drives the real MutationTest::start() of the patched tree and reads the time limit it gave
// the mutant child. Run by tests/check.sh with the patched tree's autoloader already loaded.
// Exits 0 when every figure holds, 1 otherwise.

use Pest\Mutate\Mutation;
use Pest\Mutate\MutationTest;
use Pest\Mutate\Repositories\TelemetryRepository;
use Pest\Mutate\Support\Configuration\Configuration;
use Pest\Support\Container;
use Symfony\Component\Finder\SplFileInfo;

$failures = 0;

$expect = static function (string $what, int $want, mixed $got) use (&$failures): void {
    $ok = $got === $want;
    $failures += $ok ? 0 : 1;
    fwrite(STDOUT, sprintf("%s  %s: expected %d, got %s\n", $ok ? 'ok  ' : 'FAIL', $what, $want, var_export($got, true)));
};

/** The limit MutationTest::start() gives a child, for an initial suite of $seconds and $processes workers. */
$limit = static function (float $seconds, bool $parallel, int $processes): mixed {
    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration($seconds);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);

    $path = __FILE__;
    $mutation = new Mutation(new SplFileInfo($path, '', basename($path)), 'id', 'mutator', 1, 1, '', $path);
    $configuration = new Configuration(false, [], [], [], [], $parallel, $parallel ? $processes : 1, false, null, false, false, false, null, false, false);

    $test = new MutationTest($mutation);
    $covered = [realpath($path) => [1 => ['\Tests\ExampleTest::test_it']]];
    $test->start($covered, $configuration, [PHP_BINARY, '-r', 'sleep(30);']);

    $process = (new ReflectionProperty(MutationTest::class, 'process'))->getValue($test);
    $timeout = $process->getTimeout();
    $process->stop(0);

    return $timeout === null ? null : (int) $timeout;
};

// 3.9 s initial suite: the plugin's limit is 3.9 + max(5, 0.78) = 8 s.
$expect('serial run keeps the plugin limit (1 process)', 8, $limit(3.9, false, 1));
// 12 workers stand for 46.8 s of serial work: 46.8 + max(5, 9.36) = 56 s; 4 stand for 15.6 + 5 = 20 s.
$expect('parallel run sized from serial work (12 processes)', 56, $limit(3.9, true, 12));
$expect('parallel run sized from serial work (4 processes)', 20, $limit(3.9, true, 4));

exit($failures === 0 ? 0 : 1);
