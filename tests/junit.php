<?php

declare(strict_types=1);

// Reads the command line the patched MutationTest::start() gives a mutant child when the run itself was given
// `--log-junit`. The child writes its own report to a file of its own, and Pest refuses the option twice
// ("Option --log-junit cannot be used more than once", exit 1), which scored every mutant killed. Run by
// tests/check.sh with the patched tree's autoloader already loaded. Exits 0 when every case holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#64.

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

/** The command line of the child that start() builds for these original arguments. */
$childArguments = static function (array $originalArguments): string {
    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration(3.9);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);

    $path = __FILE__;
    $mutation = new Mutation(new SplFileInfo($path, '', basename($path)), 'id', 'mutator', 1, 1, '', $path);
    $configuration = new Configuration(false, [], [], [], [], false, 1, false, null, false, false, false, null, false, false);

    $test = new MutationTest($mutation);
    $covered = [realpath($path) => [1 => ['\Tests\ExampleTest::test_it']]];
    $test->start($covered, $configuration, $originalArguments);

    $process = (new ReflectionProperty(MutationTest::class, 'process'))->getValue($test);
    $commandLine = $process->getCommandLine();
    while ($process->isRunning()) {
        usleep(20_000);
    }

    return $commandLine;
};

$base = [PHP_BINARY, '-r', 'exit(0);', '--'];

// The run's own report is not the child's: the child carries one `--log-junit`, and it is the child's own.
foreach ([
    'the run was given --log-junit=<file>' => [...$base, '--log-junit=user.xml', '--keep-me'],
    'the run was given --log-junit <file>' => [...$base, '--log-junit', 'user.xml', '--keep-me'],
] as $what => $arguments) {
    $commandLine = $childArguments($arguments);
    $expect($what.': one --log-junit', '1', (string) substr_count($commandLine, '--log-junit'));
    $expect($what.': the run\'s file is not the child\'s', '0', (string) substr_count($commandLine, 'user.xml'));
    $expect($what.': other options are kept', '1', (string) substr_count($commandLine, '--keep-me'));
}

// A control: a run given no --log-junit still gets the child's own.
$commandLine = $childArguments([...$base, '--keep-me']);
$expect('the run was given no --log-junit: one --log-junit', '1', (string) substr_count($commandLine, '--log-junit'));

exit($failures === 0 ? 0 : 1);
