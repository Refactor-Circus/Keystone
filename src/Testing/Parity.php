<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Testing;

/**
 * Actions a surface cannot reach. Every use case should be offered on each
 * surface a package has - its HTTP API, MCP tools, a UI - and an Action counts
 * as reachable when its class name appears in that surface's files.
 *
 * Pass globs of the Action files and of each surface's files (requests,
 * tools, controllers), plus the Actions deliberately left off a surface:
 *
 *     expect(Parity::gaps($actionFiles, ['http' => $requestFiles, 'mcp' => $toolRequestFiles], [
 *         'SsoLoginAction' => ['http', 'mcp'],
 *     ]))->toBe([]);
 */
final class Parity
{
    /**
     * Actions missing from each surface, keyed by surface.
     *
     * @param  string|array<int, string>  $actions  glob(s) of the Action files
     * @param  array<string, string|array<int, string>>  $surfaces  surface => glob(s) of its files
     * @param  array<string, array<int, string>>  $exceptions  Action => surfaces it is deliberately absent from
     * @return array<string, array<int, string>>
     */
    public static function gaps(string|array $actions, array $surfaces, array $exceptions = []): array
    {
        $names = array_map(fn (string $path): string => basename($path, '.php'), self::files($actions));
        $gaps = [];

        foreach ($surfaces as $surface => $patterns) {
            $source = implode("\n", array_map(fn (string $path): string => (string) file_get_contents($path), self::files($patterns)));

            $missing = array_values(array_filter(
                $names,
                fn (string $action): bool => ! str_contains($source, $action)
                    && ! in_array($surface, $exceptions[$action] ?? [], true),
            ));

            if ($missing !== []) {
                $gaps[$surface] = $missing;
            }
        }

        return $gaps;
    }

    /**
     * @param  string|array<int, string>  $patterns
     * @return array<int, string>
     */
    private static function files(string|array $patterns): array
    {
        return array_merge(...array_map(fn (string $pattern): array => glob($pattern) ?: [], (array) $patterns));
    }
}
