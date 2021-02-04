<?php

namespace RateLimit\Tests;

use RateLimit\ApcuRateLimiter;
use RateLimit\Exception\CannotUseRateLimiter;
use RateLimit\RateLimiter;
use function apcu_clear_cache;

class ApcuRateLimiterTest extends RateLimiterTest
{
    protected function getRateLimiter()
    {
        try {
            $rateLimiter = new ApcuRateLimiter();
        } catch (CannotUseRateLimiter $exception) {
            $this->markTestSkipped($exception->getMessage());
        }

        apcu_clear_cache();

        return $rateLimiter;
    }
}
