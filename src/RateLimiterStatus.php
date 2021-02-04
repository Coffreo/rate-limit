<?php

declare(strict_types=1);

namespace RateLimit;

interface RateLimiterStatus
{
    public function getRateLimitStatus(string $identifier, Rate $rate): Status;
}
