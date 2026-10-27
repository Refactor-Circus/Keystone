# Tasks: Drop brianium/paratest conflict, require pest ^5.3.4

## Group 1: Edit composer.json and verify resolution
depends on: none
files: composer.json
criteria: 1, 2, 3, 4, 5, 6

- [x] 1.1 Write the check first: confirm `composer.json` still has the `conflict` block for `brianium/paratest` and `pestphp/pest` at `^5.3.1` (the failing starting state), and `grep -rn "5.3.1"` over docs and workflows for other pins of the old floor
- [x] 1.2 Delete the `conflict` block from `composer.json` (no dangling comma, no empty `conflict` key) and change `"pestphp/pest": "^5.3.1"` to `"^5.3.4"`
- [x] 1.3 Run `composer validate`, then `composer update`; record `composer show brianium/paratest` (expect >= 7.27.4) for the PR body
- [x] 1.4 Check `git diff --stat` shows only `composer.json`, limited to the removed block and the one pest line
- [x] 1.5 Run `composer test`; all green (a failure unrelated to resolution, such as `test:types` after the Pest bump, is reported, not fixed here)

Criterion 4 (CI fresh-resolve on every matrix entry) is proven by the PR's CI run after the PR opens.
