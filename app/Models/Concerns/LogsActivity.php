<?php

namespace App\Models\Concerns;

use App\Services\ActivityLogger;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Support\Arr;

/**
 * Puts a model in the activity log (docs/activity-log.md). Changes are
 * found by comparing activitySnapshot() before and after a request, so a
 * model whose own row didn't change but whose related data did (a
 * ticket's personnel, an item's internal components, a role's
 * permissions) still gets its row.
 *
 * Models override activitySnapshot() to swap ids for names and add the
 * related data worth tracking; the default is the model's own columns.
 */
trait LogsActivity
{
    /** Columns never worth showing in a change list; models may add to it. */
    protected function activityIgnored(): array
    {
        return ['id', 'public_id', 'created_at', 'updated_at'];
    }

    public static function bootLogsActivity(): void
    {
        // Created outside a route-bound record (store routes) -- the
        // logger only listens while a logged request is running.
        static::created(fn ($model) => app(ActivityLogger::class)->created($model));
    }

    /**
     * What the log compares: field => scalar, or field => list of
     * strings (shown as added/removed).
     *
     * @return array<string, mixed>
     */
    public function activitySnapshot(): array
    {
        return self::normalizeActivityValues(
            Arr::except($this->getAttributes(), $this->activityIgnored()),
            $this,
        );
    }

    /** How the record is named in the log: ticket number, property number... */
    public function activityLabel(): string
    {
        return (string) $this->getKey();
    }

    /**
     * Casts applied (enums to their value, dates to text) so before and
     * after compare equal when nothing changed.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected static function normalizeActivityValues(array $values, ?self $model = null): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if ($model && array_key_exists($key, $model->getAttributes()) && $model->hasCast($key)) {
                $value = $model->getAttribute($key);
            }

            $normalized[$key] = match (true) {
                $value instanceof BackedEnum => $value->value,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                is_bool($value) => $value ? 1 : 0,
                default => $value,
            };
        }

        return $normalized;
    }
}
