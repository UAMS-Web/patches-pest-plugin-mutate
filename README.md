# uams-web/patches-pest-plugin-mutate

Patches that UAMS-Web repositories apply to [`pestphp/pest-plugin-mutate`](https://github.com/pestphp/pest-plugin-mutate) with [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) 2.x. One package replaces the copies each repository carried, so a patch is changed once and retires everywhere in one release.

This package pins `pestphp/pest-plugin-mutate` to exactly `5.0.2`. Every patch is written against that release.

## Install

This package is read from GitHub with a `vcs` repository, not from Packagist. The consumer's root `composer.json` lists it (Composer reads `repositories` only from the root project):

```json
"repositories": [
  { "type": "vcs", "url": "https://github.com/UAMS-Web/patches-pest-plugin-mutate.git" }
]
```

An application requires it in `require` (the plugin applies a dependency's patches only from `packages` in `composer.lock`):

```bash
composer require --no-plugins uams-web/patches-pest-plugin-mutate:^1.0
```

A package that another repository installs requires it with `--dev` and lists the patch files in its own root `patches.json`. The full procedure, including `composer patches-relock` and `composer patches-repatch`, is the `upstream-patch-lifecycle` skill's *Moving a consumer to the package*.

Patch paths are relative to the consumer's root and start `vendor/uams-web/patches-pest-plugin-mutate/patches/`, so a consumer with a non-default `vendor-dir` is not covered.

## Patches

Applied in this order, which is the order in `composer.json`. A later patch is cut against the output of the earlier ones, so none is reordered without re-cutting those after it.

- **`pest-plugin-mutate-mutant-process-launch.patch`**: Launch each mutant child process so a run cannot score every mutant killed without executing one (temporary; see #2322)
- **`pest-plugin-mutate-annotation-parsing.patch`**: Stop three silent @pest-mutate-ignore parsing defects suppressing nothing or the wrong set (temporary; see #2327)
- **`pest-plugin-mutate-windows-mutant-child.patch`**: Let a mutant child on Windows run its tests and write files, so its failure is a real kill (temporary; see #2524)
- **`pest-plugin-mutate-drop-run-filter.patch`**: Drop the run's own --filter from each mutant child so a filtered run cannot score every mutant killed (temporary; see #2570)
- **`pest-plugin-mutate-windows-filter-length.patch`**: Widen or refuse a mutant child's filter that PCRE cannot compile, or that would pass cmd.exe's command-line limit on Windows, so it cannot score a kill without running a test (temporary; see #2578, #3114)
- **`pest-plugin-mutate-write-mutated-source.patch`**: Create the mutations directory and fail on a mutated source that cannot be written, so a run cannot score mutants it never applied (temporary; see #2812)
- **`pest-plugin-mutate-drop-parallel-runner-options.patch`**: Drop the parallel runner's options (--passthru-php, --processes and the rest) from each mutant child so a parallel run given one cannot score every mutant killed (temporary; see #2926, #2947)

- **`pest-plugin-mutate-function-defaults.patch`**: Select tests for a mutated scalar function default, so it is not scored without a test running it (temporary; see UAMS-Web/uams-2020#673)
- **`pest-plugin-mutate-isolated-processes.patch`**: Propagate a mutation into isolated test processes and reject a test report that ran no tests unless the child crashed after printing a failed test or after PHPUnit named the test a fatal error ended, so an isolated run cannot score a mutant killed without running a test and a crashing mutant does not abort the run (temporary; see UAMS-Web/UAMSWP-Logo-List#82, UAMS-Web/patches-pest-plugin-mutate#27, #34)
- **`pest-plugin-mutate-class-constant-coverage.patch`**: Map a class constant mutant to the tests covering its containing class, since a constant initializer has no executable coverage line (temporary)
- **`pest-plugin-mutate-ignore-annotated-statement.patch`**: Skip a node whose own comment carries a matching @pest-mutate-ignore marker, including when the traverser still leaves that node (temporary)
- **`pest-plugin-mutate-mutant-timeout.patch`**: Size a mutant child's time limit from the serial work the initial suite stands for, so a --parallel run cannot score a mutant a timeout that no test caused (temporary; see UAMS-Web/patches-pest-plugin-mutate#2)
- **`pest-plugin-mutate-stream-wrapper-patchwork.patch`**: Keep Patchwork in front of the mutation stream wrapper after a pass-through read, so a function a test mocks is still redefined in a mutant child that logs to junit (temporary; see UAMS-Web/patches-pest-plugin-mutate#31)

Each description names the ticket that tracks the upstream fix, or the consumer ticket that moved the patch here where no upstream ticket exists.

## Versioning

| Change | Release |
| --- | --- |
| A patch's content changes, same upstream pin | patch |
| A patch is added or retired, or the pin moves within the upstream major | minor |
| The pin moves to a new upstream major | major |

## Verifying the set

Apply the thirteen patches in order to the `v5.0.2` tag of `pestphp/pest-plugin-mutate` with `git apply`. Applying `pest-plugin-mutate-windows-filter-length.patch` a second time must fail.

`tests/check.sh` does this on every pull request, in `.github/workflows/patches.yml`. It installs the pinned upstream, applies every patch `composer.json` lists and fails if one does not apply. It then drives the real `MutationTest::start()` and asserts the time limit the mutant-timeout patch gives a child: for a 3.9 s initial suite, 8 s in a serial run and 56 s at 12 processes. `tests/crash.php` drives `hasFinished()` with five children: one that crashes after printing a failed test, and one killed by a fatal error that prints PHPUnit's premature-end line naming its test, must score a kill; one that fails without a failed test, one that dies of a fatal error before any test starts, and one that exits 0 with no report must be refused. With the timeout patch left out (`SKIP_PATCH=pest-plugin-mutate-mutant-timeout.patch tests/check.sh`) the 12-process figure is 8 s and the check fails.

A consumer therefore needs no test of its own that asserts the mutant-timeout patch is present in `vendor/`: this repository's check fails when the patch stops applying to the pin or stops changing the limit. `tests/patchwork.php` also checks that Patchwork is back in front of the stream wrapper after a pass-through read. The other eleven patches are checked for applying only.

## License

MIT, see [`LICENSE`](LICENSE). The patches modify `pestphp/pest-plugin-mutate`, which is also MIT licensed.
