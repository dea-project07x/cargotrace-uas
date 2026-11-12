<?php

namespace App\Services;

use App\Enums\Severity;

final class DelayResult
{
    public function __construct(
        public readonly bool $isDelayed,
        public readonly int $delayMinutes,
        public readonly ?Severity $severity,
        public readonly string $reason,
    ) {
    }
}
