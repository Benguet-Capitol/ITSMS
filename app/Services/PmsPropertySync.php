<?php

namespace App\Services;

use App\Models\PmsProperty;
use App\Models\PmsSyncRun;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Copies PGSO's property list into pms_properties
 * (docs/inventory-reconciliation.md). Read-only towards PMS: one GET of
 * /properties, which returns everything at once.
 *
 * The copy is replaced as a whole, inside a transaction. A response that
 * would shrink the copy by more than half is refused rather than trusted,
 * so a broken or partial feed can't wipe the comparison.
 */
class PmsPropertySync
{
    /** Lock name shared by the nightly command and the "Sync now" button. */
    public const LOCK = 'pms:sync';

    /** Below this share of the current copy, a new feed is treated as broken. */
    private const MIN_SHARE_OF_PREVIOUS = 0.5;

    public static function isConfigured(): bool
    {
        return filled(config('services.pms.base_url')) && filled(config('services.pms.api_key'));
    }

    /** Starts and finishes one sync. Returns the run, or null if one is already running. */
    public function run(?int $triggeredByProfileId = null): ?PmsSyncRun
    {
        $run = $this->start($triggeredByProfileId);

        return $run ? $this->execute($run) : null;
    }

    /** The run in progress, if any. A run that outlived the timeout crashed and is closed as failed. */
    public function activeRun(): ?PmsSyncRun
    {
        $staleBefore = now()->subSeconds($this->lockSeconds());

        PmsSyncRun::where('status', PmsSyncRun::RUNNING)
            ->where('started_at', '<', $staleBefore)
            ->update([
                'status' => PmsSyncRun::FAILED,
                'error' => 'The sync stopped without finishing.',
                'finished_at' => now(),
            ]);

        return PmsSyncRun::where('status', PmsSyncRun::RUNNING)->latest('id')->first();
    }

    /**
     * Records a new run as running, so the page can follow it before the
     * download begins. Null if one is already running.
     */
    public function start(?int $triggeredByProfileId = null): ?PmsSyncRun
    {
        if ($this->activeRun()) {
            return null;
        }

        return PmsSyncRun::create([
            'status' => PmsSyncRun::RUNNING,
            'triggered_by_profile_id' => $triggeredByProfileId,
            'started_at' => now(),
        ]);
    }

    /** Downloads and stores the list for a started run, and records how it went. */
    public function execute(PmsSyncRun $run): PmsSyncRun
    {
        $lock = Cache::lock(self::LOCK, $this->lockSeconds());

        if (! $lock->get()) {
            $run->update([
                'status' => PmsSyncRun::FAILED,
                'error' => 'Another sync was already running.',
                'finished_at' => now(),
            ]);

            return $run;
        }

        try {
            return $this->executeLocked($run);
        } finally {
            $lock->release();
        }
    }

    private function lockSeconds(): int
    {
        return (int) config('services.pms.timeout') + 120;
    }

    private function executeLocked(PmsSyncRun $run): PmsSyncRun
    {
        try {
            if (! self::isConfigured()) {
                throw new RuntimeException('PMS_BASE_URL and PMS_API_KEY are not set.');
            }

            [$rows, $skipped] = $this->rowsFromFeed($this->fetch());
            $this->replaceCopy($rows, $run->started_at);

            $run->update([
                'status' => PmsSyncRun::SUCCEEDED,
                'row_count' => count($rows),
                'skipped_count' => $skipped,
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            $run->update([
                'status' => PmsSyncRun::FAILED,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);
        }

        return $run;
    }

    private function fetch(): array
    {
        // A whole-list download can take a while; don't let PHP's own limit cut it short.
        set_time_limit((int) config('services.pms.timeout') + 60);

        $response = Http::timeout((int) config('services.pms.timeout'))
            ->withHeaders([
                'x-api-key' => config('services.pms.api_key'),
                'Accept' => 'application/json',
            ])
            ->get(rtrim((string) config('services.pms.base_url'), '/').'/properties');

        $response->throw();

        $body = $response->json();

        if (! is_array($body) || ($body['success'] ?? null) !== true || ! is_array($body['data'] ?? null)) {
            throw new RuntimeException('PMS answered, but not with a property list.');
        }

        return $body['data'];
    }

    /**
     * Rows ready to store, and how many were skipped (no id or number, or
     * a repeated id).
     *
     * @return array{0: list<array<string, mixed>>, 1: int}
     */
    private function rowsFromFeed(array $feed): array
    {
        $rows = [];
        $skipped = 0;

        foreach ($feed as $item) {
            $id = filter_var($item['property_id'] ?? null, FILTER_VALIDATE_INT);
            $number = trim((string) ($item['property_no'] ?? ''));

            if (! $id || $id < 1 || $number === '' || isset($rows[$id])) {
                $skipped++;

                continue;
            }

            $mainId = filter_var($item['main_id'] ?? null, FILTER_VALIDATE_INT);
            $price = $item['property_price'] ?? null;

            $rows[$id] = [
                'id' => $id,
                'main_id' => $mainId ?: null,
                'property_name' => $this->text($item['property_name'] ?? null, 255),
                'property_no' => mb_substr($number, 0, 255),
                'property_no_key' => mb_substr(PmsProperty::numberKey($number), 0, 255),
                'property_no_core' => mb_substr(PmsProperty::numberCore($number), 0, 255),
                'property_unit' => $this->text($item['property_unit'] ?? null, 255),
                'property_price' => is_numeric($price) ? $price : null,
                'property_details' => $this->text($item['property_details'] ?? null),
                'serial_number' => mb_substr((string) PmsProperty::serialFromDetails($item['property_details'] ?? null), 0, 255) ?: null,
                'full_name' => $this->text($item['full_name'] ?? null, 255),
                'office_code' => $this->text($item['office_code'] ?? null, 255),
            ];
        }

        return [array_values($rows), $skipped];
    }

    private function text(mixed $value, ?int $max = null): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return $max ? mb_substr($value, 0, $max) : $value;
    }

    private function replaceCopy(array $rows, \DateTimeInterface $syncedAt): void
    {
        $previous = PmsProperty::count();

        if ($rows === [] || ($previous > 0 && count($rows) < $previous * self::MIN_SHARE_OF_PREVIOUS)) {
            throw new RuntimeException(sprintf(
                'PMS returned %d properties against %d last time; the copy was kept as it was.',
                count($rows),
                $previous,
            ));
        }

        DB::transaction(function () use ($rows, $syncedAt) {
            foreach (array_chunk($rows, 500) as $chunk) {
                PmsProperty::upsert(
                    array_map(fn (array $row) => [...$row, 'synced_at' => $syncedAt], $chunk),
                    ['id'],
                );
            }

            // Gone from PMS since the last sync.
            PmsProperty::where('synced_at', '<', $syncedAt)->delete();
        });
    }
}
