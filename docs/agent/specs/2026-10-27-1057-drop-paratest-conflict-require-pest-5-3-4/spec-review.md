# Spec review

## Round 1

Changes to spec.md (all minor findings; open questions untouched):
- AC1: added dangling-comma/empty-key check and `composer validate`. Makes it checkable.
- AC3: now names the local command (`composer update`, `composer show brianium/paratest`) and says to record output in the PR, since composer.lock is gitignored and absent from the diff.
- New AC4: CI must resolve with a fresh lock-free `composer update` on every matrix entry (incl. prefer-lowest), since consumers get no lock.
- Old AC4/AC5 folded into AC5: `composer test` runs `pest --parallel`, so a clean parallel run is the evidence the WrapperWorker crash is gone; unrelated failures (e.g. type coverage) are reported, not fixed.
- AC6: method stated (`git diff --stat`, only composer.json, only the two edits).
- Design: removed the tracked-lock hedge; states composer.lock is gitignored with no lock diff expected; added a grep for `5.3.1`.
- Out of scope: docs, CHANGELOG and workflows unless they pin Pest 5.3.1.
- Risks: added that dependents must raise their own pest floor to ^5.3.4 (ticket #6 or per-package jobs); no Keystone code change.
