This wiki documents `uams-web/patches-pest-plugin-mutate`, the Composer package that carries the patches UAMS-Web repositories apply to `pestphp/pest-plugin-mutate`. The [README](https://github.com/UAMS-Web/patches-pest-plugin-mutate#readme) keeps the overview, the install steps and the list of patches; the wiki holds the rest.

**Getting started**

- [Installation (README)](https://github.com/UAMS-Web/patches-pest-plugin-mutate#install): the `vcs` repository entry and the `composer require` line.
- [Using the patches](Using-the-patches.md): wire the package into a consumer and confirm the patches applied.

**Reference**

- [Patches](Patches.md): each patch, the upstream files it changes and the ticket that tracks it.
- [Versioning](Versioning.md): which change in this package makes which release.
- [Verifying the set](Verifying-the-set.md): what `tests/check.sh` applies and asserts on every pull request.

**Maintaining the package**

- [Adding or retiring a patch](Adding-or-retiring-a-patch.md): the order rule, the upstream pin and the upstream watch.

**Troubleshooting**

- [Troubleshooting](Troubleshooting.md): a patch that does not apply, a patch that is not picked up, and a failing check.
