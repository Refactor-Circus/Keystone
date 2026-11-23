# Drop the Atrium plugin hook — Shaping Notes

## Scope

Keystone stops naming Atrium. Remove `PackageServiceProvider::registerAtriumPlugin()` (it registers a plugin only when `RefactorCircus\Atrium\Atrium` exists and `<pkg>.ui.enabled` is true), the `<pkg>.ui.enabled` convention, and any other Atrium reference in `src/`, docs and the Keystone standards it ships. Keep `loadHistoryRoutes()` (`GET {prefix}/history`): it is package API, and Atrium's Keen integration uses it. Release as a minor or major as the breaking-change rules require.

## Decisions

- **Atrium hosts the suite's front ends** (user, 2026-11-23). Packages keep only their API and MCP. Atrium registers each package's UI itself when the package is installed, so Keystone needs no plugin hook.
- **History routes stay.**
- **The suite rule is unchanged for Keystone:** it names no other suite package.

## Context

- The seven package specs (Cortex, Impex, Keen, PennantPlus, Polycart, Roster, Showroom, all `2026-11-23-0943-*-v2-api-and-mcp-only`) wait for this one. Atrium's v2 spec waits for those.
- Foundry and Guardian still call `registerAtriumPlugin()`. They are out of this round and stay on the current Keystone tag until they migrate. The CHANGELOG says so.
- **Open for write-spec:** whether this is v0.2.0 or v1.0.0 under the suite's versioning.

## Standards

- `architecture/dependencies`: Keystone names no suite package.
- `architecture/public-api`: removing a public method is a breaking change.
- `architecture/service-providers`: the PackageServiceProvider surface.
- `testing/arch-tests`: add an arch rule that `src/` does not reference `RefactorCircus\Atrium`.
