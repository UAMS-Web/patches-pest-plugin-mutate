# uams-web/patches-pest-plugin-mutate

Patches that UAMS-Web repositories apply to [`pestphp/pest-plugin-mutate`](https://github.com/pestphp/pest-plugin-mutate) with [`cweagans/composer-patches`](https://github.com/cweagans/composer-patches) 2.x. One package replaces the copies each repository carried, so a patch is changed once and retires everywhere in one release.

This package pins `pestphp/pest-plugin-mutate` to exactly `5.0.2`. Every patch is written against that release.

## Install

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

Each description names the ticket that tracks the upstream fix.

## Versioning

| Change | Release |
| --- | --- |
| A patch's content changes, same upstream pin | patch |
| A patch is added or retired, or the pin moves within the upstream major | minor |
| The pin moves to a new upstream major | major |

## Verifying the set

Apply the seven patches in order to the `v5.0.2` tag of `pestphp/pest-plugin-mutate` with `git apply`. Applying `pest-plugin-mutate-windows-filter-length.patch` a second time must fail.

## License

MIT, see [`LICENSE`](LICENSE). The patches modify `pestphp/pest-plugin-mutate`, which is also MIT licensed.
