Written against v1.5.0.

This page takes a consumer repository from nothing to a Composer install that applies the patches in this package. At the end, `vendor/pestphp/pest-plugin-mutate` carries every patch listed on [Patches](Patches.md).

The package is read from GitHub with a `vcs` repository, not from Packagist, and the install commands are in the [README](https://github.com/UAMS-Web/patches-pest-plugin-mutate#install) rather than copied here. The steps below say where each one goes.

1. **List the repository in the root `composer.json`.** Composer reads `repositories` only from the root project, so a package that another repository installs still needs its entry in the root of the project being installed.
2. **Require the package.** An application requires it in `require`, because `cweagans/composer-patches` 2.x applies a dependency's patches only from `packages` in `composer.lock`. A package that another repository installs requires it with `--dev` and lists the patch files in its own root `patches.json`.
3. **Install.** The package pins `pestphp/pest-plugin-mutate` to exactly `5.0.2`, so the install resolves to that release.
4. **Check that the patches applied.** Each patch is a file under `vendor/uams-web/patches-pest-plugin-mutate/patches/`, and [Patches](Patches.md) names the upstream files each one changes. Reading those files under `vendor/pestphp/pest-plugin-mutate/src/` confirms the result.

**Note:** patch paths start `vendor/uams-web/patches-pest-plugin-mutate/patches/`, so a consumer with a non-default `vendor-dir` is not covered.

The full procedure for moving an existing consumer onto the package, including `composer patches-relock` and `composer patches-repatch`, is the `upstream-patch-lifecycle` skill's *Moving a consumer to the package*, delivered to each consumer repository.

A consumer needs no test of its own that asserts a patch is present in `vendor/`: this repository's check fails when a patch stops applying to the pin, and for the patches it drives, when one stops changing behavior. See [Verifying the set](Verifying-the-set.md).

Reference: [Patches](Patches.md), [Versioning](Versioning.md). If an install fails, see [Troubleshooting](Troubleshooting.md).
