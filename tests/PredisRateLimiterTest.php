<?php

namespace RateLimit\Tests;

use Predis\Client;
use RateLimit\PredisRateLimiter;
use RateLimit\RateLimiter;
use function class_exists;

class PredisRateLimiterTest extends RateLimiterTest
{
    protected function getRateLimiter()
    {
        if (!class_exists('Predis\Client')) {
            $this->markTestSkipped('Predis library is not available');
        }

        $predis = new Client('tcp://redis.coffreo.ext:6379');

        $predis->connect();
        if (!$predis->isConnected()) {
            $this->markTestSkipped('Cannot connect with Predis.');
        }

        $predis->flushdb();

        return new PredisRateLimiter($predis);
    }
}
