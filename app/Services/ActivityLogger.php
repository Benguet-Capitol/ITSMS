<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Concerns\LogsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Writes the audit trail (docs/activity-log.md). One instance per request
 * (registered scoped).
 *
 * For a changing request, App\Http\Middleware\LogActivity calls begin(),
 * watch()es the records the route names, and flush()es once the response
 * is successful: each watched or newly created record whose snapshot
 * changed gets exactly one row, however many times the controller saved
 * it. A failed request writes nothing (discard()).
 *
 * Logins and report downloads aren't changes to a record and are written
 * straight away with record().
 */
class ActivityLogger
{
    private bool $active = false;

    /** @var array<string, array{model: Model, before: ?array, label: string, context: array}> */
    private array $entries = [];

    public function begin(): void
    {
        $this->active = true;
        $this->entries = [];
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /** Remembers the record as it is now, before the controller changes it. */
    public function watch(Model $model): void
    {
        if (! $this->active || ! $this->isLoggable($model) || ! $model->exists) {
            return;
        }

        $key = $this->keyFor($model);

        if (! isset($this->entries[$key])) {
            $this->entries[$key] = [
                'model' => $model,
                'before' => $model->activitySnapshot(),
                'label' => $model->activityLabel(),
                'context' => [],
            ];
        }
    }

    /** A record created during the request: logged as created, whatever happens to it after. */
    public function created(Model $model): void
    {
        if (! $this->active || ! $this->isLoggable($model)) {
            return;
        }

        $this->entries[$this->keyFor($model)] = [
            'model' => $model,
            'before' => null,
            'label' => $model->activityLabel(),
            'context' => [],
        ];
    }

    /** Extra detail for a watched record's row, e.g. who a ticket was transferred from. */
    public function addContext(Model $model, array $context): void
    {
        $this->watch($model);

        $key = $this->keyFor($model);

        if (isset($this->entries[$key])) {
            $this->entries[$key]['context'] = [...$this->entries[$key]['context'], ...$context];
        }
    }

    /** Writes one row per changed record; $action names what the request did (accept, resolve...). */
    public function flush(string $action): void
    {
        $entries = $this->entries;
        $this->discard();

        foreach ($entries as $entry) {
            $model = $entry['model'];
            $current = $this->reload($model);
            $before = $entry['before'];
            $after = $current?->activitySnapshot();

            $wasLive = $before !== null && empty($before['deleted_at']);
            $isDeleted = $current === null || ! empty($after['deleted_at'] ?? null);

            // [action, changes, whether a row needs actual changes]
            [$rowAction, $changes, $needsChanges] = match (true) {
                $before === null && $current === null => [null, null, false],
                $before === null => ['created', $this->diff([], $after), false],
                $wasLive && $isDeleted => ['deleted', null, false],
                ! empty($before['deleted_at']) && ! $isDeleted => ['restored', $this->diff($before, $after), false],
                default => [$action, $this->diff($before, $after), true],
            };

            if ($rowAction === null || ($needsChanges && ! $changes && ! $entry['context'])) {
                continue;
            }

            $this->write(
                action: $rowAction,
                subject: $current ?? $model,
                label: $current?->activityLabel() ?? $entry['label'],
                changes: $changes ?: null,
                context: $entry['context'] ?: null,
            );
        }
    }

    public function discard(): void
    {
        $this->active = false;
        $this->entries = [];
    }

    /** A row that isn't a record change: a login, a report download. */
    public function record(string $action, ?Model $subject = null, ?array $context = null, ?User $actor = null, ?string $label = null): ActivityLog
    {
        return $this->write(
            action: $action,
            subject: $subject,
            label: $label ?? ($subject && $this->isLoggable($subject) ? $subject->activityLabel() : null),
            changes: null,
            context: $context,
            actor: $actor,
        );
    }

    /**
     * field => ['old' => x, 'new' => y], or for lists
     * field => ['added' => [...], 'removed' => [...]].
     */
    public function diff(array $before, array $after): array
    {
        $changes = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            if ($field === 'deleted_at') {
                continue;
            }

            $old = $before[$field] ?? null;
            $new = $after[$field] ?? null;

            if ($this->isNameList($old) && $this->isNameList($new) && (is_array($old) || is_array($new))) {
                $old = array_map('strval', (array) $old);
                $new = array_map('strval', (array) $new);
                $added = array_values(array_diff($new, $old));
                $removed = array_values(array_diff($old, $new));

                if ($added || $removed) {
                    $changes[$field] = ['added' => $added, 'removed' => $removed];
                }

                continue;
            }

            if ($this->comparable($old) === $this->comparable($new)) {
                continue;
            }

            $changes[$field] = in_array($field, $this->secretKeys(), true)
                ? ['old' => null, 'new' => '(changed)']
                : ['old' => $old, 'new' => $new];
        }

        return $changes;
    }

    private function write(string $action, ?Model $subject, ?string $label, ?array $changes, ?array $context, ?User $actor = null): ActivityLog
    {
        $actor ??= Auth::user();
        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return ActivityLog::create([
            'user_id' => $actor?->getKey(),
            'user_name' => $actor ? ($actor->profile?->display_name ?? $actor->username) : null,
            'action' => $action,
            'loggable_type' => $subject?->getMorphClass(),
            'loggable_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'subject_label' => $label === null ? null : Str::limit($label, 250, ''),
            'changes' => $changes,
            'context' => $context,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 500, '') ?: null : null,
        ]);
    }

    private function reload(Model $model): ?Model
    {
        $query = in_array(SoftDeletes::class, class_uses_recursive($model), true)
            ? $model::withTrashed()
            : $model::query();

        return $query->find($model->getKey());
    }

    private function isLoggable(Model $model): bool
    {
        return in_array(LogsActivity::class, class_uses_recursive($model), true);
    }

    private function keyFor(Model $model): string
    {
        return $model::class.'#'.$model->getKey();
    }

    /** A list of plain values (names), shown as added/removed; null counts as an empty list. */
    private function isNameList(mixed $value): bool
    {
        return $value === null || (is_array($value) && array_is_list($value)
            && collect($value)->every(fn ($item) => is_scalar($item)));
    }

    private function comparable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_array($value) ? json_encode($value) : (string) $value;
    }

    /** @return list<string> */
    private function secretKeys(): array
    {
        return ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];
    }
}
