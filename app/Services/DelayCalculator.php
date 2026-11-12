<?php

namespace App\Services;

use App\Enums\Severity;
use Carbon\CarbonInterface;

/**
 * Pure, deterministic delay rule (no database, easy to unit-test).
 *
 *   delay = estimated - expected
 *   delay <= grace                       => not delayed
 *   delay <= lowMaxHours                 => LOW
 *   delay <= mediumMaxHours              => MEDIUM
 *   otherwise                            => HIGH
 *
 * Thresholds default to config('cargotrace.delay.*') so each group can set
 * its own parameters through .env.
 */
class DelayCalculator
{
    private int $lowMaxHours;
    private int $mediumMaxHours;
    private int $graceMinutes;

    public function __construct(?int $lowMaxHours = null, ?int $mediumMaxHours = null, ?int $graceMinutes = null)
    {
        $this->lowMaxHours = $lowMaxHours ?? (int) config('cargotrace.delay.low_max_hours', 6);
        $this->mediumMaxHours = $mediumMaxHours ?? (int) config('cargotrace.delay.medium_max_hours', 24);
        $this->graceMinutes = $graceMinutes ?? (int) config('cargotrace.delay.grace_minutes', 0);
    }

    public function calculate(CarbonInterface $expected, CarbonInterface $estimated): DelayResult
    {
        $format = 'Y-m-d H:i';
        $delaySeconds = $estimated->getTimestamp() - $expected->getTimestamp();

        if ($delaySeconds <= $this->graceMinutes * 60) {
            return new DelayResult(
                false,
                0,
                null,
                sprintf(
                    'On time: arrival %s is not later than expected %s (grace %d min).',
                    $estimated->format($format),
                    $expected->format($format),
                    $this->graceMinutes
                )
            );
        }

        $severity = match (true) {
            $delaySeconds <= $this->lowMaxHours * 3600 => Severity::Low,
            $delaySeconds <= $this->mediumMaxHours * 3600 => Severity::Medium,
            default => Severity::High,
        };

        return new DelayResult(
            true,
            (int) ceil($delaySeconds / 60),
            $severity,
            sprintf(
                'Delayed (%s): arrival %s is %.1f h after expected %s (LOW <= %dh, MEDIUM <= %dh, HIGH beyond).',
                $severity->value,
                $estimated->format($format),
                $delaySeconds / 3600,
                $expected->format($format),
                $this->lowMaxHours,
                $this->mediumMaxHours
            )
        );
    }
}
