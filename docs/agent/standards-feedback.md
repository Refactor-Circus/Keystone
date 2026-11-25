# Standards Feedback

Every time work has to break a standard, add a row. `/ring-master-discover-standards` reads this log, so changes to the shared standards are based on evidence.

| Date | Spec | Standard | Deviation | Why |
| --- | --- | --- | --- | --- |
| 2026-11-23 | 2026-11-23-0943-drop-the-atrium-plugin-hook | architecture/service-providers | The standard still lists `registerAtriumPlugin()` among the `PackageServiceProvider` helpers; Keystone removed it (and the `{key}.ui.enabled` convention) in v0.2.0. | Atrium registers package UIs itself, so Keystone no longer names it. The shared standard lives in refactor-circus/standards and is not edited from here (D-004); it needs a Standards spec to drop the helper. |
| 2026-11-23 | 2026-11-23-0943-drop-the-atrium-plugin-hook | errors/exceptions | `Authorization\Abilities::define()` throws a bare `InvalidArgumentException` for an empty, whitespace or duplicate ability name instead of a package exception subclass. | Spec criterion 20 names `InvalidArgumentException`. It is a programmer error raised from a provider's `boot()`, never caller input, and Keystone already throws SPL `LogicException` for such errors (`Data`, `JsonResource`). A `PackageException` subclass would be a `RuntimeException` rendered as HTTP 409, which does not fit. |
