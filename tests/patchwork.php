<?php

declare(strict_types=1);

// Checks which stream wrapper is active for file:// after a call that passes through the mutation
// wrapper while Patchwork chains to it. Patchwork must be back in front, or it is left out of every
// later include and cannot redefine a function. Run by tests/check.sh with the patched tree's
// autoloader already loaded. Exits 0 when the check holds, 1 otherwise.
// See UAMS-Web/patches-pest-plugin-mutate#31.

use Pest\Mutate\Support\StreamWrapper;

$source = tempnam(sys_get_temp_dir(), 'mutate-source-');
$override = tempnam(sys_get_temp_dir(), 'mutate-override-');

/** The class of the wrapper that opens a file now, or "native". */
$active = static function (): string {
    $handle = fopen(__FILE__, 'r');
    $data = stream_get_meta_data($handle)['wrapper_data'] ?? null;
    fclose($handle);

    return is_object($data) ? $data::class : 'native';
};

// The order a mutant child has: the mutation wrapper first, then Patchwork loaded by the bootstrap.
StreamWrapper::start($source, $override);
// $argv[1] is the patched tree's vendor/autoload.php, as tests/check.sh passes it.
require_once dirname((string) $GLOBALS['argv'][1]).'/antecedent/patchwork/Patchwork.php';

$before = $active();

// Patchwork delegates a read to the wrapper it chains to by calling its method on an instance, as here.
(new StreamWrapper)->url_stat(__FILE__, 0);
$after = $active();

unlink($source);
unlink($override);
StreamWrapper::disable();

$failures = 0;
foreach (['first read' => $before, 'after a pass-through call on the wrapper' => $after] as $what => $got) {
    $want = 'Patchwork\CodeManipulation\Stream';
    $ok = $got === $want;
    $failures += $ok ? 0 : 1;
    fwrite(STDOUT, sprintf("%s  active wrapper, %s: expected %s, got %s\n", $ok ? 'ok  ' : 'FAIL', $what, $want, $got));
}

exit($failures === 0 ? 0 : 1);
