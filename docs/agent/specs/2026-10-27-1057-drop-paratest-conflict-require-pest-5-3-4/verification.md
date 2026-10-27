# Verification: Drop brianium/paratest conflict, require pest ^5.3.4

Date: 2026-10-27 · Result: PASS

| Check | Result | Detail |
| --- | --- | --- |
| Criterion 1: no `conflict` block, valid JSON | pass | `composer.json` diff removes the block cleanly; `composer validate` reports the file valid (its only complaints were the stale lock, refreshed by `composer update`) |
| Criterion 2: pest `^5.3.4` | pass | `composer.json` require-dev; lock resolved `pestphp/pest v5.3.4` |
| Criterion 3: `composer update` resolves, paratest >= 7.27.4 | pass | `composer update` completed; `composer show brianium/paratest` reports `v7.27.4` |
| Criterion 4: CI fresh resolve | pending | Proven by the PR's CI run once the PR opens |
| Criterion 5: `composer test` passes | pass | phpstan 0 errors, pint passed, type coverage 100%, pest 82/82 in parallel with paratest 7.27.4 |
| Criterion 6: diff limited to `composer.json` | pass | `git diff --stat`: `composer.json` only, 1 insertion, 4 deletions (conflict block and the pest line); no other pins of 5.3.1 outside spec folders |
| Tasks | pass | every box ticked in `tasks.md` |
| Full gate | pass | `composer test` exit 0 |
| Standards drift | pass | `vendor/bin/ringside check` passed |

## Failures
None.

## Notes for the PR body
`composer show brianium/paratest` reports `v7.27.4`. `composer.lock` is gitignored, so it is not in the diff.
