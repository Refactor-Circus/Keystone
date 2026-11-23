VERDICT: PASS

Gate: composer test passed (phpstan, pint, pest 96/96), lint:check passed, ringside check passed.

Criteria:
1. tests/Feature/PackageTest.php method_exists check
2. tests/ArchTest.php src scan (ui.enabled, configKey('ui); src grep clean
3. tests/Feature/HistoryTest.php unchanged, passes
4. tests/Unit/SurfaceTest.php (atrium.dashboard -> cli)
5. tests/Unit/SurfaceTest.php (route('atrium.', 'atrium'))
6. tests/ArchTest.php namespace rule and src string scan
7. tests/Feature/DocumentationTest.php README checks
8. tests/Feature/DocumentationTest.php CHANGELOG checks
9. tests/Feature/DocumentationTest.php branch alias; composer.json 0.2.x-dev
10. composer test (pest, phpstan) and lint:check pass
