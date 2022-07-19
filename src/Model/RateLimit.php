<?php

declare(strict_types=1);

namespace Bedrock\Bundle\RateLimitBundle\Model;

class RateLimit
{
    /** @var array<string> */
    private array $vary = [];
    private readonly int $limit;
    private readonly int $period;

    public function __construct(int $limit, int $period)
    {
        if ($limit < 0 || $period < 0) {
            throw new \InvalidArgumentException('Limit and period must be > 0');
        }

        $this->limit = $limit;
        $this->period = $period;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getPeriod(): int
    {
        return $this->period;
    }

    public function varyHashOn(string $key, string $value): void
    {
        if (array_key_exists($key, $this->vary)) {
            throw new \InvalidArgumentException(sprintf('Key "%s" already exists in "vary" array.', $key));
        }

        $this->vary[$key] = $value;
    }

    public function getDiscriminator(): string
    {
        if (count($this->vary) === 0) {
            throw new \InvalidArgumentException('Cannot compute rate limit discriminator with an empty vary.');
        }

        return (string) json_encode($this->vary, JSON_THROW_ON_ERROR);
    }

    /**
     * @return string The current request's hash discriminator on which to calculate the rate limit
     */
    public function getHash(): string
    {
        return md5($this->getDiscriminator());
    }
}
