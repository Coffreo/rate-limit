<?php

namespace RateLimit;

use DateTimeImmutable;
use function max;

class Status
{
    /** @var string */
    protected $identifier;

    /** @var bool */
    protected $success;

    /** @var int */
    protected $limit;

    /** @var int */
    protected $remainingAttempts;

    /** @var DateTimeImmutable */
    protected $resetAt;

    final protected function __construct($identifier, $success, $limit, $remainingAttempts, DateTimeImmutable $resetAt)
    {
        $this->identifier = $identifier;
        $this->success = $success;
        $this->limit = $limit;
        $this->remainingAttempts = $remainingAttempts;
        $this->resetAt = $resetAt;
    }

    public static function from($identifier, $current, $limit, $resetTime)
    {
        return new static(
            $identifier,
            $current <= $limit,
            $limit,
            max(0, $limit - $current),
            new DateTimeImmutable("@$resetTime")
        );
    }

    public function getIdentifier()
    {
        return $this->identifier;
    }

    public function limitExceeded()
    {
        return !$this->success;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function getRemainingAttempts()
    {
        return $this->remainingAttempts;
    }

    public function getResetAt()
    {
        return $this->resetAt;
    }
}
