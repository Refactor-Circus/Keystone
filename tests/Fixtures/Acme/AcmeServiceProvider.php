<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Tests\Fixtures\Acme;

use RefactorCircus\Keystone\Packages\Package;
use RefactorCircus\Keystone\Support\PackageServiceProvider;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\WidgetServiceProvider;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Mcp\AcmeServer;

/**
 * A package of the suite, as small as the shared runtime allows.
 */
final class AcmeServiceProvider extends PackageServiceProvider
{
    protected function definition(): Package
    {
        return Package::make('acme', __NAMESPACE__)->label('Acme')->server(AcmeServer::class);
    }

    public function register(): void
    {
        $this->registerPackage();

        $this->app->register(WidgetServiceProvider::class);
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerMcpServer();
        $this->registerCortex();
        $this->loadHistoryRoutes();
        $this->registerNotFoundResponses();
    }
}
