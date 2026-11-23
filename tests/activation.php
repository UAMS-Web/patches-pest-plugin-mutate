<?php

declare(strict_types=1);

// Checks that the patched src/Autoload.php starts the mutation stream wrapper from the mutation
// environment variables, which is what an isolated PHPUnit child (no Pest kernel, so no
// Mutate::boot()) relies on, and does nothing without them. Run by tests/check.sh with the
// patched tree's autoloader already loaded, in a process of its own. Exits 0 when every case
// holds, 1 otherwise. See UAMS-Web/patches-pest-plugin-mutate#92.

use Pest\Mutate\Plugins\Mutate;

$failures = 0;

$expect = static function (string $what, string $want, string $got) use (&$failures): void {
    $ok = $got === $want;
    $failures += $ok ? 0 : 1;
    fwrite(STDOUT, sprintf("%s  %s: expected %s, got %s\n", $ok ? 'ok  ' : 'FAIL', $what, $want, $got));
};

// $argv[1] is the patched tree's vendor/autoload.php, as tests/check.sh passes it.
$autoload = dirname((string) $GLOBALS['argv'][1]).'/pestphp/pest-plugin-mutate/src/Autoload.php';
if (! is_file($autoload)) {
    fwrite(STDOUT, "FAIL  the patched tree has no src/Autoload.php\n");

    exit(1);
}

$source = tempnam(sys_get_temp_dir(), 'mutate-source-');
$replacement = tempnam(sys_get_temp_dir(), 'mutate-replacement-');
file_put_contents($source, 'original');
file_put_contents($replacement, 'mutated');

/** What reading the source file gives now. */
$read = static fn (): string => (string) file_get_contents($source);

// Control: without the mutation variables the file is loaded and nothing starts.
putenv(Mutate::ENV_MUTATION_TESTING);
putenv(Mutate::ENV_MUTATION_FILE);
require $autoload;
$expect('no mutation variables: wrapper not started', 'no', defined('PEST_MUTATE_STREAM_WRAPPER_STARTED') ? 'yes' : 'no');
$expect('no mutation variables: source read unchanged', 'original', $read());

// With the variables, the wrapper starts and reads of the source return the replacement.
putenv(Mutate::ENV_MUTATION_TESTING.'='.$source);
putenv(Mutate::ENV_MUTATION_FILE.'='.$replacement);
require $autoload;
$expect('mutation variables set: wrapper started', 'yes', defined('PEST_MUTATE_STREAM_WRAPPER_STARTED') ? 'yes' : 'no');
$expect('mutation variables set: source read returns the replacement', 'mutated', $read());

// Loaded again (a second autoloader, or Mutate::boot() after it), it must not start a second wrapper.
require $autoload;
$expect('loaded twice: source still reads the replacement', 'mutated', $read());

@unlink($source);
@unlink($replacement);

exit($failures === 0 ? 0 : 1);
