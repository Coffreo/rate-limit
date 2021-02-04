<?php

namespace RateLimit;

use RateLimit\Exception\LimitExceeded;
use Redis;
use function ceil;
use function max;
use function time;

final class RedisRateLimiter implements RateLimiter, SilentRateLimiter, RateLimiterStatus
{
    /** @var Redis */
    private $redis;

    /** @var string */
    private $keyPrefix;

    public function __construct(Redis $redis, $keyPrefix = '')
    {
        $this->redis = $redis;
        $this->keyPrefix = $keyPrefix;
    }

    public function limit($identifier, Rate $rate)
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->getCurrent($key);

        if ($current >= $rate->getOperations()) {
            throw LimitExceeded::forData($identifier, $rate);
        }

        $this->updateCounter($key, $rate->getInterval());
    }

    public function limitSilently($identifier, Rate $rate)
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->getCurrent($key);

        if ($current <= $rate->getOperations()) {
            $current = $this->updateCounter($key, $rate->getInterval());
        }

        return Status::from(
            $identifier,
            $current,
            $rate->getOperations(),
            time() + $this->ttl($key)
        );
    }

    public function getRateLimitStatus($identifier, Rate $rate)
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->getCurrent($key) ;

        return Status::from(
            $identifier,
            $current,
            $rate->getOperations(),
            time() + $this->ttl($key)
        );
    }

    private function key($identifier, $interval)
    {
        return "{$this->keyPrefix}{$identifier}:$interval";
    }

    private function getCurrent($key)
    {
        return (int) $this->redis->get($key);
    }

    private function updateCounter($key, $interval)
    {
        $current = $this->redis->incr($key);

        if ($current === 1) {
            $this->redis->expire($key, $interval);
        }

        return $current;
    }

    private function ttl($key)
    {
        return max((int) ceil($this->redis->pttl($key) / 1000), 0);
    }
}
