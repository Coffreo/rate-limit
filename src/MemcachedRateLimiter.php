<?php

namespace RateLimit;

use Memcached;
use RateLimit\Exception\CannotUseRateLimiter;
use RateLimit\Exception\LimitExceeded;
use function max;
use function sprintf;
use function time;

final class MemcachedRateLimiter implements RateLimiter, SilentRateLimiter, RateLimiterStatus
{
    const MEMCACHED_SECONDS_LIMIT = 2592000; // 30 days in seconds

    /** @var Memcached */
    private $memcached;

    /** @var string */
    private $keyPrefix;

    public function __construct(Memcached $memcached, $keyPrefix = '')
    {
        // @see https://www.php.net/manual/en/memcached.increment.php#111187
        if ($memcached->getOption(Memcached::OPT_BINARY_PROTOCOL) !== 1) {
            throw new CannotUseRateLimiter('Memcached "OPT_BINARY_PROTOCOL" option should be set to "true".');
        }

        $this->memcached = $memcached;
        $this->keyPrefix = $keyPrefix;
    }

    public function limit($identifier, Rate $rate)
    {
        $limitKey = $this->limitKey($identifier, $rate->getInterval());

        $current = $this->getCurrent($limitKey);
        if ($current >= $rate->getOperations()) {
            throw LimitExceeded::forData($identifier, $rate);
        }

        $this->updateCounter($limitKey, $rate->getInterval());
    }

    public function limitSilently($identifier, Rate $rate)
    {
        $interval = $rate->getInterval();
        $limitKey = $this->limitKey($identifier, $interval);
        $timeKey = $this->timeKey($identifier, $interval);

        $current = $this->getCurrent($limitKey);
        if ($current <= $rate->getOperations()) {
            $current = $this->updateCounterAndTime($limitKey, $timeKey, $interval);
        }

        return Status::from(
            $identifier,
            $current,
            $rate->getOperations(),
            time() + max(0, $interval - $this->getElapsedTime($timeKey))
        );
    }

    public function getRateLimitStatus($identifier, Rate $rate)
    {
        $interval = $rate->getInterval();
        $timeKey = $this->timeKey($identifier, $interval);
        $limitKey = $this->limitKey($identifier, $interval);

        $current = $this->getCurrent($limitKey) ;

        return Status::from(
            $identifier,
            $current,
            $rate->getOperations(),
            time() + max(0, $interval - $this->getElapsedTime($timeKey))
        );
    }

    private function limitKey($identifier, $interval)
    {
        return sprintf('%s%s:%d', $this->keyPrefix, $identifier, $interval);
    }

    private function timeKey($identifier, $interval)
    {
        return sprintf('%s%s:%d:time', $this->keyPrefix, $identifier, $interval);
    }

    private function getCurrent($limitKey)
    {
        return (int) $this->memcached->get($limitKey);
    }

    private function updateCounterAndTime($limitKey, $timeKey, $interval)
    {
        $current = $this->updateCounter($limitKey, $interval);

        if ($current === 1) {
            $this->memcached->add($timeKey, time(), $this->intervalToMemcachedTime($interval));
        }

        return $current;
    }

    private function updateCounter($limitKey, $interval)
    {
        $current = $this->memcached->increment($limitKey, 1, 1, $this->intervalToMemcachedTime($interval));

        return $current === false ? 1 : $current;
    }

    private function getElapsedTime($timeKey)
    {
        return time() - (int) $this->memcached->get($timeKey);
    }

    /**
     * Interval to Memcached expiration time.
     *
     * @see https://www.php.net/manual/en/memcached.expiration.php
     *
     * @param $interval
     * @return int
     */
    private function intervalToMemcachedTime($interval)
    {
        return $interval <= self::MEMCACHED_SECONDS_LIMIT ? $interval : time() + $interval;
    }
}
