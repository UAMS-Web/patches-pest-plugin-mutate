<?php

declare(strict_types=1);

// Checks what the stat family reports through the mutation stream wrapper, which answers it from
// url_stat() while it is registered for file://. A file that exists but cannot be read must be
// reported present, as the plain handler reports it, or code that reads it behind an existence
// check never reaches the read in a mutant child. A missing path must still be reported absent,
// raising nothing for an existence check and one warning for stat() or lstat(), and the source
// file must still be served from the override. Run by tests/check.sh with the patched tree's
// autoloader already loaded. Exits 0 when every check holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#52.

use Pest\Mutate\Support\StreamWrapper;

$dir = sys_get_temp_dir().'/mutate-unreadable-'.bin2hex(random_bytes(4));
mkdir($dir);
$locked = $dir.'/locked.txt';
$missing = $dir.'/missing.txt';
$dangling = $dir.'/dangling';
$source = $dir.'/Source.php';
$override = $dir.'/Override.php';

/**
 * Runs $call and counts the error handler's calls: every call, and the ones @ did not silence.
 * A handler still runs for a silenced warning, and some (PHPUnit's among them) act on it.
 *
 * @return array{mixed, int, int}
 */
$counted = static function (callable $call): array {
    clearstatcache();
    $calls = 0;
    $visible = 0;
    set_error_handler(static function (int $level) use (&$calls, &$visible): bool {
        $calls++;
        $visible += (error_reporting() & $level) !== 0 ? 1 : 0;

        return true;
    });
    try {
        $result = $call();
    } finally {
        restore_error_handler();
        clearstatcache();
    }

    return [$result, $calls, $visible];
};

/** @return array<string, mixed> */
$probe = static function () use ($counted, $locked, $missing, $dangling, $source): array {
    $rows = [];
    $rows['is_file(unreadable)'] = $counted(static fn (): bool => is_file($locked))[0];
    $rows['file_exists(unreadable)'] = $counted(static fn (): bool => file_exists($locked))[0];
    [$rows['is_file(missing)'], $rows['handler calls from is_file(missing)']] = $counted(static fn (): bool => is_file($missing));
    [$rows['file_exists(missing)'], $rows['handler calls from file_exists(missing)']] = $counted(static fn (): bool => file_exists($missing));
    [$rows['stat(missing)'], $rows['handler calls from stat(missing)'], $rows['warnings from stat(missing)']] = $counted(static fn (): array|false => stat($missing));
    [$rows['lstat(missing)'], $rows['handler calls from lstat(missing)'], $rows['warnings from lstat(missing)']] = $counted(static fn (): array|false => lstat($missing));
    $rows['is_link(dangling symlink)'] = $counted(static fn (): bool => is_link($dangling))[0];
    $rows['include(source)'] = include $source;

    return $rows;
};

$common = [
    'is_file(unreadable)' => true, 'file_exists(unreadable)' => true,
    'is_file(missing)' => false, 'handler calls from is_file(missing)' => 0,
    'file_exists(missing)' => false, 'handler calls from file_exists(missing)' => 0,
    'stat(missing)' => false, 'handler calls from stat(missing)' => 1, 'warnings from stat(missing)' => 1,
    'lstat(missing)' => false, 'handler calls from lstat(missing)' => 1, 'warnings from lstat(missing)' => 1,
    'is_link(dangling symlink)' => true,
];

try {
    file_put_contents($locked, "x\n");
    chmod($locked, 0o000);
    symlink($missing, $dangling);
    file_put_contents($source, "<?php\nreturn 'source';\n");
    file_put_contents($override, "<?php\nreturn 'override';\n");

    // Mode 000 does not stop a read by root, or on Windows; the unreadable case then cannot be built.
    $unreadable = ! is_readable($locked);

    $plain = $probe();
    StreamWrapper::start($source, $override);
    try {
        $wrapped = $probe();
    } finally {
        StreamWrapper::disable();
    }
} finally {
    @chmod($locked, 0o644);
    foreach ([$locked, $dangling, $source, $override] as $path) {
        if (is_link($path) || file_exists($path)) {
            unlink($path);
        }
    }
    rmdir($dir);
}

$expected = [
    'no wrapper' => [$plain, $common + ['include(source)' => 'source']],
    'wrapper started' => [$wrapped, $common + ['include(source)' => 'override']],
];

$failures = 0;
foreach ($expected as $when => [$got, $want]) {
    foreach ($want as $what => $value) {
        if (! $unreadable && str_contains($what, 'unreadable')) {
            // In CI the case must be built, or the check would pass with the patch left out.
            $ci = getenv('CI') !== false && getenv('CI') !== '';
            $failures += $ci ? 1 : 0;
            fwrite(STDOUT, sprintf("%s  %s, %s: mode 000 is readable here\n", $ci ? 'FAIL' : 'skip', $when, $what));

            continue;
        }
        $ok = $got[$what] === $value;
        $failures += $ok ? 0 : 1;
        fwrite(STDOUT, sprintf("%s  %s, %s: expected %s, got %s\n", $ok ? 'ok  ' : 'FAIL', $when, $what, var_export($value, true), var_export($got[$what], true)));
    }
}

exit($failures === 0 ? 0 : 1);
