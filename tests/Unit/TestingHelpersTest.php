<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use PHPUnit\Framework\AssertionFailedError;
use RefactorCircus\Keystone\Audit\Contracts\AuditTrail;
use RefactorCircus\Keystone\Audit\Data\AuditEntry;
use RefactorCircus\Keystone\Testing\Arch;
use RefactorCircus\Keystone\Testing\McpTools;
use RefactorCircus\Keystone\Testing\Parity;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Domains\Widget\Models\WidgetModel;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Mcp\AcmeServer;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Mcp\Tools\ListAcmeHistoryTool;
use RefactorCircus\Keystone\Tests\Fixtures\Acme\Mcp\Tools\ProbeTool;
use RefactorCircus\Keystone\Tests\Fixtures\FakeAuditTrail;

/**
 * A scratch directory of PHP files, by name => contents.
 *
 * @param  array<string, string>  $files
 */
function scratch(array $files): string
{
    $dir = sys_get_temp_dir().'/keystone-'.uniqid();

    foreach ($files as $name => $contents) {
        @mkdir(dirname($dir.'/'.$name), recursive: true);
        file_put_contents($dir.'/'.$name, $contents);
    }

    return $dir;
}

it('calls a tool and checks its answer against its output schema', function (): void {
    app()->instance(AuditTrail::class, new FakeAuditTrail([
        new AuditEntry(id: 1, source: 'acme', action: 'widget.created', surface: 'http', createdAt: Carbon::now()->toImmutable(), subjectType: WidgetModel::class, subjectId: '1'),
    ]));

    McpTools::call(AcmeServer::class, ListAcmeHistoryTool::class)->assertOk()->assertSee('widget.created');
});

it('fails a tool that answers with structured content but declares no output schema', function (): void {
    expect(fn () => McpTools::call(AcmeServer::class, ProbeTool::class))->toThrow(AssertionFailedError::class, 'declares no outputSchema()');

    McpTools::call(AcmeServer::class, ProbeTool::class, checkSchema: false)->assertOk();
});

it('lists the actions a surface cannot reach, less the deliberate exceptions', function (): void {
    $dir = scratch([
        'Actions/CreateWidgetAction.php' => '<?php',
        'Actions/ShipWidgetAction.php' => '<?php',
        'Actions/LoginAction.php' => '<?php',
        'Http/WidgetRequest.php' => '<?php // CreateWidgetAction',
        'Mcp/WidgetTool.php' => '<?php // CreateWidgetAction ShipWidgetAction',
    ]);

    expect(Parity::gaps($dir.'/Actions/*.php', ['http' => $dir.'/Http/*.php', 'mcp' => $dir.'/Mcp/*.php'], ['LoginAction' => ['http', 'mcp']]))
        ->toBe(['http' => ['ShipWidgetAction']]);
});

it('lists the domain classes and the actions missing their events', function (): void {
    $domains = dirname(__DIR__).'/Fixtures/Acme/Domains';
    $dir = scratch(['Actions/SilentAction.php' => "<?php\n\nfinal class SilentAction {}\n"]);

    expect(Arch::domainNamespaces('RefactorCircus\\Keystone\\Tests\\Fixtures\\Acme', $domains, 'Models'))->toBe(['RefactorCircus\\Keystone\\Tests\\Fixtures\\Acme\\Domains\\Widget\\Models'])
        ->and(Arch::domainClasses('RefactorCircus\\Keystone\\Tests\\Fixtures\\Acme', $domains, 'Models'))->toBe([WidgetModel::class])
        ->and(Arch::actionEventGaps($dir.'/Actions/*.php'))->toBe(['SilentAction' => ['starting', 'finished']]);
});

it('lists the models missing a lifecycle event', function (): void {
    expect(Arch::modelEventGaps([WidgetModel::class]))->toBe([
        'WidgetModel' => ['creating', 'updating', 'updated', 'saving', 'saved', 'deleting', 'deleted', 'replicating'],
    ]);
});

it('lists the actions that write outside a transaction', function (): void {
    $dir = scratch([
        'Actions/CreateWidgetAction.php' => "<?php\n\nWidgetModel::query()->create([]);\n",
        'Actions/UpdateWidgetAction.php' => "<?php\n\nDB::transaction(fn () => \$widget->update([]));\n",
        'Actions/ShowWidgetAction.php' => "<?php\n\nreturn \$widget->load('parts');\n",
    ]);

    expect(Arch::writesOutsideTransactions($dir.'/Actions/*.php'))->toBe(['CreateWidgetAction'])
        ->and(Arch::writesOutsideTransactions($dir.'/Actions/*.php', ['load']))->toBe(['CreateWidgetAction', 'ShowWidgetAction']);
});

it('lists the models without a configured policy', function (): void {
    expect(Arch::modelsWithoutPolicies([WidgetModel::class], []))->toBe(['WidgetModel'])
        ->and(Arch::modelsWithoutPolicies([WidgetModel::class], [WidgetModel::class => 'Missing\\Policy']))->toBe(['WidgetModel'])
        ->and(Arch::modelsWithoutPolicies([WidgetModel::class], [WidgetModel::class => FakeAuditTrail::class]))->toBe([]);
});

it('lists the files that name a table as a literal', function (): void {
    $dir = scratch([
        'migrations/create.php' => "<?php\n\nSchema::create('acme_widgets', fn () => null);\n",
        'migrations/parts.php' => "<?php\n\nSchema::create(Tables::name('parts'), fn () => null);\n",
    ]);

    expect(Arch::literalTableNames($dir.'/migrations/*.php', ['acme_widgets', 'acme_parts']))->toBe(['create.php' => ['acme_widgets']]);
});
