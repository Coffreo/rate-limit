<?php

namespace RateLimit\Tests;

use RateLimit\RateLimiter;
use RateLimit\RedisRateLimiter;
use Redis;
use function extension_loaded;

class RedisRateLimiterTest extends RateLimiterTest
{
    protected function getRateLimiter()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not loaded.');
        }

        $redis = new Redis();

        $success = @ $redis->connect('redis.coffreo.ext', 6379);

        if (!$success) {
            $this->markTestSkipped('Cannot connect to Redis.');
        }

        $redis->flushDB();

        return new RedisRateLimiter($redis);
    }
}
