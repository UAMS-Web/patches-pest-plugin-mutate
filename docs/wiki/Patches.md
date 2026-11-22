Written against v1.5.0.

The patches are applied in the order they appear under `extra.patches` in `composer.json`, which is the order below. A later patch is cut against the output of the earlier ones, so none is reordered without re-cutting those after it. Every patch is written against `pestphp/pest-plugin-mutate` `5.0.2`, and every one is temporary: it retires once a fixed upstream release ships (see [Adding or retiring a patch](Adding-or-retiring-a-patch.md)).

There are 15 patches. Each file is under `patches/` in this repository, and the paths it changes are under `src/` in `pestphp/pest-plugin-mutate`.

Each entry names the ticket that tracks the upstream fix, or the consumer ticket that moved the patch here where no upstream ticket exists.

| # | Patch | Changes | Tracked by |
| --- | --- | --- | --- |
| 1 | `pest-plugin-mutate-mutant-process-launch.patch` | `Tester/MutationTestRunner.php` | [`UAMS-Web/uams-statamic-archive#2322`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2322) |
| 2 | `pest-plugin-mutate-annotation-parsing.patch` | `Support/MutationGenerator.php`, `Support/NodeVisitor.php` | [`UAMS-Web/uams-statamic-archive#2327`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2327) |
| 3 | `pest-plugin-mutate-windows-mutant-child.patch` | `MutationTest.php`, `Support/StreamWrapper.php` | [`UAMS-Web/uams-statamic-archive#2524`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2524) |
| 4 | `pest-plugin-mutate-drop-run-filter.patch` | `MutationTest.php` | [`UAMS-Web/uams-statamic-archive#2570`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2570) |
| 5 | `pest-plugin-mutate-windows-filter-length.patch` | `MutationTest.php` | [`UAMS-Web/uams-statamic-archive#2578`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2578), [`#3114`](https://github.com/UAMS-Web/uams-statamic-archive/issues/3114) |
| 6 | `pest-plugin-mutate-write-mutated-source.patch` | `Mutation.php`, `Support/MutationGenerator.php` | [`UAMS-Web/uams-statamic-archive#2812`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2812) |
| 7 | `pest-plugin-mutate-drop-parallel-runner-options.patch` | `MutationTest.php` | [`UAMS-Web/uams-statamic-archive#2926`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2926), [`#2947`](https://github.com/UAMS-Web/uams-statamic-archive/issues/2947) |
| 8 | `pest-plugin-mutate-function-defaults.patch` | `Mutation.php` | [`UAMS-Web/uams-2020#673`](https://github.com/UAMS-Web/uams-2020/issues/673) |
| 9 | `pest-plugin-mutate-isolated-processes.patch` | `Autoload.php`, `MutationTest.php`, `Plugins/Mutate.php` | [`UAMS-Web/UAMSWP-Logo-List#82`](https://github.com/UAMS-Web/UAMSWP-Logo-List/issues/82), and in this repository [`#27`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/27), [`#34`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/34), [`#40`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/40), [`#41`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/41), [`#45`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/45) |
| 10 | `pest-plugin-mutate-class-constant-coverage.patch` | `Mutation.php`, `Support/NodeVisitor.php` | [`UAMS-Web/patches-pest-plugin-mutate#47`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/47) |
| 11 | `pest-plugin-mutate-ignore-annotated-statement.patch` | `Support/NodeVisitor.php` | none recorded |
| 12 | `pest-plugin-mutate-mutant-timeout.patch` | `MutationTest.php` | [`UAMS-Web/patches-pest-plugin-mutate#2`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/2) |
| 13 | `pest-plugin-mutate-stream-wrapper-patchwork.patch` | `Support/StreamWrapper.php` | [`UAMS-Web/patches-pest-plugin-mutate#31`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/31) |
| 14 | `pest-plugin-mutate-mutant-timeout-retry.patch` | `MutationTest.php` | [`UAMS-Web/patches-pest-plugin-mutate#30`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/30) |
| 15 | `pest-plugin-mutate-url-stat-unreadable.patch` | `Support/StreamWrapper.php` | [`UAMS-Web/patches-pest-plugin-mutate#52`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/issues/52) |

## What each patch does

1. **Mutant process launch.** Launches each mutant child process, so a run cannot score every mutant killed without executing one.
2. **Annotation parsing.** Stops three silent `@pest-mutate-ignore` parsing defects that suppressed nothing or the wrong set.
3. **Windows mutant child.** Lets a mutant child on Windows run its tests and write files, so its failure is a real kill.
4. **Drop the run filter.** Drops the run's own `--filter` from each mutant child, so a filtered run cannot score every mutant killed.
5. **Windows filter length.** Widens or refuses a mutant child's filter that PCRE cannot compile, or that would pass `cmd.exe`'s command-line limit on Windows, so it cannot score a kill without running a test.
6. **Write the mutated source.** Creates the mutations directory and fails on a mutated source that cannot be written, so a run cannot score mutants it never applied.
7. **Drop the parallel runner's options.** Drops `--passthru-php`, `--processes` and the parallel runner's other options from each mutant child, so a parallel run given one cannot score every mutant killed.
8. **Function defaults.** Selects tests for a mutated scalar function default, so it is not scored without a test running it.
9. **Isolated processes.** Propagates a mutation into isolated test processes and rejects a test report that ran no tests, so an isolated run cannot score a mutant killed without running a test. A report with no tests still scores a kill when the child crashed after printing a failed test, after PHPUnit named the test a fatal error ended, after a fatal error named the mutated file (code that does not compile), after PHP's time limit ended it, or after the hard memory limit ended it, so a crashing mutant does not abort the run.
10. **Class constant coverage.** Maps a class constant mutant to the tests covering its containing class, and generates it under `--covered-only` when any line of that class is covered, since a constant initializer has no executable coverage line.
11. **Ignore an annotated statement.** Skips a node whose own comment carries a matching `@pest-mutate-ignore` marker, including when the traverser still leaves that node.
12. **Mutant timeout.** Sizes a mutant child's time limit from the serial work the initial suite stands for, so a `--parallel` run cannot score a mutant a timeout that no test caused.
13. **Stream wrapper and Patchwork.** Keeps Patchwork in front of the mutation stream wrapper after a pass-through read, so a function a test mocks is still redefined in a mutant child that logs to junit.
14. **Mutant timeout retry.** Runs a mutant child again when it times out once, and scores a timeout only when the second attempt times out too and a third run without the mutation finishes inside the limit. A mutant that a slow covering test file overruns is scored untested, so a transient slowdown cannot keep `--min=100` out of reach and a slow file cannot score a mutant caught.
15. **Unreadable files in `url_stat()`.** Reports an existing file that cannot be read as present from the mutation stream wrapper's `url_stat()`, as the plain `file://` handler does, so code that reads it behind an `is_file()` or `file_exists()` check reaches the read in a mutant child. A missing path is still reported absent.

Related: [Verifying the set](Verifying-the-set.md), [Versioning](Versioning.md), [Adding or retiring a patch](Adding-or-retiring-a-patch.md).
