<?php

namespace RateLimit\Tests;

use RateLimit\InMemoryRateLimiter;
use RateLimit\RateLimiter;

class InMemoryRateLimiterTest extends RateLimiterTest
{
    protected function getRateLimiter()
    {
        return new InMemoryRateLimiter();
    }
}
