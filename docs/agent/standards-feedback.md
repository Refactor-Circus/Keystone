# Standards Feedback

Every time work has to break a standard, add a row. `/ring-master-discover-standards` reads this log, so changes to the shared standards are based on evidence.

| Date | Spec | Standard | Deviation | Why |
| --- | --- | --- | --- | --- |
| 2026-11-23 | 2026-11-23-0943-drop-the-atrium-plugin-hook | architecture/service-providers | The standard still lists `registerAtriumPlugin()` among the `PackageServiceProvider` helpers; Keystone removed it (and the `{key}.ui.enabled` convention) in v0.2.0. | Atrium registers package UIs itself, so Keystone no longer names it. The shared standard lives in refactor-circus/standards and is not edited from here (D-004); it needs a Standards spec to drop the helper. |
