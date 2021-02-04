<?php

namespace RateLimit;

use Predis\ClientInterface;
use RateLimit\Exception\LimitExceeded;
use function ceil;
use function max;
use function time;

final class PredisRateLimiter implements RateLimiter, SilentRateLimiter, RateLimiterStatus
{
    /** @var ClientInterface */
    private $predis;

    /** @var string */
    private $keyPrefix;

    public function __construct(ClientInterface $predis, $keyPrefix = '')
    {
        $this->predis = $predis;
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
        return (int) $this->predis->get($key);
    }

    private function updateCounter($key, $interval)
    {
        $current = $this->predis->incr($key);

        if ($current === 1) {
            $this->predis->expire($key, $interval);
        }

        return $current;
    }

    private function ttl($key)
    {
        return max((int) ceil($this->predis->pttl($key) / 1000), 0);
    }
}
