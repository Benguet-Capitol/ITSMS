<?php

namespace App\Models\Concerns;

use App\Support\DateSchedule;

/**
 * A JSON `schedule` column holding the request's dates (see DateSchedule).
 * The model keeps its older single-date columns in step through
 * syncScheduleColumns(), so sorting, indexes and anything still reading
 * those columns keep working.
 */
trait HasDateSchedule
{
    /** Copies the schedule into the model's derived date column(s). */
    abstract protected function syncScheduleColumns(): void;

    public function initializeHasDateSchedule(): void
    {
        $this->mergeCasts(['schedule' => 'array']);
        $this->mergeFillable(['schedule']);
    }

    protected static function bootHasDateSchedule(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('schedule')) {
                $model->schedule = $model->schedule ? DateSchedule::sort($model->schedule) : $model->schedule;
                $model->syncScheduleColumns();
            }
        });
    }

    /**
     * The schedule as written on the form, e.g. "October 2 to 4 and 7, 2026".
     * Includes the times when the dates have their own.
     */
    public function scheduleText(): ?string
    {
        return DateSchedule::format($this->schedule);
    }

    /** Whether the dates carry their own times instead of one shared time. */
    public function hasScheduleTimes(): bool
    {
        return DateSchedule::hasEntryTimes($this->schedule);
    }

    public function scheduleStart(): ?string
    {
        return DateSchedule::first($this->schedule);
    }

    public function scheduleEnd(): ?string
    {
        return DateSchedule::last($this->schedule);
    }
}
