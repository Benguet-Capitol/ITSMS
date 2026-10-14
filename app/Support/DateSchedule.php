<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\ProhibitedIf;

/**
 * A request's dates as a list of entries, each a single day or an
 * inclusive range: [{"from": "2026-10-02", "to": "2026-10-04"},
 * {"from": "2026-10-07", "to": null}]. Covers "October 2 to 4" as well as
 * "October 2 and 5" (and any mix of the two) on one request.
 *
 * Stored sorted, without overlaps, and with `to` null for single days.
 *
 * When the time differs between dates, entries also carry their own
 * `time` (and `time_to`, for a time window). The request's single time
 * field stays the normal case; prepare() folds per-date times that are all
 * the same back into it.
 */
class DateSchedule
{
    public const MAX_ENTRIES = 20;

    /** A start time per date, "HH:MM" (Network Assistance). */
    public const TIME_POINT = 'point';

    /** A time window per date, "HH:MM" to "HH:MM" (CCTV footage). */
    public const TIME_RANGE = 'range';

    /** Free-text time per date, e.g. "8:30 AM onwards" (Other IT Services). */
    public const TIME_TEXT = 'text';

    public const SHARED_TIME_MESSAGE = 'Use one time for all dates or a time for each date, not both.';

    private const TIME_KEYS = ['time', 'time_to'];

    /**
     * Drops blank rows and blank times, trims values and turns a same-day
     * range into a single day. Non-array input is returned untouched so
     * validation reports it. Row order is kept so validation errors point at
     * the row the user typed; sort() runs when the model saves.
     */
    public static function normalize(mixed $schedule): mixed
    {
        if (! is_array($schedule)) {
            return $schedule;
        }

        $entries = [];

        foreach ($schedule as $entry) {
            if (! is_array($entry)) {
                $entries[] = $entry;

                continue;
            }

            $from = is_string($entry['from'] ?? null) ? trim($entry['from']) : ($entry['from'] ?? null);
            $to = is_string($entry['to'] ?? null) ? trim($entry['to']) : ($entry['to'] ?? null);
            $from = $from === '' ? null : $from;
            $to = $to === '' || $to === $from ? null : $to;

            $normalized = ['from' => $from, 'to' => $to];

            foreach (self::TIME_KEYS as $key) {
                $value = is_string($entry[$key] ?? null) ? trim($entry[$key]) : ($entry[$key] ?? null);

                if ($value !== null && $value !== '') {
                    $normalized[$key] = $value;
                }
            }

            if ($normalized === ['from' => null, 'to' => null]) {
                continue;
            }

            $entries[] = $normalized;
        }

        return $entries;
    }

    /**
     * normalize(), then -- when the request has no shared time and every
     * entry carries the same time -- moves that time to the shared field(s),
     * so per-date times that are all the same are stored as one time.
     * Returns the values to merge into the request.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, string>  $sharedTimeFields  entry key => request field, e.g. ['time' => 'activity_time']
     * @return array<string, mixed>
     */
    public static function prepare(array $input, array $sharedTimeFields = [], string $field = 'schedule'): array
    {
        if (! array_key_exists($field, $input)) {
            return [];
        }

        $schedule = self::normalize($input[$field]);

        if (! $sharedTimeFields || ! is_array($schedule) || ! $schedule) {
            return [$field => $schedule];
        }

        foreach ($sharedTimeFields as $sharedField) {
            if (filled($input[$sharedField] ?? null)) {
                return [$field => $schedule];
            }
        }

        $common = null;

        foreach ($schedule as $entry) {
            if (! is_array($entry)) {
                return [$field => $schedule];
            }

            $times = array_map(fn (string $key) => $entry[$key] ?? null, array_keys($sharedTimeFields));

            if ($common !== null && $times !== $common) {
                return [$field => $schedule];
            }

            $common = $times;
        }

        if (! array_filter($common, fn ($value) => $value !== null)) {
            return [$field => $schedule];
        }

        foreach (array_keys($schedule) as $index) {
            foreach (array_keys($sharedTimeFields) as $key) {
                unset($schedule[$index][$key]);
            }
        }

        return [$field => $schedule, ...array_combine(array_values($sharedTimeFields), $common)];
    }

    /** Whether any entry has its own time. */
    public static function hasEntryTimes(mixed $schedule): bool
    {
        if (! is_array($schedule)) {
            return false;
        }

        foreach ($schedule as $entry) {
            if (is_array($entry) && (filled($entry['time'] ?? null) || filled($entry['time_to'] ?? null))) {
                return true;
            }
        }

        return false;
    }

    /**
     * For the request's shared time field: it can't be filled when the
     * dates carry their own times. Pair with SHARED_TIME_MESSAGE.
     */
    public static function sharedTimeRule(mixed $schedule): ProhibitedIf
    {
        return Rule::prohibitedIf(fn () => self::hasEntryTimes($schedule));
    }

    /** Entries ordered by start date, then start time (same-day CCTV windows). */
    public static function sort(array $schedule): array
    {
        usort($schedule, fn (array $a, array $b) => [(string) $a['from'], (string) ($a['time'] ?? '')] <=> [(string) $b['from'], (string) ($b['time'] ?? '')]);

        return $schedule;
    }

    /**
     * Validation rules for a schedule field.
     *
     * @param  string|null  $notAfterField  another date field no schedule date may come after
     * @param  string|null  $time  TIME_POINT, TIME_RANGE or TIME_TEXT when each date may carry its own time
     * @return array<string, array<mixed>>
     */
    public static function rules(string $field = 'schedule', bool $required = true, ?string $notAfterField = null, ?string $time = null): array
    {
        $notAfter = $notAfterField ? ["before_or_equal:{$notAfterField}"] : [];

        $timeRules = match ($time) {
            self::TIME_POINT => [
                'time' => ['nullable', 'date_format:H:i'],
            ],
            self::TIME_RANGE => [
                'time' => ['nullable', 'date_format:H:i'],
                'time_to' => ['nullable', 'date_format:H:i', "required_with:{$field}.*.time"],
            ],
            self::TIME_TEXT => [
                'time' => ['nullable', 'string', 'max:50'],
            ],
            null => [],
        };

        $rules = [
            $field => [
                $required ? 'required' : 'nullable',
                'array',
                $required ? 'min:1' : 'min:0',
                'max:'.self::MAX_ENTRIES,
                self::noOverlapRule($time),
            ],
            "{$field}.*" => ['array:'.implode(',', ['from', 'to', ...array_keys($timeRules)])],
            "{$field}.*.from" => ['required', 'date_format:Y-m-d', ...$notAfter],
            "{$field}.*.to" => ['nullable', 'date_format:Y-m-d', "after_or_equal:{$field}.*.from", ...$notAfter],
        ];

        foreach ($timeRules as $key => $keyRules) {
            $rules["{$field}.*.{$key}"] = $keyRules;
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(string $field = 'schedule', string $noun = 'date'): array
    {
        return [
            "{$field}.required" => "Add at least one {$noun}.",
            "{$field}.min" => "Add at least one {$noun}.",
            "{$field}.max" => 'A request can list at most '.self::MAX_ENTRIES.' dates or date ranges.',
            "{$field}.*.from.required" => 'Pick a date.',
            "{$field}.*.from.date_format" => 'Pick a valid date.',
            "{$field}.*.to.date_format" => 'Pick a valid date.',
            "{$field}.*.to.after_or_equal" => 'The end date cannot be before the start date.',
            "{$field}.*.time.date_format" => 'Pick a valid time.',
            "{$field}.*.time.max" => 'Keep the time under 50 characters.',
            "{$field}.*.time_to.date_format" => 'Pick a valid time.',
            "{$field}.*.time_to.required_with" => 'Please provide the end of the time.',
        ];
    }

    /**
     * Rejects entries that share a day -- except, with time windows
     * (TIME_RANGE), entries whose windows don't overlap: CCTV footage can be
     * requested for 8-9 AM and 3-4 PM of the same day. Invalid rows are left
     * to their own rules.
     */
    private static function noOverlapRule(?string $time): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($time) {
            if (! is_array($value)) {
                return;
            }

            foreach ($value as $entry) {
                if (! self::isValidEntry($entry)) {
                    return;
                }
            }

            $entries = self::sort($value);

            // Pairwise: with same-day entries allowed, a clash isn't always
            // with the entry right before.
            foreach ($entries as $i => $a) {
                foreach (array_slice($entries, $i + 1) as $b) {
                    if ($b['from'] > ($a['to'] ?? $a['from'])) {
                        break;
                    }

                    $windows = $time === self::TIME_RANGE ? [self::window($a), self::window($b)] : [null, null];

                    if ($windows[0] && $windows[1]) {
                        if (! self::windowsOverlap(...$windows)) {
                            continue;
                        }

                        $fail('The footage times overlap on the same day: '.self::format([$a]).' and '.self::format([$b]).'.');

                        return;
                    }

                    $fail('The dates overlap: '.self::formatDates([$a]).' and '.self::formatDates([$b]).'.'
                        .($time === self::TIME_RANGE ? ' Give each its own time window to list the same day twice.' : ''));

                    return;
                }
            }
        };
    }

    private static function isValidEntry(mixed $entry): bool
    {
        if (! is_array($entry) || ! self::isDate($entry['from'] ?? null)) {
            return false;
        }

        $to = $entry['to'] ?? null;

        return $to === null || (self::isDate($to) && $to >= $entry['from']);
    }

    /**
     * An entry's time window as minute intervals within a day, or null
     * without a complete, valid window. A window ending at or before its
     * start runs past midnight: 22:00-02:00 covers 22:00-24:00 and 00:00-02:00.
     *
     * @return array<int, array{int, int}>|null
     */
    private static function window(array $entry): ?array
    {
        $minutes = function (mixed $value): ?int {
            if (! is_string($value) || ! preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $value, $match)) {
                return null;
            }

            return (int) $match[1] * 60 + (int) $match[2];
        };

        $start = $minutes($entry['time'] ?? null);
        $end = $minutes($entry['time_to'] ?? null);

        if ($start === null || $end === null) {
            return null;
        }

        return $end > $start ? [[$start, $end]] : [[$start, 1440], [0, $end]];
    }

    private static function windowsOverlap(array $a, array $b): bool
    {
        foreach ($a as [$aStart, $aEnd]) {
            foreach ($b as [$bStart, $bEnd]) {
                if ($aStart < $bEnd && $bStart < $aEnd) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isDate(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    /** Earliest date in the schedule (Y-m-d), or null when empty. */
    public static function first(?array $schedule): ?string
    {
        return $schedule ? $schedule[0]['from'] : null;
    }

    /** Latest date in the schedule (Y-m-d), or null when empty. */
    public static function last(?array $schedule): ?string
    {
        if (! $schedule) {
            return null;
        }

        $lastEntry = $schedule[array_key_last($schedule)];

        return $lastEntry['to'] ?? $lastEntry['from'];
    }

    /**
     * Reads the schedule the way it's written on the paper forms:
     *   [Oct 2]                       "October 2, 2026"
     *   [Oct 2 - Oct 4]               "October 2 to 4, 2026"
     *   [Oct 2], [Oct 5]              "October 2 and 5, 2026"
     *   [Oct 2 - 4], [Oct 7], [Nov 3] "October 2 to 4, 7 and November 3, 2026"
     *   [Oct 30 - Nov 2]              "October 30 to November 2, 2026"
     * Dates spanning more than one year are written in full.
     *
     * When dates carry their own time, consecutive dates with the same time
     * are grouped and each group gets its time:
     *   "October 2 to 4, 2026 (8:00 AM); October 5, 2026 (1:00 PM)"
     */
    public static function format(?array $schedule): ?string
    {
        if (! $schedule) {
            return null;
        }

        if (! self::hasEntryTimes($schedule)) {
            return self::formatDates($schedule);
        }

        // Same day(s) listed again with another time window (CCTV): one
        // date with both windows, "October 2, 2026 (8:00 AM - 9:00 AM and
        // 3:00 PM - 4:00 PM)".
        $dated = [];

        foreach ($schedule as $entry) {
            $time = self::timeText($entry);
            $last = array_key_last($dated);

            if ($last !== null && $time !== null && $dated[$last]['time'] !== null
                && $dated[$last]['entry']['from'] === $entry['from']
                && ($dated[$last]['entry']['to'] ?? null) === ($entry['to'] ?? null)) {
                $dated[$last]['time'] .= " and {$time}";
            } else {
                $dated[] = ['entry' => $entry, 'time' => $time];
            }
        }

        $groups = [];

        foreach ($dated as ['entry' => $entry, 'time' => $time]) {
            $last = array_key_last($groups);

            if ($last !== null && $groups[$last]['time'] === $time) {
                $groups[$last]['entries'][] = $entry;
            } else {
                $groups[] = ['time' => $time, 'entries' => [$entry]];
            }
        }

        return implode('; ', array_map(
            fn (array $group) => self::formatDates($group['entries']).($group['time'] !== null ? " ({$group['time']})" : ''),
            $groups,
        ));
    }

    /**
     * An entry's own time for display: "8:00 AM", "8:00 AM - 10:00 AM", or
     * free text as typed. Null when the entry has none.
     */
    public static function timeText(array $entry): ?string
    {
        $parts = array_values(array_filter(
            array_map(fn (string $key) => $entry[$key] ?? null, self::TIME_KEYS),
            fn ($value) => filled($value),
        ));

        if (! $parts) {
            return null;
        }

        return implode(' - ', array_map(
            fn (string $value) => preg_match('/^\d{2}:\d{2}$/', $value)
                ? Carbon::createFromFormat('H:i', $value)->format('g:i A')
                : $value,
            $parts,
        ));
    }

    /** The dates alone, ignoring any per-date times. */
    private static function formatDates(array $schedule): string
    {
        $entries = array_map(fn (array $entry) => [
            Carbon::parse($entry['from']),
            ($entry['to'] ?? null) ? Carbon::parse($entry['to']) : null,
        ], $schedule);

        $years = [];
        foreach ($entries as [$from, $to]) {
            $years[$from->year] = true;
            if ($to) {
                $years[$to->year] = true;
            }
        }

        if (count($years) > 1) {
            $parts = array_map(
                fn (array $entry) => $entry[0]->format('F j, Y').($entry[1] ? ' to '.$entry[1]->format('F j, Y') : ''),
                $entries,
            );

            return self::joinNatural($parts);
        }

        $parts = [];
        $month = null;

        foreach ($entries as [$from, $to]) {
            $part = $from->month === $month ? $from->format('j') : $from->format('F j');

            if ($to) {
                $part .= ' to '.($to->month === $from->month ? $to->format('j') : $to->format('F j'));
            }

            $parts[] = $part;
            $month = ($to ?? $from)->month;
        }

        return self::joinNatural($parts).', '.array_key_first($years);
    }

    /** "a", "a and b", "a, b and c". */
    private static function joinNatural(array $parts): string
    {
        if (count($parts) < 2) {
            return $parts[0] ?? '';
        }

        $last = array_pop($parts);

        return implode(', ', $parts).' and '.$last;
    }
}
