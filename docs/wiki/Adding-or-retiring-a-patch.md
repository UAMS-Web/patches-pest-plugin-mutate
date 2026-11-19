Written against v1.5.0.

This page is for changing the package itself. The detailed procedure is the `patch-package` skill, delivered to this repository under `.claude/skills/patch-package/`; this page keeps the rules a change has to respect.

## Adding a patch

1. Cut the patch against the exact upstream pin (`pestphp/pest-plugin-mutate` `5.0.2`) so that `git apply -p1` accepts it.
2. Register it under `extra.patches` in `composer.json` with a description. The description names the ticket that tracks the upstream fix, where there is one, and the path starts `vendor/uams-web/patches-pest-plugin-mutate/patches/`.
3. **Place it in the order it was cut for.** Patches apply in the order listed, and a later patch is cut against the output of the earlier ones.
4. Add its entry to the list in the README and to [Patches](Patches.md).
5. Where the patch changes behavior a check can drive, add a check under `tests/` and call it from `tests/check.sh`, and confirm that `SKIP_PATCH=<file> tests/check.sh` then fails. See [Verifying the set](Verifying-the-set.md).
6. If a ticket in this repository waits on an upstream issue or pull request, add an upstream watch row in the same pull request (see below).

## Retiring a patch

A patch retires once a fixed upstream release ships. Remove its file, its `composer.json` entry, its README and wiki entries, any check that drives it, and its watch row, and close the tracking issue. Removing a patch is a minor release; see [Versioning](Versioning.md).

## The upstream watch

The `watch-upstream` workflow (`.github/workflows/watch-upstream.yml`) reads one JSON file per upstream issue or pull request in `.github/upstream-watches/` once a day, and comments on the row's tracking issue when something moves. A directory with no rows is valid, and the run then does nothing. The row format is in [`.github/upstream-watches/README.md`](https://github.com/UAMS-Web/patches-pest-plugin-mutate/blob/main/.github/upstream-watches/README.md).

Reference: [Patches](Patches.md), [Versioning](Versioning.md).
