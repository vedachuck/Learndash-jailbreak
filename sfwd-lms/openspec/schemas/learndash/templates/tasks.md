<!--
Stacked layout. For a ticket the user agreed to ship as one PR, drop the trunk,
the sub PR sections and the PR 1 re-review, and keep: cut the branch, the
ticket's work, a CodeRabbit review, merge the source branch's latest tip,
changelog, archive, then open the PR.
-->

<!-- One sentence: how many sub PRs, and that the trunk gets no PR from them. -->

| | Branch | Base | Source files, tests excluded |
|---|---|---|---|
| trunk | `base/<TICKET>/<description>` | `<source branch the user named>` | none of its own |
| 1 | `<TICKET>/01-<topic>-openspec` | the trunk | none |
| 2 | `<TICKET>/02-<topic>` | PR 1's branch | <!-- count --> |
| up | the trunk | `<source branch the user named>` | the sub PRs' work |

<!--
Why the trunk is cut from that branch, if it is not the newest release/* branch.
Name any open PR it depends on and the git merge-base check that shows whether
it has merged.
-->

<!--
Why the work splits where it does: the decision each sub PR asks a reviewer to
accept on its own.
-->

No sub PR exceeds 10 files with tests excluded, and no sub PR that adds or changes logic exceeds 4 source files.

The OpenSpec artifacts are edited on PR 1 only. Later sub PRs tick checkboxes in `tasks.md` and change no other artifact text. A later PR that makes the artifact text wrong gets the fix on PR 1's branch, carried up through the branches above it.

<!-- What must not be touched, and what to re-read if a task seems to need it. -->

<!--
Changelog: the `bun run changelog` type and entry. When none is needed, say why,
and that the trunk's PR gets the `No Changelog Needed` label when it is marked
ready for review. The reason stays here, never in a PR Description.
-->

Sub PRs open as drafts, titled `<Shared prefix> [X/Y]: <rest> | <TICKET>`. `Y` is the count of sub PRs, so adding one means retitling the PRs already open. Bodies follow `.github/pull_request_template.md` with every section kept and the ticket linked. The Description is a few short bullets a human reviewer needs, not a restatement of the design. No labels while a PR is a draft.

See AGENTS.md for how to deprecate, how to treat the phpstan baseline, and TBD versions.

## 1. The trunk

- [ ] 1.1 Cut `base/<TICKET>/<description>` from `<source branch>` and push it with `git push -u origin HEAD`. It carries no commits of its own. Verify `git log -1` is the source branch's tip.

## 2. PR 1: the OpenSpec change

- [ ] 2.1 Cut `<TICKET>/01-<topic>-openspec` from the trunk and push it with `git push -u origin HEAD`. Verify `git log -1` is the trunk's tip.
- [ ] 2.2 Commit the change artifacts and the `.openspec.yaml` the scaffold created. Verify `openspec validate <change> --strict` passes and `git show --stat` lists nothing outside `openspec/changes/<change>/`.
- [ ] 2.3 Review the branch with `coderabbit review --committed --base <trunk>` and resolve what it raises, one commit per finding. Verify a re-run reports nothing outstanding.
- [ ] 2.4 Open the draft PR against the trunk, titled `<Shared prefix> [1/Y]: the OpenSpec change | <TICKET>`. Verify the PR's base is the trunk.

## 3. PR 2: <!-- topic -->

- [ ] 3.1 Cut `<TICKET>/02-<topic>` from PR 1's branch and push it with `git push -u origin HEAD`. Verify `git log -1` is PR 1's tip.
<!--
Implementation and test tasks for this PR, as many as its work needs, in the
order they are done. Each implementation task names its files and how to verify
it. Each test task names its test files and the command that runs only them.
Tests may follow the step they cover or be grouped after several steps.
-->
- [ ] 3.2 <!-- Implementation step -->
- [ ] 3.3 <!-- Test step -->
- [ ] 3.N Review the branch with `coderabbit review --committed --base <PR 1's branch>`, one commit per finding. Verify `composer check` (PHP) or the lint and test commands for the changed files pass, then open the draft PR against PR 1's branch, titled `<Shared prefix> [2/Y]: <rest> | <TICKET>`.

<!-- Repeat the section above for each further sub PR. -->

## 4. Re-reviewing PR 1

- [ ] 4.1 After the last sub PR is finished, re-review PR 1 only if work on a later sub PR required going back to PR 1's branch and changing its artifact text after PR 1's first review. Ticked checkboxes and result notes on tasks do not count as changes. If that happened, re-run `coderabbit review --committed --base <trunk>` on PR 1's branch, resolve what it raises there one commit per finding, and carry the fixes up through the branches above. If nothing changed, tick this task with that note and do not re-run the review.

## 5. Taking the trunk up

- [ ] 5.1 Once every sub PR is approved, merge them into the trunk from the bottom up: merge PR 1 into the trunk, then each later sub PR in order. Each sub PR targets the branch below it, so before merging one, confirm GitHub retargeted it to the trunk after the PR below it merged and its branch was deleted, and retarget it with `gh pr edit <number> --base <trunk>` if not. Verify every sub PR shows as merged into the trunk and `git log origin/<trunk>` contains each one's commits.
- [ ] 5.2 Check that `<source branch>` still exists and is still the right target, then merge its latest `origin/` tip into the trunk and push. Verify `git merge-base --is-ancestor origin/<source branch> HEAD` succeeds and the tests for the changed files still pass.
- [ ] 5.3 Add the changelog entry on the trunk with `bun run changelog`, or, when the preamble says none is needed, add none. Verify the entry file exists under `changelog/`, or that `git diff --stat origin/<source branch>...HEAD` adds no file under `changelog/`.
- [ ] 5.4 Archive the change on the trunk with `openspec archive <change> --yes` and push, so reviewers of the trunk's PR see the archived specs. Archive only when the trunk already contains the latest `origin/<source branch>`; if that branch has moved since 5.2, merge it again first. Verify `git merge-base --is-ancestor origin/<source branch> HEAD` succeeds and `openspec validate --specs` passes.
- [ ] 5.5 Open the trunk's draft PR against `<source branch>`, titled `<Ticket description> | <TICKET>`, with the body from the template and a Description that only lists the sub PRs. Verify the PR's diff is the sub PRs' work, the changelog entry if there is one, and the archive, and nothing else.
