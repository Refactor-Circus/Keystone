# Fix CI failure on setup-php 2.40.0 Dependabot bump — Shaping Notes

## Scope

Ticket #6. Dependabot PR #2 (shivammathur/setup-php 2.37.2 -> 2.40.0) fails only the `P8.5 - L13.* - prefer-stable - ubuntu-latest` job; the other three matrix lanes pass. Goal: PR #2 goes green. Out of scope: merging PR #2, other matrix lanes.

## Decisions

- D-001: ticket created as drafted.

## Context

The failing log (run 38594628917) shows the failure is not caused by setup-php. The `composer update` step cannot resolve dependencies:

- `pestphp/pest v5.3.1 conflicts with phpunit/phpunit 13.4.5`
- `refactor-circus/keystone conflicts with brianium/paratest v7.27.1 - v7.27.4`

The second line is the `conflict: brianium/paratest >=7.26.1` entry in `composer.json`, the same problem as ticket #7 ("Drop paratest conflict, require pest ^5.3.4"). A newer prefer-stable resolution of dependencies hits it regardless of the setup-php version. Changing the workflow for the bump would not fix it.

## Standards

None apply: the work is CI/composer maintenance and touches no package code.
