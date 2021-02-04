<?php

namespace RateLimit;

use RateLimit\Exception\CannotUseRateLimiter;
use RateLimit\Exception\LimitExceeded;
use function apcu_fetch;
use function apcu_inc;
use function apcu_store;
use function extension_loaded;
use function ini_get;
use function max;
use function sprintf;
use function time;

final class ApcuRateLimiter implements RateLimiter, SilentRateLimiter, RateLimiterStatus
{
    /** @var string */
    private $keyPrefix;

    public function __construct($keyPrefix = '')
    {
        if (!extension_loaded('apcu') || ini_get('apc.enabled') === '0') {
            throw new CannotUseRateLimiter('APCu extension is not loaded or not enabled.');
        }

        if (ini_get('apc.use_request_time') === '1') {
            throw new CannotUseRateLimiter('APCu ini configuration "apc.use_request_time" should be set to "0".');
        }

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
        return (int) apcu_fetch($limitKey);
    }

    private function updateCounterAndTime($limitKey, $timeKey, $interval)
    {
        $current = $this->updateCounter($limitKey, $interval);

        if ($current === 1) {
            apcu_store($timeKey, time(), $interval);
        }

        return $current;
    }

    private function updateCounter($limitKey, $interval)
    {
        $current = apcu_inc($limitKey, 1, $success, $interval);

        return $current === false ? 1 : $current;
    }

    private function getElapsedTime($timeKey)
    {
        return time() - (int) apcu_fetch($timeKey);
    }
}
