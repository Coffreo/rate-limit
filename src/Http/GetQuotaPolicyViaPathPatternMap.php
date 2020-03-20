<?php

declare(strict_types=1);

namespace RateLimit\Http;

use Assert\Assertion;
use Psr\Http\Message\ServerRequestInterface;
use RateLimit\QuotaPolicy;

final class GetQuotaPolicyViaPathPatternMap implements GetQuotaPolicy
{
    /** @var array */
    private $pathPatternQuotaPolicyMap;

    public function __construct(array $pathPatternQuotaPolicyMap)
    {
        Assertion::allString(array_keys($pathPatternQuotaPolicyMap));
        Assertion::allIsInstanceOf($pathPatternQuotaPolicyMap, QuotaPolicy::class);

        $this->pathPatternQuotaPolicyMap = $pathPatternQuotaPolicyMap;
    }

    public function forRequest(ServerRequestInterface $request): ?QuotaPolicy
    {
        $path = $request->getUri()->getPath();

        foreach ($this->pathPatternQuotaPolicyMap as $pattern => $quotaPolicy) {
            if (preg_match($pattern, $path)) {
                return $quotaPolicy;
            }
        }

        return null;
    }
}
