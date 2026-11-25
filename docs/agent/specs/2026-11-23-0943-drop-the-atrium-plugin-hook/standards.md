# Standards for Drop the Atrium plugin hook

| Standard | File | Source | Why it applies |
| --- | --- | --- | --- |
| architecture/dependencies | vendor/refactor-circus/standards/standards/architecture/dependencies.md | refactor-circus/standards@dev-main | Keystone is the base package and names no other suite package; the hook was an Atrium reference behind `class_exists()` |
| architecture/public-api | vendor/refactor-circus/standards/standards/architecture/public-api.md | refactor-circus/standards@dev-main | `registerAtriumPlugin()` is a documented extension point on an open base class; removing it is a breaking change |
| architecture/service-providers | vendor/refactor-circus/standards/standards/architecture/service-providers.md | refactor-circus/standards@dev-main | The `PackageServiceProvider` helper surface changes (the standard still lists `registerAtriumPlugin()`; see spec Risks) |
| architecture/configuration | vendor/refactor-circus/standards/standards/architecture/configuration.md | refactor-circus/standards@dev-main | The shared `{package}` config shape loses its `ui.enabled` key |
| testing/arch-tests | vendor/refactor-circus/standards/standards/testing/arch-tests.md | refactor-circus/standards@dev-main | New arch rule: `RefactorCircus\Keystone` does not use `RefactorCircus\Atrium` |
