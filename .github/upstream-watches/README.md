<!--
  Synced from UAMS-Web/uams-claude-skills: shared/.claude/skills/patch-package/templates/upstream-watches-README.md
  Edit it there, not here; the next sync overwrites this copy.
  Delivered to this repository through the profile(s): patches.
-->
# Upstream watches

<!-- cspell:ignore Packagist -->

One file per upstream issue or pull request this repository is waiting on. The `watch-upstream` workflow (`.github/workflows/watch-upstream.yml`) reads every `*.json` file here once a day and comments on the row's tracking issue when something moves. This README is also what keeps the directory tracked while it holds no rows, which is a valid state: the workflow then has nothing to do.

## Row format

Name the file `<tracker>-<owner>-<repo>-<number>.json`, for example `412-laravel-framework-58123.json` for tracker `412` watching `laravel/framework#58123` (`/` in the repository becomes `-`). The loader rejects a file whose name does not match its contents, so the name is always unique and says what it watches.

```json
{ "tracker": 412, "repo": "laravel/framework", "kind": "pr", "number": 58123, "composer": "laravel/framework", "released": true }
```

| Key | Required | Meaning |
| --- | --- | --- |
| `tracker` | yes | Issue number in this repository that receives the comments. Closing it turns the watch off. |
| `repo` | yes | The upstream repository, `owner/name`. |
| `kind` | yes | `issue` or `pr`. |
| `number` | yes | The upstream issue or pull request number. |
| `composer` | no | A `vendor/package` name. Adds a signal for a Packagist release newer than the version in `composer.lock`. |
| `released` | no | `true` on a `pr` row only. Adds a signal for the first release on the pull request's base-branch line that contains the merge commit. |

Unknown keys, a missing key and a wrong type fail the run with the file named, so a malformed row is never silently skipped.

## Adding and retiring a row

- **Add** a row in the same pull request that adds the local patch it exists for, so the patch and its removal trigger land together.
- **Retire** a row in the pull request that drops the patch: delete the file and close the tracking issue. Closing the tracker alone stops the comments; deleting the file stops the workflow reading it.
- Two rows under one tracker share an off switch, so give threads separate trackers whenever either can outlive the other.

## Notes on current rows

No row names `composer`: this repository commits no `composer.lock`, so there is no installed version for a Packagist signal to compare against. A `pr` row with `released: true` covers the release signal instead, which is the condition that retires a patch.

| Row | Upstream thread | Waits for |
| --- | --- | --- |
| `6-pestphp-pest-1942.json` | `pestphp/pest#1942`, issue: `@pest-mutate-ignore` silently suppresses nothing, or the wrong set | The report is closed. Tracker #6, which retires `pest-plugin-mutate-annotation-parsing`. |
| `6-pestphp-pest-plugin-mutate-46.json` | `pestphp/pest-plugin-mutate#46`, pull request: honor a bare `@pest-mutate-ignore` | The pull request merges and a release carries it. Tracker #6; a bare-marker fix may cover only part of the patch, so a release is a prompt to compare, not to drop the patch. |
| `15-pestphp-pest-plugin-mutate-46.json` | `pestphp/pest-plugin-mutate#46`, pull request | The pull request merges and a release carries it. Tracker #15, which retires `pest-plugin-mutate-ignore-annotated-statement`. |
| `9-pestphp-pest-plugin-mutate-43.json` | `pestphp/pest-plugin-mutate#43`, pull request: keep the mutant `--filter` under the kernel's per-argument limit | The pull request merges and a release carries it. Tracker #9, which retires `pest-plugin-mutate-windows-filter-length`. Moved here from the `uams-statamic` watcher. |

Threads without a row: the upstream reports not yet filed (#17, #18, #25, #26, #33) have no thread to watch, and each ticket's own filing adds its row. The other retirement tickets (#5, #7, #8 and #10 to #14) name no upstream thread, so there is nothing for a row to point at.
