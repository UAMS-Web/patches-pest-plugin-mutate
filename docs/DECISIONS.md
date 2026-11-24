# Decision log

The canonical record of why the project is the way it is: the reasoning and the rejected alternatives behind decisions that have closed. It guards against silently reopening a settled decision.

**Consult it before acting.** Before proposing a change, reopening a question or picking an approach, check here first. The path under consideration may already be a decided-and-recorded one, or an explicitly rejected one. If it is, follow the record, or make the case to revisit it.

**When a decision closes, record the verdict in both places.** Capture the what, the why and the rejected alternatives here (the cross-ticket index) and in the GitHub issue's `## Decision` section (the per-ticket record), keeping the two consistent. Put the full reasoning in the relevant `docs/` companion when the decision touches a documented area. The issue tracker stays the system of record for a single ticket's status.

## How to add an entry

Entries run oldest first, one per closed decision, so a new entry is appended to the end of the file. Keep it terse; the companion or the issue carries the depth.

Every entry heading is `## YYYY-MM-DD`, a space, and a short title. Leave exactly one blank line above each heading. The check (`decision-log` skill) fails on a heading dated earlier than the one before it, and on a heading that sits directly under the previous line.

```markdown
## YYYY-MM-DD <short decision title> (<#issue or area>)

**Decision.** <what was decided, in a sentence or two>

**Why.** <the reasoning that settled it>

**Rejected.** <the alternatives considered, and why each lost>

**Companion.** <link to the docs/ companion or the GitHub issue holding the full reasoning>
```

## 2026-11-24 Score a child that dies before any test starts as untested (#124)

**Decision.** In `pest-plugin-mutate-no-test-check.patch`, a mutant child that ran no test and matches none of the accepted crash forms is scored untested and the run continues, with its exit code and the start of its output surfaced. It is no longer refused with a `RuntimeException` that ends the run. The accepted forms keep their results.

**Why.** The refusal ended a run on every load-time death that was not on the list (a dataset emptied by the mutation, a throw or `exit` at test-file load), where stock scores the same child killed; the list had grown seven times for that reason (#27, #34, #40, #41, #45, #58, #60). Untested keeps the run going, makes the problem show in the score, matches pestphp/pest#2018 section 4 so retiring the patch later changes nothing for consumers, and cannot reopen the original defect of reading as success when nothing ran. The patch is temporary, so the smaller change wins.

**Rejected.** Keep the abort and correct the documents: the only measured option, but it keeps adding accepted forms and ends runs. Run the child again without the mutation and abort only when that also fails: it separates a broken launch from a broken mutant, but it is unmeasured, adds a restart path that overlaps the retry patch, and diverges from the upstream proposal. It stays available if broken launches prove hard to read. Score such a child killed, as stock does: it lets a broken launch read as 100.00%.

**Companion.** UAMS-Web/patches-pest-plugin-mutate#124; follow-ups #128, #129 and #130.
