# Drop brianium/paratest conflict, require pest ^5.3.4 — Shaping Notes

## Scope

Ticket #7. Remove the `conflict: brianium/paratest >=7.26.1` block from `composer.json` and raise `pestphp/pest` to `^5.3.4`. Confirm `composer update`, `composer test` and `pest --parallel` pass, then open a PR. Out of scope: Keen's identical conflict (own job).

## Decisions

- D-001: ticket created as drafted.

## Context

The conflict was added for a Pest 5.3.1 crash in `WrapperWorker::__construct`. Pest 5.3.4 requires `paratest ^7.27.4`, so the conflict makes every dependent's `composer update` fail. CI evidence: run 38594628917 (PR #2) fails resolution with `conflicts with brianium/paratest v7.27.1 - v7.27.4` and `pestphp/pest v5.3.1 conflicts with phpunit/phpunit 13.4.5`. Raising the Pest floor to 5.3.4 fixes both. Merging this unblocks ticket #6 (Dependabot PR #2).

## Standards

None apply: the work is composer maintenance and touches no package code.
