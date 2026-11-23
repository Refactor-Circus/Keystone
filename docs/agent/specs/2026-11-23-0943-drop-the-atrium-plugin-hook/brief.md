Risk: high

## What changed
- Source: `PackageServiceProvider::registerAtriumPlugin()` is deleted and its class docblock no longer lists `ui{enabled}`. `Surface` drops the built-in `atrium.` prefix, so `route()` is how a host names that surface. Doc comments in `Tables`, `Arch`, `PackageRegistry` and `AuditTrail` use neutral examples.
- Release metadata: `composer.json` branch alias moves to `0.2.x-dev`. CHANGELOG `[Unreleased]` gets a `### Breaking` section for v0.2.0. README drops Atrium, the plugin call and the `ui` config key.
- Tests: new arch rule and `src/` string scan, a `method_exists` check, Surface tests, and a `DocumentationTest` covering the README, CHANGELOG and branch alias.
- Standards: one drift row in `docs/agent/standards-feedback.md`.

## Why
Keystone is the headless base and must not name Atrium; Atrium now registers each package's UI itself (spec goal). Decisions: D-001 this is v0.2.0, not v1.0.0, and the alias bump keeps `^0.1` dependents off `main`. D-002 the built-in `atrium` surface goes too. D-003 doc examples and the README are neutralised. D-004 the shared standard is not edited, only recorded. D-005 no tagging in this job.

## Look at
- `src/Support/PackageServiceProvider.php:103`: removal of a public, protected-API helper; confirm nothing else in `src/` still uses it and `loadHistoryRoutes()` is untouched.
- `src/Support/Surface.php:155`: removal of the default `atrium.` prefix, which changes audit surface labels for Atrium routes unless the host calls `route()`.
- `composer.json:108`: branch alias `0.2.x-dev`; confirm that this is the intended resolution for `^0.1` dependents.
- `tests/ArchTest.php:17`: the case-insensitive `atrium` scan over `src/` and the `ui.enabled` and `configKey('ui` checks.
- `CHANGELOG.md:5`: the Breaking wording against the spec's criterion 8.

## Tested
Gate round 1: PASS (`composer test`, phpstan, pint, pest 96/96, `lint:check`).
- AC1: `tests/Feature/PackageTest.php` (`method_exists`).
- AC2 and AC6: `tests/ArchTest.php` (namespace rule, `src/` string scan, `ui.enabled`).
- AC3: existing `tests/Feature/HistoryTest.php`, unmodified.
- AC4 and AC5: `tests/Unit/SurfaceTest.php`.
- AC7 to AC9: `tests/Feature/DocumentationTest.php`.
- AC10: the full gate.

## Risks
- Breaking for every caller of `registerAtriumPlugin()` (Cortex, Impex, Keen, Polycart, Showroom, Foundry, Guardian) once they move to v0.2.0; mitigated by the `^0.1` constraints and the alias bump. No package is broken by this diff itself.
- Audit surface values for `atrium.*` routes change to `http` or `code` unless Atrium registers `Surface::route('atrium.', 'atrium')`.
- `architecture/service-providers` in refactor-circus/standards still names the removed helper; it needs a follow-up Standards spec.
- Nits: 1 left open; the gate report lists no must-fix findings.
