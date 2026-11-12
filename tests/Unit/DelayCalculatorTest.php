<?php

namespace Tests\Unit;

use App\Enums\Severity;
use App\Services\DelayCalculator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Pure rule tests: no framework, no database. Bands: LOW <= 6h, MEDIUM <= 24h, HIGH beyond. */
class DelayCalculatorTest extends TestCase
{
    private function calc(): DelayCalculator
    {
        return new DelayCalculator(6, 24, 0);
    }

    public static function cases(): array
    {
        return [
            'early arrival' => [-120, false, null],
            'exactly on time' => [0, false, null],
            '1 minute late' => [1, true, Severity::Low],
            'exactly 6h' => [360, true, Severity::Low],
            '6h + 1 min' => [361, true, Severity::Medium],
            'exactly 24h' => [1440, true, Severity::Medium],
            '24h + 1 min' => [1441, true, Severity::High],
            '3 days' => [4320, true, Severity::High],
        ];
    }

    #[DataProvider('cases')]
    public function test_severity_bands(int $shiftMinutes, bool $delayed, ?Severity $severity): void
    {
        $expected = CarbonImmutable::parse('2026-12-01 10:00:00');
        $result = $this->calc()->calculate($expected, $expected->addMinutes($shiftMinutes));

        $this->assertSame($delayed, $result->isDelayed);
        $this->assertSame($severity, $result->severity);
        $this->assertSame($delayed ? $shiftMinutes : 0, $result->delayMinutes);
    }

    public function test_reason_states_the_values_used(): void
    {
        $expected = CarbonImmutable::parse('2026-12-01 10:00:00');
        $result = $this->calc()->calculate($expected, $expected->addHours(7));

        $this->assertStringContainsString('MEDIUM', $result->reason);
        $this->assertStringContainsString('7.0 h', $result->reason);
        $this->assertStringContainsString('2026-12-01 10:00', $result->reason);
    }

    public function test_thresholds_are_configurable_per_group(): void
    {
        $expected = CarbonImmutable::parse('2026-12-01 10:00:00');
        $groupA = new DelayCalculator(12, 36, 0);

        $this->assertSame(Severity::Low, $groupA->calculate($expected, $expected->addHours(10))->severity);
        $this->assertSame(Severity::Medium, $groupA->calculate($expected, $expected->addHours(30))->severity);
        $this->assertSame(Severity::High, $groupA->calculate($expected, $expected->addHours(40))->severity);
    }

    public function test_grace_period_ignores_small_delays(): void
    {
        $expected = CarbonImmutable::parse('2026-12-01 10:00:00');
        $calc = new DelayCalculator(6, 24, 30);

        $this->assertFalse($calc->calculate($expected, $expected->addMinutes(30))->isDelayed);
        $this->assertTrue($calc->calculate($expected, $expected->addMinutes(31))->isDelayed);
    }
}
