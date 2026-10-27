# Drop brianium/paratest conflict, require pest ^5.3.4

Ticket: [#7](https://github.com/Refactor-Circus/Keystone/issues/7)

## Goal
Make `composer update` resolve again for Keystone and every package that requires it, by removing the stale paratest conflict and requiring a Pest release that no longer needs it.

## Acceptance criteria
1. `composer.json` has no `conflict` entry for `brianium/paratest`, and removing the block leaves no dangling comma or empty `conflict` key. `composer validate` passes.
2. `composer.json` requires `pestphp/pest` at `^5.3.4`.
3. Locally, `composer update` completes without a resolution error, and `composer show brianium/paratest` reports a version >= 7.27.4. Record the output in the PR body (composer.lock is gitignored, so it is not in the diff).
4. The PR's CI run resolves with a fresh `composer update` (no local lock) and passes on every matrix entry, including prefer-lowest if the workflow uses it (expected to install pest 5.3.4 and paratest 7.27.4).
5. `composer test` passes. It runs `test:unit` (`pest --parallel`), so a clean parallel run with paratest >= 7.27.4 is the evidence the `WrapperWorker::__construct` crash is gone. A failure unrelated to resolution (for example `test:types` with `--min=100` after the Pest bump) is reported, not fixed in this change.
6. `git diff --stat` shows only `composer.json`, and the diff is limited to the removed conflict block and the one pest line. No other constraint, source or test file changes.

## Design
Edit `composer.json` only: delete the `conflict` block and change `"pestphp/pest": "^5.3.1"` to `"^5.3.4"`. `composer.lock` is untracked (gitignored), so run `composer update` locally to refresh it and expect no lock diff in the PR. Pest 5.3.4 requires `paratest ^7.27.4`, which carries the fix for the `WrapperWorker::__construct` crash the conflict guarded against. Run `grep -rn "5.3.1"` over docs and workflows as a check that nothing else pins the old floor.

## Out of scope
Keen's identical conflict (its own job). Merging Dependabot PR #2 (ticket #6). Docs, CHANGELOG and workflow files, unless they pin Pest 5.3.1.

## Risks and open questions
Risk: removing the conflict drops the only guard against the `WrapperWorker::__construct` crash. Keystone's require-dev floor does not propagate to dependents (Atrium, Cortex, Keen and others), so a dependent that still allows pest 5.3.1 with paratest 7.26.1 to 7.27.3 can resolve into the crash. Each dependent must raise its own `pestphp/pest` floor to `^5.3.4` (follow-up: ticket #6 or per-package jobs). No code change in Keystone.

Open questions: none. If `composer update` still fails after the change, stop and report the resolution output rather than widening constraints.
