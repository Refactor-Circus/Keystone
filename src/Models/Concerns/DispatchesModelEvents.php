<?php

declare(strict_types=1);

namespace RefactorCircus\Keystone\Models\Concerns;

use Illuminate\Support\Str;
use Illuminate\Support\Testing\Fakes\EventFake;
use RefactorCircus\Keystone\Contracts\ModelLifecycleEvent;

/**
 * Dispatch a class-based event for every Eloquent lifecycle hook.
 *
 * Models live in `{Package}\Domains\{Domain}\Models`, and each hook maps by
 * convention to `{Package}\Domains\{Domain}\Events\{Entity}{Hook}Event`, where
 * the entity is the model's name less its `Model` suffix —
 * `DashboardWidgetModel` fires `DashboardWidgetCreatingEvent`. Hooks without
 * such a class (soft-delete hooks on models that cannot be trashed) are skipped.
 *
 * A subclass of a package model, such as an application's `TeamDashboard
 * extends DashboardModel`, fires the package model's events, so listeners see
 * every dashboard whatever class it hydrates as. Entries a model declares on
 * `$dispatchesEvents` itself win over the derived ones. The `retrieved` event,
 * which fires for every model loaded, is only dispatched while something
 * listens for it.
 */
trait DispatchesModelEvents
{
    /**
     * Every Eloquent hook, in the order Eloquent documents them.
     *
     * @var list<string>
     */
    private static array $modelEventHooks = [
        'retrieved', 'creating', 'created', 'updating', 'updated',
        'saving', 'saved', 'deleting', 'deleted', 'restoring', 'restored',
        'trashed', 'forceDeleting', 'forceDeleted', 'replicating',
    ];

    /**
     * @var array<class-string, array<string, class-string>>
     */
    private static array $derivedModelEvents = [];

    protected function initializeDispatchesModelEvents(): void
    {
        // At construction $dispatchesEvents holds only the class's declared
        // entries, so the merged map is the same for every instance.
        $this->dispatchesEvents = self::$derivedModelEvents[static::class]
            ??= array_merge(self::deriveModelEvents(), $this->dispatchesEvents);
    }

    /**
     * `retrieved` fires for every model hydrated, so its event is only built
     * and dispatched while something listens: to the event itself, or to
     * every lifecycle event through ModelLifecycleEvent. Always under
     * `Event::fake()`, so tests can assert it. A wildcard listener (`*`,
     * as debug tooling registers) counts as listening, so with one present
     * the event is built for every model hydrated - that listener asked for
     * every event.
     *
     * @param  string  $event
     * @param  'until'|'dispatch'  $method
     * @return mixed
     */
    protected function fireCustomModelEvent($event, $method)
    {
        if ($event === 'retrieved' && isset($this->dispatchesEvents['retrieved']) && static::$dispatcher !== null
            && ! static::$dispatcher instanceof EventFake
            && ! static::$dispatcher->hasListeners($this->dispatchesEvents['retrieved'])
            && ! static::$dispatcher->hasListeners(ModelLifecycleEvent::class)) {
            return null;
        }

        return parent::fireCustomModelEvent($event, $method);
    }

    /**
     * @return array<string, class-string>
     */
    private static function deriveModelEvents(): array
    {
        $model = self::packageModel();
        $namespace = Str::of($model)->beforeLast('\\')->replaceEnd('\\Models', '');
        $prefix = Str::replaceEnd('Model', '', class_basename($model));
        $map = [];

        foreach (self::$modelEventHooks as $hook) {
            $event = $namespace.'\\Events\\'.$prefix.Str::ucfirst($hook).'Event';

            if (class_exists($event)) {
                $map[$hook] = $event;
            }
        }

        return $map;
    }

    /**
     * The package model this class is, or extends: the nearest class in its
     * ancestry that lives in a `\Domains\{Domain}\Models\` namespace.
     */
    private static function packageModel(): string
    {
        foreach ([static::class, ...array_values(class_parents(static::class) ?: [])] as $class) {
            if (preg_match('/\\\\Domains\\\\[^\\\\]+\\\\Models\\\\[^\\\\]+$/', $class) === 1) {
                return $class;
            }
        }

        return static::class;
    }
}
