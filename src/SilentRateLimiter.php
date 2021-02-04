<?php

namespace RateLimit;

interface SilentRateLimiter
{
    public function limitSilently($identifier, Rate $rate);
}
