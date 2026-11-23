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
| `8-pestphp-pest-2073.json` | `pestphp/pest#2073`, issue: a `--mutate` run given `--filter` gives each mutant child two `--filter` options, PHPUnit warns `Option --filter cannot be used more than once` and exits 1, and every mutant is scored tested | The report is closed. Tracker #8, which retires `pest-plugin-mutate-drop-run-filter`. Filed by UAMS-Web/patches-pest-plugin-mutate#74. |
| `15-pestphp-pest-plugin-mutate-46.json` | `pestphp/pest-plugin-mutate#46`, pull request | The pull request merges and a release carries it. Tracker #15, which retires `pest-plugin-mutate-ignore-annotated-statement`. |
| `9-pestphp-pest-plugin-mutate-43.json` | `pestphp/pest-plugin-mutate#43`, pull request: keep the mutant `--filter` under the kernel's per-argument limit | The pull request merges and a release carries it. Tracker #9, which retires `pest-plugin-mutate-windows-filter-length`. Moved here from the `uams-statamic` watcher. |
| `5-pestphp-pest-1916.json` | `pestphp/pest#1916`, issue: a forward-slash outer `argv[0]` fails to launch the per-mutant children on Windows | The report is closed. Tracker #5, which retires `pest-plugin-mutate-mutant-process-launch`. |
| `7-pestphp-pest-1927.json` | `pestphp/pest#1927`, issue: Windows, a quoted `--filter` breaks `cmd` when a line is covered by several tests | The report is closed. Tracker #7, which retires `pest-plugin-mutate-windows-mutant-child`. It covers the quoted-filter half of that patch; the other half is `pestphp/pest#2072`, in the next row. |
| `7-pestphp-pest-2072.json` | `pestphp/pest#2072`, issue: Windows, `StreamWrapper::stream_lock()` takes `LOCK_SH` for every request, so `LOCK_EX` writes fail in a mutant child | The report is closed. Tracker #7, which retires `pest-plugin-mutate-windows-mutant-child`. It covers the `stream_lock()` half of that patch, reported by UAMS-Web/patches-pest-plugin-mutate#72 and a native blocker of #7. |
| `7-pestphp-pest-plugin-mutate-55.json` | `pestphp/pest-plugin-mutate#55`, pull request: `StreamWrapper::stream_lock()` honors the requested lock operation, with a Windows regression test; declares that it closes `pestphp/pest#2072` | The pull request merges and a release carries it. Tracker #7, which retires `pest-plugin-mutate-windows-mutant-child`. It covers the `stream_lock()` half of that patch only; the quoted-`--filter` half waits on `pestphp/pest#1927`, `pestphp/pest-plugin-mutate#43` and `#45`, so a release is a prompt to compare with the patch, not to drop it. Opened by UAMS-Web/patches-pest-plugin-mutate#73. |
| `10-pestphp-pest-1942.json` | `pestphp/pest#1942`, issue: `@pest-mutate-ignore` silently suppresses nothing, or the wrong set | The report is closed. Tracker #10, which retires `pest-plugin-mutate-write-mutated-source` and is blocked by this issue; the same item also has a row under tracker 6. |
| `11-pestphp-pest-1937.json` | `pestphp/pest#1937`, issue: `--mutate` counts mutants as killed when the mutant subprocess never runs a test (paratest options leak into it) | The report is closed. Tracker #11, which retires `pest-plugin-mutate-drop-parallel-runner-options`. |
| `11-pestphp-pest-plugin-mutate-45.json` | `pestphp/pest-plugin-mutate#45`, pull request: stop counting a mutant whose subprocess never ran a test as killed | The pull request merges and a release carries it. Tracker #11; its description names both inherited arguments, so a release is a prompt to compare with the patch, not to drop it. |
| `15-pestphp-pest-1993.json` | `pestphp/pest#1993`, issue: a `@pest-mutate-ignore` marker above a statement does not suppress a mutator that targets the statement | The report is closed. Tracker #15, which retires `pest-plugin-mutate-ignore-annotated-statement` and is blocked by this issue. |
| `15-pestphp-pest-plugin-mutate-51.json` | `pestphp/pest-plugin-mutate#51`, pull request: honor an ignore marker on the annotated statement | The pull request merges and a release carries it. Tracker #15. |

Threads without a row, read on 2026-11-22 from each ticket's own body and a walk of every issue and pull request on `pestphp/pest` (1,823 read) and `pestphp/pest-plugin-mutate` (48 read), with the full text of the 51 `pestphp/pest` items that mention mutation and of all 48 plugin items searched. The known item `pestphp/pest#1916` is found by the same search, so it can find a thread; an absence here is a search of those texts, not proof that no upstream item exists:

- **#12** (`pest-plugin-mutate-function-defaults`): waits on nothing upstream. Its body names only a consumer ticket (`UAMS-Web/uams-2020#673`), and no item concerns test selection for a mutated function default.
- **#13** (`pest-plugin-mutate-no-test-check`): waits on nothing upstream that matches the patch. `pestphp/pest#2018` reports mutants scored as tested when no test ran, through other causes (`--processes`, a filter past PCRE's limit, timeouts); it is the nearest thread, and it is not this patch's isolated-process activation, so it has no row.
- **#14** (`pest-plugin-mutate-class-constant-coverage`): waits on the unfiled upstream report #25 and offer #26 in this repository. No upstream item exists to watch, and a row is added with the report.

The upstream reports not yet filed (#17, #18, #25, #26) have no thread to watch, and each ticket's own filing adds its row.
