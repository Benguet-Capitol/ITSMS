<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Working-day arithmetic for the "late to accept" rule: Monday to Friday
 * count, weekends don't. Holidays aren't known to ITSMS, so they count as
 * working days.
 *
 * Something filed at a moment T is late once one working day has passed:
 * Thursday 15:00 is late from Friday 15:00, Friday 15:00 from Monday
 * 15:00, and anything filed over the weekend from the end of Monday.
 */
class WorkingDays
{
    /**
     * The moment `$days` working days before `$now`: whatever was filed
     * before it has been waiting longer than that. Inverse of counting
     * forward from the filing time, so it can go straight into a
     * `created_at < ?` query.
     */
    public static function cutoff(CarbonInterface $now, int $days = 1): CarbonImmutable
    {
        $cutoff = CarbonImmutable::instance($now);

        // A weekend moment is, in working time, the end of Friday.
        if ($cutoff->isWeekend()) {
            $cutoff = $cutoff->previous(CarbonInterface::FRIDAY)->addDay()->startOfDay();
        }

        for ($i = 0; $i < $days; $i++) {
            $cutoff = $cutoff->subDay();

            // Landing on a weekend (only from a Monday) skips it entirely.
            if ($cutoff->isWeekend()) {
                $cutoff = $cutoff->subDays($cutoff->isSunday() ? 2 : 1);
            }
        }

        return $cutoff;
    }
}
