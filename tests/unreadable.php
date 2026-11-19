<?php

declare(strict_types=1);

// Checks what the stat family reports through the mutation stream wrapper, which answers it from
// url_stat() while it is registered for file://. A file that exists but cannot be read must be
// reported present, as the plain handler reports it, or code that reads it behind an existence
// check never reaches the read in a mutant child. A missing path must still be reported absent,
// with one warning from stat(), and the source file must still be served from the override.
// Run by tests/check.sh with the patched tree's autoloader already loaded. Exits 0 when every
// check holds, 1 otherwise. See UAMS-Web/patches-pest-plugin-mutate#52.

use Pest\Mutate\Support\StreamWrapper;

$dir = sys_get_temp_dir().'/mutate-unreadable-'.bin2hex(random_bytes(4));
mkdir($dir);
$locked = $dir.'/locked.txt';
file_put_contents($locked, "x\n");
chmod($locked, 0o000);
$missing = $dir.'/missing.txt';
$source = $dir.'/Source.php';
file_put_contents($source, "<?php\nreturn 'source';\n");
$override = $dir.'/Override.php';
file_put_contents($override, "<?php\nreturn 'override';\n");

// Mode 000 does not stop a read by root, or on Windows; the unreadable case then cannot be built.
$unreadable = ! is_readable($locked);

/** @return array<string, mixed> */
$probe = static function () use ($locked, $missing, $source): array {
    clearstatcache();
    $warnings = 0;
    // A handler still runs for a warning @ silenced; error_reporting() then excludes it.
    set_error_handler(static function (int $level) use (&$warnings): bool {
        $warnings += (error_reporting() & $level) !== 0 ? 1 : 0;

        return true;
    });
    $stat = stat($missing);
    restore_error_handler();
    clearstatcache();

    return [
        'is_file(unreadable)' => is_file($locked),
        'file_exists(unreadable)' => file_exists($locked),
        'is_file(missing)' => is_file($missing),
        'file_exists(missing)' => file_exists($missing),
        'stat(missing)' => $stat,
        'warnings from stat(missing)' => $warnings,
        'include(source)' => include $source,
    ];
};

$plain = $probe();
StreamWrapper::start($source, $override);
$wrapped = $probe();
StreamWrapper::disable();

chmod($locked, 0o644);
array_map('unlink', glob($dir.'/*'));
rmdir($dir);

$expected = [
    'no wrapper' => [$plain, [
        'is_file(unreadable)' => true, 'file_exists(unreadable)' => true,
        'is_file(missing)' => false, 'file_exists(missing)' => false,
        'stat(missing)' => false, 'warnings from stat(missing)' => 1, 'include(source)' => 'source',
    ]],
    'wrapper started' => [$wrapped, [
        'is_file(unreadable)' => true, 'file_exists(unreadable)' => true,
        'is_file(missing)' => false, 'file_exists(missing)' => false,
        'stat(missing)' => false, 'warnings from stat(missing)' => 1, 'include(source)' => 'override',
    ]],
];

$failures = 0;
foreach ($expected as $when => [$got, $want]) {
    foreach ($want as $what => $value) {
        if (! $unreadable && str_contains($what, 'unreadable')) {
            fwrite(STDOUT, sprintf("skip  %s, %s: mode 000 is readable here\n", $when, $what));

            continue;
        }
        $ok = $got[$what] === $value;
        $failures += $ok ? 0 : 1;
        fwrite(STDOUT, sprintf("%s  %s, %s: expected %s, got %s\n", $ok ? 'ok  ' : 'FAIL', $when, $what, var_export($value, true), var_export($got[$what], true)));
    }
}

exit($failures === 0 ? 0 : 1);
