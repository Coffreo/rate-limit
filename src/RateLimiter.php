<?php

namespace RateLimit;

use RateLimit\Exception\LimitExceeded;

interface RateLimiter
{
    /**
     * @throws LimitExceeded
     */
    public function limit($identifier, Rate $rate);
}
