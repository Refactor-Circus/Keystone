<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use RefactorCircus\Keystone\Support\Surface;

function surfaceForRoute(string $name): string
{
    $request = Request::create('/');
    $request->setRouteResolver(fn (): Route => (new Route('GET', '/', fn (): null => null))->name($name));
    app()->instance('request', $request);

    return app(Surface::class)->current();
}

it('names the surface from the route', function (): void {
    expect(surfaceForRoute('atrium.dashboard'))->toBe('atrium')
        ->and(surfaceForRoute('acme.widgets.store'))->toBe('http');
});

it('lets a package name a more specific route prefix', function (): void {
    app(Surface::class)->route('acme.scim.', 'scim');

    expect(surfaceForRoute('acme.scim.users'))->toBe('scim')
        ->and(surfaceForRoute('acme.widgets.store'))->toBe('http');
});

it('picks up a prefix named after the first lookup', function (): void {
    expect(surfaceForRoute('acme.scim.users'))->toBe('http');

    app(Surface::class)->route('acme.scim.', 'scim');

    expect(surfaceForRoute('acme.scim.users'))->toBe('scim');
});

it('falls back to cli in the console', function (): void {
    expect(app(Surface::class)->current())->toBe('cli');
});

it('nests forced surfaces', function (): void {
    $surface = app(Surface::class);

    $surface->enter('cortex');

    expect($surface->using('mcp', fn (): string => $surface->current()))->toBe('mcp')
        ->and($surface->current())->toBe('cortex');

    $surface->leave();

    expect($surface->current())->toBe('cli');
});
