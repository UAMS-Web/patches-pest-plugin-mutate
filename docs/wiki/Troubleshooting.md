Written against v1.5.0.

- [A patch does not apply](#a-patch-does-not-apply)
- [The patches are not picked up from the package](#the-patches-are-not-picked-up-from-the-package)
- [A non-default vendor directory](#a-non-default-vendor-directory)
- [The check fails after a patch changes](#the-check-fails-after-a-patch-changes)

## A patch does not apply

**Symptom:** the install reports that a patch could not be applied to `pestphp/pest-plugin-mutate`.

**Cause:** every patch is cut against exactly `pestphp/pest-plugin-mutate` `5.0.2`. A different release of that package changes the lines the patch expects.

**Fix:** let this package's `require` resolve the pin, and do not override it in the consumer. To test a patch by hand, apply the patches in order to the `v5.0.2` tag of `pestphp/pest-plugin-mutate` with `git apply -p1`, or run `tests/check.sh` in this repository.

## The patches are not picked up from the package

**Symptom:** the install succeeds and `vendor/pestphp/pest-plugin-mutate` carries no patch from this package.

**Cause:** `cweagans/composer-patches` 2.x applies a dependency's patches only from `packages` in `composer.lock`. A package that the lock file records only under `packages-dev` is not read for its patches.

**Fix:** an application requires the package in `require`; a package that another repository installs lists the patch files in its own root `patches.json`. See [Using the patches](Using-the-patches.md), then re-lock.

## A non-default vendor directory

**Symptom:** a patch path is reported missing.

**Cause:** patch paths start `vendor/uams-web/patches-pest-plugin-mutate/patches/`, which assumes the default `vendor-dir`.

**Fix:** none in this package. A consumer with another `vendor-dir` is not covered.

## The check fails after a patch changes

**Symptom:** `tests/check.sh` prints `FAIL  <patch> does not apply to upstream 5.0.2`, or one of the behavior checks exits non-zero.

**Cause:** a patch no longer applies on top of the patches listed before it, or the patched behavior no longer matches what a check under `tests/` asserts.

**Fix:** re-cut the failing patch, and every patch listed after it, against the output of the earlier ones. For a behavior check, read which assertion failed; [Verifying the set](Verifying-the-set.md) says what each one expects.
