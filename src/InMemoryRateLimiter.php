<?php

namespace RateLimit;

use RateLimit\Exception\LimitExceeded;
use function floor;
use function time;

final class InMemoryRateLimiter implements RateLimiter, SilentRateLimiter
{
    /** @var array */
    private $store = [];

    public function limit($identifier, Rate $rate)
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->hit($key, $rate);

        if ($current > $rate->getOperations()) {
            throw LimitExceeded::forData($identifier, $rate);
        }
    }

    public function limitSilently($identifier, Rate $rate)
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->hit($key, $rate);

        return Status::from(
            $identifier,
            $current,
            $rate->getOperations(),
            $this->store[$key]['reset_time']
        );
    }

    private function key($identifier, $interval)
    {
        return "$identifier:$interval:" . floor(time() / $interval);
    }

    private function hit($key, Rate $rate)
    {
        if (!isset($this->store[$key])) {
            $this->store[$key] = [
                'current' => 1,
                'reset_time' => time() + $rate->getInterval(),
            ];
        } elseif ($this->store[$key]['current'] <= $rate->getOperations()) {
            $this->store[$key]['current']++;
        }

        return $this->store[$key]['current'];
    }
}
