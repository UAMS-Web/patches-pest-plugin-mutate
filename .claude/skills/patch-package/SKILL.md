---
name: patch-package
description: >-
  The upstream-facing and package-side half of carrying a vendor Composer patch, for a
  `uams-web/patches-*` repository: the shared patch package itself (layout, the exact upstream
  pin, the consumer-root patch path, `extra.patches`, the listed order), its versioning, and its
  retirement once a fixed upstream release ships; tickets 2 and 3 of the four-ticket chain (filing
  the bug upstream and opening the upstream pull request, each `afk` with a human approving the
  post, and when the claim verification runs); the two transitions that keep the chain connected;
  the cross-repo REST recipe for making an upstream issue a blocker on the removal ticket; where to
  file when the upstream tracker is not where it seems (`has_issues`); writing and documenting a
  patch; the tags-not-Releases check for whether the fix shipped, and the upstream watcher and its
  templates; and the upstream-request variant, where no patch exists and the filing ticket closes
  once the item is posted. Activate when filing a patch's bug or pull request upstream, wiring an
  upstream issue into a removal ticket, checking whether an upstream fix has shipped, adopting the
  watcher or editing an upstream watch row, adding, changing or retiring a patch in the package,
  cutting the package's version, or closing out an upstream request. The consumer side (registering a patch, requiring this package,
  updating the lock files, and removing a patch in its load-bearing order) is the `upstream-patch-lifecycle`
  skill in each consumer repository.
---
<!--
  Synced from UAMS-Web/uams-claude-skills: shared/.claude/skills/patch-package/SKILL.md
  Edit it there, not here; the next sync overwrites this copy.
  Delivered to this repository through the profile(s): patches.
-->
<!-- cspell:ignore Packagist retargeted -->

# Maintain a shared patch package and its upstream fix

This repository is a shared patch package: it holds the patches two or more UAMS-Web
repositories apply to one upstream package, and the retirement ticket for each. A patch's
lifecycle is a four-ticket chain (write the patch, file the bug upstream, open the upstream pull
request, retire the patch once a release carries the fix). This skill is the part of it that
happens here: tickets 2 and 3, the package, and its retirement. The consumer side (how a
repository registers a patch, requires this package, updates its lock files, and removes a patch in order) is
the `upstream-patch-lifecycle` skill, which each consumer repository carries (UAMS-Web/uams-claude-skills#702).

---

## Tickets 2 and 3: filing upstream and offering the fix

Tickets 2 and 3 are `afk`, like 1 and 4. An agent does all the preparation: checking the
upstream tracker for an existing report, drafting the issue or pull-request body, and preparing
and testing the branch. The one step it does not take alone is the post itself, because the post
appears on a third-party public tracker under a maintainer's own identity: the agent asks a human
to approve it, and posts only after that approval. That approval is a step inside the ticket, not a
reason to label the whole ticket `hitl`, which would leave the preparation waiting for a person
too (`UAMS-Web/uams-statamic-archive#2772`). There is no decision to make either, so the
[`design-decision-forks`](../../rules/design-decision-forks.md) rule does not apply to them.

**Tickets 2 and 3 verify the draft before the approval is requested.** The
[`verify-claims-before-a-third-party-post`](../../rules/verify-claims-before-a-third-party-post.md)
rule owns the requirement: every claim in the draft is checked three ways, each by an
independent method, and the claims and the checks are recorded on the filing ticket. This chain
owns only the timing. The checks run **after the local patch from ticket 1 is merged and
installed**, so they exercise the shipped fix rather than a working copy, and **after the last
edit to the draft**. A draft written before the patch existed is re-verified at that point, not
trusted. Two things the rule's claim list names are specific to a patch chain and are easy to
leave stale in a draft written early: the revision of the upstream package the reproduction was
run against, and any "no existing report" statement, which is re-searched at that point because
the upstream tracker has moved since the draft was written.

### The two transitions that carry the subtlety

Both are easy to miss, and missing either leaves the chain silently broken.

- **Filing the upstream issue satisfies and closes ticket 2, *and* that upstream issue must
  also be added as a blocker on ticket 4.** Closing ticket 2 otherwise disconnects the thing we
  are waiting on from the work that waits on it, and ticket 4 sits looking unblocked while
  nothing upstream has happened.
- **Opening the upstream PR satisfies and closes ticket 3.** Ticket 4 then stays blocked until
  the fix is both **merged** and **tagged**, which are separate events. A merged PR on a branch
  we cannot install retires nothing.

### The cross-repo `blocked_by` recipe

`blocked_by` resolves against a **public repo we do not own**: verified 2026-08-04 in
`uams-statamic` by adding [`pestphp/pest#1790`](https://github.com/pestphp/pest/issues/1790) as a
blocker on its #1898, where it is still readable. The endpoint takes the issue's **numeric database
`id`**, not its issue number:

```bash
# 1. Resolve the upstream issue's DB id. For pestphp/pest#1790 this is 5014035150: NOT 1790.
id=$(gh api repos/pestphp/pest/issues/1790 --jq '.id')

# 2. Add it as a blocker on OUR removal ticket.
gh api -X POST repos/<owner>/<repo>/issues/<removal-ticket>/dependencies/blocked_by -F issue_id=$id

# 3. Read it back: always confirm, the POST is easy to aim at the wrong issue.
gh api repos/<owner>/<repo>/issues/<removal-ticket>/dependencies/blocked_by \
  --jq '.[] | "\(.repository.full_name)#\(.number)"'
```

Passing the issue *number* where the id belongs either 404s or, worse, silently links whatever
unrelated issue happens to carry that database id. The endpoint's REST-only status and the
budget it protects are the [`github-api-budget`](../../rules/github-api-budget.md) rule.

### Where to file when the upstream tracker is not where you think

Check `has_issues` before drafting anything, and check **only** that field.
`pestphp/pest-plugin-mutate` has issues **disabled**, so UAMS-Web/uams-statamic-archive#1729 had to be
retargeted to Pest core mid-flight. Its `open_issues_count` is actively misleading, with issues
off, GitHub still counts pull requests there, so the repo reads as a live tracker with a handful
of open reports (7 when #1729 checked it, 4 on a later check, never once an issue):

```bash
gh api repos/pestphp/pest-plugin-mutate --jq '{has_issues, open_issues_count}'
# {"has_issues": false, "open_issues_count": 4}   <- every one of those is a PR
```

When a plugin's issues are off, the **parent repo is the intake**: that is where its bugs are
filed in practice. Match the parent's issue form (Pest core uses `bug_report.yml`, requiring
What Happened / How to Reproduce / Pest Version / PHP Version / Operating System) and shape the
draft to it before filing. The PR still goes to the plugin repo; only the issue moves.

---

## The shared package

### The package

```text
patches-pest-plugin-mutate/
  composer.json
  patches/<basename>.patch      the patch files, unchanged in form
  README.md                     per-patch narrative: problem, fix, tracking ticket, retirement condition
```

```json
{
    "name": "uams-web/patches-pest-plugin-mutate",
    "description": "Patches UAMS-Web repositories apply to pestphp/pest-plugin-mutate",
    "type": "library",
    "require": {
        "pestphp/pest-plugin-mutate": "5.0.2"
    },
    "extra": {
        "patches": {
            "pestphp/pest-plugin-mutate": {
                "<what it fixes> (temporary; see <ticket>)": "vendor/uams-web/patches-pest-plugin-mutate/patches/<basename>.patch"
            }
        }
    }
}
```

One detail in that file is chosen and two are measured. The measurements are in
`UAMS-Web/uams-claude-skills#548`'s Findings: `cweagans/composer-patches` 2.0.0, PHP 8.4.27 and
Composer 2.11.1, on Windows 11 and on macOS 27.3, against path repositories carrying one patch.
Resolution through a `vcs` entry, with eight patches in one package consumer, is measured on a real
consumer under *Moving a consumer to the package* in the `upstream-patch-lifecycle` skill. Several patch packages in one consumer, the
application form through a `vcs` entry, and an application whose plugin sits in `require-dev` are
not yet measured.

- **The upstream requirement is an exact version (chosen).** The patches are written against one
  release, so the pin is meant to make a version mismatch fail at resolution, by Composer's own
  rules, rather than at patch time. A mismatch was not part of the measurement.
- **The patch path is relative to the consumer's root**, not to the package: the plugin resolves
  a dependency's path against the root project, so it must start
  `vendor/uams-web/patches-<package>/`. A consumer with a non-default `vendor-dir` is not covered
  by that path.
- **Patches are declared in `extra.patches`, not a `patches.json`.** The plugin reads a
  dependency's `extra.patches` from the consumer's `composer.lock`, and reading a dependency's
  patches file is unimplemented. This is the leak *Where a patch is registered* in the `upstream-patch-lifecycle` skill warns about, used
  deliberately: here the dependency's patches are the point.

**The listed order is the order the plugin collects the patches in** (2.0.0, source read; in the
eight-patch install measured under *Moving a consumer to the package* in the `upstream-patch-lifecycle` skill, the patches applied in
`patches.json`'s listed order). Where a later patch touches lines an earlier one
edits, it is cut against the earlier one's output, so a patch is never reordered without re-cutting
the ones after it. Verify a set by applying it in that order to the pinned upstream tag with
`git apply`, and show that applying one a second time fails.

### Versioning

The package carries its own semantic version, and each release pins exactly one upstream version:

| Change | Release |
| --- | --- |
| A patch's content changes, same upstream pin | patch (`1.0.1`) |
| A patch is added or retired, or the pin moves within the upstream major | minor (`1.1.0`) |
| The pin moves to a new upstream major | major (`2.0.0`) |

A consumer requires the package with a caret range (`^1.0`) and updates it with
`composer update uams-web/patches-<package> --with-dependencies`, so the upstream package can move
with it. A targeted update without `--with-dependencies` holds the upstream package at its locked
version, and Composer then stays on the newest package release pinned to that version without
saying so. Where the consumer's other constraints hold the upstream package back, the same
selection keeps it on an older package release.

### Retirement

A patch retires from the package, not from each consumer. When an upstream release carries a fix,
the package releases without that patch and with its pin moved to that upstream version, in one
release, so inside the package the patch comes out in the same step the upstream package moves.
The package repository is archived once the last patch retires; the consumer side of retirement
(adopting the release, and removing the package) is in the `upstream-patch-lifecycle` skill.

---

## Writing and documenting a patch

Every patch here is **hand-written**: edit the `.patch` directly. There is no snippet pair and no
generator command.

Document it: the `patches/README.md` section where the repository has one (problem, local fix,
**the retirement condition**, and a removal checklist naming every file that dies with the
patch: the guard test, its PHPStan pins, any generator command and snippet pair); otherwise the
description key, the `_comment` array in `patches.json`, or the decision log.

Registering it in a consumer, the relock, `composer validate` and `composer install -v` are *Adding a patch* in the `upstream-patch-lifecycle` skill.

---

## Checking whether the fix shipped

**Do not consult the Releases feed.** `pest-plugin-mutate` publishes **tags, not GitHub
Releases**: its newest Release is `v2.0.0-beta.5` from 2024-08-05, while `v5.0.1` shipped
2026-08-04. Watching Releases waits forever. The tag is the release, and Packagist serves it.

Three checks, in order:

```bash
# 1. Is there a tag past what we have? (Packagist is the authority on installability.)
curl -sS https://repo.packagist.org/p2/pestphp/pest-plugin-mutate.json \
  | jq -r '[.packages["pestphp/pest-plugin-mutate"][].version] | .[0:5][]'

# 2. Does that tag actually contain the fix? `identical` means the tag is at the branch tip.
gh api repos/pestphp/pest-plugin-mutate/compare/v5.0.1...5.x --jq '.status'

# 3. What do we have locked?
jq -r '[.packages[], ."packages-dev"[]] | map(select(.name == "pestphp/pest-plugin-mutate")) | .[0].version' composer.lock
```

Without `jq`, `php -r` reads the same JSON:

```bash
php -r '$l=json_decode(file_get_contents("composer.lock"),true);
  foreach(array_merge($l["packages"],$l["packages-dev"]) as $p){
    if($p["name"]==="pestphp/pest-plugin-mutate"){echo $p["version"],"\n";}}'
```

A merge is not a release, and a release on the wrong major line is not a release *for us*.
Upstream PRs `pestphp/pest#1742` and `#1757` implemented a parallel-merge fix `uams-statamic`
once carried as a patch, but both were based on `4.x`, so neither could reach the 5.x line that
repository runs even if merged. The patch stayed until `pestphp/pest` `v5.2.0` shipped an
equivalent fix on 5.x (`UAMS-Web/uams-statamic-archive#2414`).

**No upstream watcher runs here until the repository adopts one.** The skill ships one in
[`templates/`](templates/), and the sync never writes a target's `.github/`, so adoption is a
copy, done once:

| Copy this template | To |
| --- | --- |
| `templates/watch-upstream.yml` | `.github/workflows/watch-upstream.yml` |
| `templates/load-upstream-watches.mjs` | `scripts/load-upstream-watches.mjs` |
| `templates/check-upstream-release.mjs` | `scripts/check-upstream-release.mjs` |
| `templates/upstream-watches-README.md` | `.github/upstream-watches/README.md` |

Add a row file per upstream signal to `.github/upstream-watches/` when the chain reaches ticket 2
or 3 (the README gives the format). The sentinel is **per-signal**, so one tracker can carry both
an issue row and a PR row and each notifies once. A row naming `composer` also polls Packagist for
a release past the pin in `composer.lock`, which is the condition that retires the patch; a `pr`
row with `released: true` also comments once when the newest stable tag on the pull request's
base-branch line contains the merge commit. Closing the tracking issue turns its watch off. Each
row is its own file, so two pull requests that add rows never conflict. A directory holding only
the README is valid and the run does nothing, while a missing directory or a malformed row fails
the run, so a wrong path never reads as nothing to watch. To tag a person on a notification, set
the repository variable `UPSTREAM_WATCH_NOTIFY` (for example `@example-user`).

Dependabot stays the second hook: it opens a pull request when the patched package has a new
release, and a bump of the patched package is exactly what obsoletes a patch. The
`update-dependencies-ticket` skill, in each consumer, re-confirms the patch
stack on every Dependabot pull request that changes `composer.lock`, for every patched package; check it there.

---

## The upstream-request variant: nothing to retire

Not every `upstream` ticket carries a patch. A feature request, a documentation request, or a
pull request offered without a local patch behind it has no removal ticket and no release to wait
for. What such a ticket usually has left, once the item is posted, is one criterion that only a
maintainer can satisfy: "if upstream answers X, do Y". Left in place, that criterion holds the
ticket open indefinitely for an answer it cannot produce. Six were open in `uams-statamic` on
2026-09-30 on that ground alone.

**The post still carries the claim verification.** A request with no patch behind it goes to
the same third-party tracker under a maintainer's identity, and its claims cost the same when
they are wrong. The
[`verify-claims-before-a-third-party-post`](../../rules/verify-claims-before-a-third-party-post.md)
rule applies in full. There is no local patch to wait for, so the checks run immediately before
the approval is requested, against the current upstream release named in the draft; a draft
written earlier is re-verified at that point, and the claims and checks are recorded on the
filing ticket first.

**The rule (decided 2026-09-30, `UAMS-Web/uams-statamic-archive#2723`):**

1. **The filing ticket closes once the item is posted.** Its own work, drafting and filing
   the upstream item and recording the link, is done at that point.
2. **"Act on the answer" moves to a follow-up ticket.** File it per
   [`writing-issues`](../writing-issues/SKILL.md), with a native cross-repo `blocked_by` edge
   on the upstream item (the recipe is under *The cross-repo `blocked_by` recipe* above), and
   read the edge back. On the filing ticket, **strike** the moved criterion rather than
   ticking it, and close with a one-line comment naming the follow-up.

   **A pull request cannot be the blocker.** The endpoint refuses one with `Validation failed:
   Target issue may only be an issue` (HTTP 422). When the upstream item is a pull request,
   put the edge on the issue it fixes, so the merge that answers the follow-up also closes
   its blocker. Any watch still tracks the pull request, because that is where the answer
   arrives.
3. **Where the repository has an upstream watcher, its row tracks the follow-up, never the
   filing ticket.** A row tracking the filing ticket would go dead the moment step 1 closes it,
   which is the same reason the patch chain's rows track the removal ticket.

**Order, when the filing ticket is already open and the item already posted:** file the
follow-up and its edge, add any watch, and only then close the filing ticket. Closed first,
nothing would be watching the upstream item in the gap.

**When a follow-up already exists** (a parent feature the request was filed for, already
blocked by the upstream item), it *is* the follow-up: point any watch at it and close the
filing ticket.

**Rejected alternatives, so they are not re-proposed:**

- **Split without a watch.** The follow-up's edge records the dependency, but nothing surfaces
  the maintainer's answer when it arrives; the follow-up is found only by someone who goes
  looking.
- **One umbrella tracker for every pending upstream item.** It collapses independent answers
  onto one ticket, so no answer gets an owner or a close, and every watch row would point at a
  ticket that can never close.

---

## The DRY line

This skill owns the **upstream-facing and package-side** work of a patch, and nothing else.

- **The consumer side**: the two registration forms, requiring this package, moving a consumer to
  it, `patches.lock.json` in a consumer, the relock, the removal order and its proof, and the
  traps already paid for, stay in the `upstream-patch-lifecycle` skill, with the four-ticket chain
  as the shape.
- **What is verified before an upstream post, and how** stays in the
  [`verify-claims-before-a-third-party-post`](../../rules/verify-claims-before-a-third-party-post.md)
  rule; this skill keeps only when the checks run.
- **Which API surface to spend** stays in the
  [`github-api-budget`](../../rules/github-api-budget.md) rule.
- **Issue and PR *style*** stays in the [`writing-issues`](../writing-issues/SKILL.md) and
  [`writing-pull-requests`](../writing-pull-requests/SKILL.md) skills.
