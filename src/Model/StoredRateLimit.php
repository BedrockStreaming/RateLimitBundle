<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Model;

class StoredRateLimit
{
    public function __construct(private RateLimit $rateLimit, private int $hits, private \DateTimeImmutable $validUntil)
    {
    }

    public function getHash(): string
    {
        return $this->rateLimit->getHash();
    }

    public function getLimit(): int
    {
        return $this->rateLimit->getLimit();
    }

    public function getHits(): int
    {
        return $this->hits;
    }

    public function withHits(int $hits): self
    {
        $clone = clone $this;
        $clone->hits = $hits;

        return $clone;
    }

    public function isOutdated(): bool
    {
        return $this->validUntil < \DateTimeImmutable::createFromFormat('U', (string) time());
    }

    public function getValidUntil(): \DateTimeImmutable
    {
        return $this->validUntil;
    }

    /**
     * @return array<string, array<string>|int|string>
     */
    public function getLimitReachedOutput(): array
    {
        return [
            'message' => sprintf(
                'Too many requests. Only %d calls allowed every %d seconds.',
                $this->getLimit(),
                $this->rateLimit->getPeriod()
            ),
            'limit' => $this->getLimit(),
            'period' => $this->rateLimit->getPeriod(),
            'until' => $this->getValidUntil()->format('Y-m-d H:i:s'),
            'vary' => $this->rateLimit->getDiscriminator(),
        ];
    }
}
