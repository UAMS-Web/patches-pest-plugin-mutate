Written against v1.5.0.

The set is verified by applying the 16 patches in order to the `v5.0.2` tag of `pestphp/pest-plugin-mutate` with `git apply`, then driving the patched code.

## What runs, and where

`tests/check.sh` does this on every pull request, on each push to `main` and once a week, in the `Patches` workflow (`.github/workflows/patches.yml`). The workflow's job is skipped when the `RUN_REMOTE_CI` variable is `false`; run the same script locally for the same check.

1. It installs the pinned upstream into a temporary directory.
2. It applies every patch `composer.json` lists, in order, and fails if one does not apply.
3. It runs the behavior checks below against the patched code.

| Option | Effect |
| --- | --- |
| `SKIP_PATCH=<file> tests/check.sh` | Leaves one patch out. The negative control: the check for that patch must then fail. |
| `PATCH_DIR=<dir> tests/check.sh` | Applies the patches from another directory. |

## The behavior checks

- **`tests/timeout.php`** drives the real `MutationTest::start()` and asserts the time limit the mutant-timeout patch gives a child: for a 3.9 s initial suite, 8 s in a serial run and 56 s at 12 processes. With `SKIP_PATCH=pest-plugin-mutate-mutant-timeout.patch` the 12-process figure is 8 s and the check fails.
- **`tests/crash.php`** drives `hasFinished()` with children that end in different ways. Of the first ten, six must score a kill: one that crashes after printing a failed test, one killed by a fatal error that prints PHPUnit's premature-end line naming its test, one that dies of a compile error in the mutated file, two that run past PHP's time limit, and one that exhausts the hard memory limit. Four must be refused: one that fails without a failed test, one that dies of a fatal error before any test starts, one that dies of a fatal error in another file, and one that exits 0 with no report. The later cases cover a silent exit of `-1073741571` (Windows only), which must score a kill, silent exits of 1 and 255, which stay refused, and a child that ends through the code's own `exit` while a test runs, which must score a kill, against the same silent exit before any test starts or after every test finished, which stay refused.
- **`tests/patchwork.php`** checks that Patchwork is back in front of the stream wrapper after a pass-through read.
- **`tests/retry.php`** drives `hasFinished()` with a child that runs past its limit. One that times out once and is killed on the second attempt, and one that times out once and passes on it, are scored by the second attempt; one that times out twice is run once more without the mutation, and is scored a timeout when that run finishes and untested when it runs past the limit too (a slow covering test file). A child whose fast test fails the mutant before the slow file is scored a kill. With `SKIP_PATCH=pest-plugin-mutate-mutant-timeout-retry.patch` the first two cases score a timeout and the check fails.
- **`tests/constants.php`** drives `MutationGenerator` with a covered class that holds a constant array. With the covered lines of that class as `linesToMutate`, as `--covered-only` passes them, both constant mutants must be generated; a class with no covered line must get none; with no filter the population is unchanged. A second class carries an attribute: its constant mutants must keep their own line (not the attribute's), carry the class range for the coverage lookup, keep it through serialization, and find the tests covering the class when `MutationTest::start()` looks them up. With `SKIP_PATCH=pest-plugin-mutate-class-constant-coverage.patch` the first case generates none and the check fails.
- **`tests/junit.php`** drives the real `MutationTest::start()` with a run that was given `--log-junit=<file>` and with one given `--log-junit <file>`, and reads the child's command line. It must carry one `--log-junit` (its own), none naming the run's file, and every other option; a run given no `--log-junit` still gets the child's own. With the no-test-check patch as it stood before #64 the child carries two and the run's file, which Pest refuses.
- **`tests/activation.php`** loads the patched `src/Autoload.php` with no mutation variables set (the wrapper must not start and the source file reads unchanged), then with them set (the wrapper starts and the source file reads as the replacement), then a second time (it must not start a second wrapper). With `SKIP_PATCH=pest-plugin-mutate-isolated-process-activation.patch` it fails for the missing file.
- **`tests/unreadable.php`** checks what the stat family reports for a mode-`000` file, a missing path, a dangling symlink and the source file, with no wrapper and with the mutation stream wrapper started. With the wrapper, `is_file()` and `file_exists()` must report the unreadable file present, as they do without it; a missing path must report absent, calling no error handler for an existence check and one, unsilenced, for `stat()` or `lstat()`; `is_link()` must report the dangling symlink; and including the source file must load the override. With `SKIP_PATCH=pest-plugin-mutate-url-stat-unreadable.patch` the unreadable file reports absent and the check fails. Where mode `000` does not stop a read (as root, or on Windows) the unreadable rows are skipped, and fail when `CI` is set.

The other nine patches are checked for applying only.

## A check by hand

After the 16 patches are applied in order, applying `pest-plugin-mutate-windows-filter-length.patch` a second time must fail. `tests/check.sh` does not run this; it applies each patch once.

## What this means for a consumer

A consumer needs no test of its own that asserts the mutant-timeout patch is present in `vendor/`: this repository's check fails when the patch stops applying to the pin or stops changing the limit.

Related: [Patches](Patches.md), [Troubleshooting](Troubleshooting.md).
