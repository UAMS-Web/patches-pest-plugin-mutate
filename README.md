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
- **`pest-plugin-mutate-write-mutated-source.patch`**: Create the mutations directory, fail on a mutated source that cannot be written, and rebuild a cached mutation whose mutated file is missing, so a run cannot score mutants it never applied (temporary; see #2812)
- **`pest-plugin-mutate-drop-parallel-runner-options.patch`**: Drop the parallel runner's options (--passthru-php, --processes and the rest) from each mutant child so a parallel run given one cannot score every mutant killed (temporary; see #2926, #2947)

- **`pest-plugin-mutate-function-defaults.patch`**: Select tests for a mutated scalar function default, so it is not scored without a test running it (temporary; see UAMS-Web/uams-2020#673)
- **`pest-plugin-mutate-no-test-check.patch`**: Reject a test report that ran no tests unless the child crashed after printing a failed test, after PHPUnit named the test a fatal error ended, after a fatal error named the mutated file (code that does not compile), after PHP's time limit ended it, after the hard memory limit ended it, after Windows reported a stack overflow (exit -1073741571), or after the code's own exit ended it while a test was running (PHPUnit's events log shows a test prepared and not finished), so a child that ran no test cannot be scored killed and a crashing mutant does not abort the run, and drop the run's own `--log-junit` from each child, which Pest refuses beside the child's own (temporary; see UAMS-Web/UAMSWP-Logo-List#82, UAMS-Web/patches-pest-plugin-mutate#27, #34, #40, #41, #45, #58, #60, #64)
- **`pest-plugin-mutate-class-constant-coverage.patch`**: Map a class constant mutant to the tests covering its containing class, and generate it under `--covered-only` when any line of that class is covered, since a constant initializer has no executable coverage line (temporary; see UAMS-Web/patches-pest-plugin-mutate#47)
- **`pest-plugin-mutate-ignore-annotated-statement.patch`**: Skip a node whose own comment carries a matching @pest-mutate-ignore marker, including when the traverser still leaves that node (temporary)
- **`pest-plugin-mutate-mutant-timeout.patch`**: Size a mutant child's time limit from the serial work the initial suite stands for, so a --parallel run cannot score a mutant a timeout that no test caused (temporary; see UAMS-Web/patches-pest-plugin-mutate#2)
- **`pest-plugin-mutate-stream-wrapper-patchwork.patch`**: Keep Patchwork in front of the mutation stream wrapper after a pass-through read, so a function a test mocks is still redefined in a mutant child that logs to junit (temporary; see UAMS-Web/patches-pest-plugin-mutate#31)
- **`pest-plugin-mutate-mutant-timeout-retry.patch`**: Run a mutant child again when it times out once, and score a timeout only when the second attempt times out too and a third run without the mutation finishes inside the limit; a mutant that a slow covering test file overruns is scored untested, so a transient slowdown cannot keep --min=100 out of reach and a slow file cannot score a mutant caught (temporary; see UAMS-Web/patches-pest-plugin-mutate#30, #56)
- **`pest-plugin-mutate-url-stat-unreadable.patch`**: Report an existing file that cannot be read as present from the mutation stream wrapper's url_stat(), so code that reads it behind an is_file() or file_exists() check reaches the read in a mutant child (temporary; see UAMS-Web/patches-pest-plugin-mutate#52)
- **`pest-plugin-mutate-isolated-process-activation.patch`**: Start the mutation stream wrapper in an isolated PHPUnit child, which has no Pest kernel and so never runs `Mutate::boot()`, so an isolated test sees the mutated source (temporary; see UAMS-Web/UAMSWP-Logo-List#82). Split from the no-test check in UAMS-Web/patches-pest-plugin-mutate#92, so each is reported and retired on its own

Each description names the ticket that tracks the upstream fix, or the consumer ticket that moved the patch here where no upstream ticket exists.

## Versioning and verification

The [wiki](https://github.com/UAMS-Web/patches-pest-plugin-mutate/wiki) documents each patch, using the package, which change makes which release ([Versioning](https://github.com/UAMS-Web/patches-pest-plugin-mutate/wiki/Versioning)), and what `tests/check.sh` applies and asserts on every pull request ([Verifying the set](https://github.com/UAMS-Web/patches-pest-plugin-mutate/wiki/Verifying-the-set)). Its pages are kept in [`docs/wiki/`](docs/wiki/) and published from there.

## License

MIT, see [`LICENSE`](LICENSE). The patches modify `pestphp/pest-plugin-mutate`, which is also MIT licensed.
