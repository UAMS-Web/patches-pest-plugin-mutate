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

/** How the mutant is scored when its child runs $code and writes no junit report: the result's name, or "refused" when hasFinished() throws, which the patch no longer does. */
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
// A child that fails without printing a failed test ran no test: untested.
$expect('failure that printed no failed test', 'Untested', $scored('echo "boot failure\n"; exit(1);'));
// A child killed by a fatal error (memory exhausted by the mutant) prints no summary, only PHPUnit's
// premature-end line naming the test it was running: a kill. See #34.
$expect('fatal error naming the test it was running (scored a kill)', 'Tested', $scored('fwrite(STDERR, "Fatal error: Allowed memory size of 1073741824 bytes exhausted (tried to allocate 671088640 bytes) in /x.php on line 493\nFatal error: Premature end of PHP process when running P\\Tests\\Unit\\FooTest::__pest_evaluable_it_x@dataset \"a\" with data (1).\n"); exit(255);'));
// A child that dies before any test starts (a missing dataset while collecting tests) names no test: untested.
$expect('fatal error before any test started', 'Untested', $scored('fwrite(STDERR, "Fatal error: Uncaught Pest\\Exceptions\\DatasetMissing: A test with the name `x` does not have a dataset named `y`.\n"); exit(255);'));
// A child that dies of a compile-time fatal error in the mutated file (`[] = $x;` from `[$a] = $x;`) names that
// file and cannot have passed: a kill. A fatal error in any other file is a boot failure: untested. See #40.
$mutated = var_export(realpath(__FILE__), true);
$expect('compile error in the mutated file (scored a kill)', 'Tested', $scored('fwrite(STDERR, "PHP Fatal error:  Cannot use empty list in ".'.$mutated.'." on line 199\n"); exit(255);'));
$expect('compile error in another file', 'Untested', $scored('fwrite(STDERR, "PHP Fatal error:  Cannot use empty list in /elsewhere/Other.php on line 199\n"); exit(255);'));
// A child that runs past PHP's time limit (a mutant that never leaves a loop, under a test suite that calls set_time_limit())
// did not pass: a kill. See #41.
$expect('time limit exceeded (scored a kill)', 'Tested', $scored('fwrite(STDERR, "PHP Fatal error:  Maximum execution time of 2 seconds exceeded in /x/Foo.php on line 12\n"); exit(255);'));
$expect('time limit exceeded, plain fatal line', 'Tested', $scored('echo "Fatal error: Maximum execution time of 1 second exceeded in /x/Foo.php on line 12\n"; exit(255);'));
// A child that exhausts the hard memory limit (a mutant that makes a loop grow an array without end) did not pass: a kill. See #45.
$expect('hard memory limit exhausted (scored a kill)', 'Tested', $scored('echo "Hard memory limit of 3221225472 bytes exhausted (tried to allocate 262144 bytes) in /x/Foo.php on line 12\n"; exit(137);'));
// A child that overflows the stack on Windows exits 0xC00000FD (-1073741571) and prints nothing: a kill. See #58.
// PHP truncates an exit status above 255 on Linux and macOS, so the case runs only where a child can report it.
if (DIRECTORY_SEPARATOR === '\\') {
    $expect('silent Windows stack-overflow exit (scored a kill)', 'Tested', $scored('exit(-1073741571);'));
}
// Any other silent failure ran no test, so matching the exit code and not the empty output keeps these untested.
$expect('silent exit 1', 'Untested', $scored('exit(1);'));
$expect('silent exit 255', 'Untested', $scored('exit(255);'));
if (DIRECTORY_SEPARATOR === '\\') {
    $expect('silent exit with another Windows status', 'Untested', $scored('exit(-1073741819);'));
}
// A child that ends through the code's own `exit` while a test runs (a mutant removes the call before an `exit;`) prints nothing and
// writes no report, and PHPUnit's events log is what shows a test was running. See #60.
$events = static fn (string $lines): string => 'foreach ($argv as $a) { if (str_starts_with($a, "--log-events-text=")) { file_put_contents(substr($a, 18), '.var_export($lines, true).'); } }';
$started = "Test Runner Started\nTest Prepared (Tests\ExampleTest::test_it)\n";
$expect('silent exit while a test runs (scored a kill)', 'Tested', $scored($events($started).' exit(2);'));
// A control: the same silent exit before any test started names no test, so it stays untested.
$expect('silent exit before any test started', 'Untested', $scored($events("Test Runner Started\nTest Suite Loaded (1 tests)\n").' exit(2);'));
// Every test that started also finished, so the silent exit is not inside one: untested.
$expect('silent exit after every test finished', 'Untested', $scored($events($started."Test Finished (Tests\ExampleTest::test_it)\n").' exit(2);'));

// The same through a real PHPUnit child, which checks that it accepts the `--log-events-text` option the patched command line now carries.
// PHPUnit's own output for a child that exits mid-test already names the test, so these two do not need the events log to pass; the
// fixtures above are what do.
$phpunit = dirname((new ReflectionClass(Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/phpunit/phpunit/phpunit';
$fixtureDir = sys_get_temp_dir().'/exit-fixture-'.getmypid();
@mkdir($fixtureDir);
$fixture = $fixtureDir.'/ExampleTest.php';
// The child is given Pest's `--bail`, which PHPUnit does not know, so a wrapper drops it and runs PHPUnit.
$wrapper = sys_get_temp_dir().'/exit-wrapper-'.getmypid().'.php';
file_put_contents($wrapper, '<?php $_SERVER["argv"] = array_values(array_filter($_SERVER["argv"], static fn (string $a): bool => $a !== "--bail")); $argv = $_SERVER["argv"]; require '.var_export($phpunit, true).';');
$real = static function (string $body, ?string $file = null) use ($wrapper, $fixture): string {
    file_put_contents($fixture, $file ?? "<?php\nnamespace Tests;\nfinal class ExampleTest extends \PHPUnit\Framework\TestCase\n{\n    public function test_it(): void\n    {\n        ".$body."\n    }\n}\n");

    $telemetry = new TelemetryRepository;
    $telemetry->initialTestSuiteDuration(3.9);
    Container::getInstance()->add(TelemetryRepository::class, $telemetry);

    $path = __FILE__;
    $mutation = new Mutation(new SplFileInfo($path, '', basename($path)), 'id', 'mutator', 1, 1, '', $path);
    $configuration = new Configuration(false, [], [], [], [], false, 1, false, null, false, false, false, null, false, false);

    $test = new MutationTest($mutation);
    $covered = [realpath($path) => [1 => ['\Tests\ExampleTest::test_it']]];
    $test->start($covered, $configuration, [PHP_BINARY, $wrapper, '--no-configuration', $fixture]);

    try {
        for ($i = 0; $i < 200 && ! $test->hasFinished(); $i++) {
            usleep(50_000);
        }
    } catch (RuntimeException) {
        return 'refused';
    }

    return $test->result()->name;
};
$expect('real PHPUnit child that calls exit in a test (scored a kill)', 'Tested', $real('exit;'));
$expect('real PHPUnit child whose test passes', 'Untested', $real('$this->assertTrue(true);'));
// A mutant that breaks the test file while it loads (a throw at load time, as an emptied dataset does) ran no test, so it is untested and
// the run goes on; it used to end the run. See UAMS-Web/patches-pest-plugin-mutate#124 and #128.
$expect('real PHPUnit child that fails while the test file loads', 'Untested', $real('', "<?php\nnamespace Tests;\nthrow new \\RuntimeException('failed while loading');\n"));
@unlink($fixture);
@rmdir($fixtureDir);
@unlink($wrapper);

// A child that exits 0 with no report ran no test: untested.
$expect('success that wrote no report', 'Untested', $scored('exit(0);'));

exit($failures === 0 ? 0 : 1);
