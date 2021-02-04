<?php

namespace RateLimit;

interface RateLimiterStatus
{
    public function getRateLimitStatus($identifier, Rate $rate);
}
