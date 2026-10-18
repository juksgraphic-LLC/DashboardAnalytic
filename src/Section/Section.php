<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Section;

use Closure;
use Juksgraphic\DashboardAnalytic\Contracts\SectionInterface;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\DTO\Table;
use Juksgraphic\DashboardAnalytic\Exceptions\InvalidSectionResultException;

/**
 * Fluent, immutable section definition:
 *
 *   Section::make('orders_chart')
 *       ->tags(['orders'])
 *       ->ttl(600)
 *       ->using(fn (AnalyticsQuery $q) => new Chart(...));
 */
final class Section implements SectionInterface
{

    private ?Closure $resolver = null;

    /** @param string[] $tags */

    /**
     * Summary of __construct
     * @param string $id
     * @param array $tags
     * @param mixed $ttl
     */
    private function __construct(
        private string $id,
        private array $tags = [],
        private ?int $ttl = null,
    ) {}

    /**
     * Summary of make
     * @param string $id
     * @return Section
     */
    public static function make(string $id): self
    {
        return new self($id);
    }


    /**
     * May return StatItem|Chart|Table|AnalyticsResponse or an array of these.
     * @param callable(AnalyticsQuery): mixed $resolver
     */
    public function using(callable $resolver): self
    {
        $clone = clone $this;
        $clone->resolver = Closure::fromCallable($resolver);

        return $clone;
    }
    
    /**
     * Summary of id
     * @return string
     */
    public function id(): string
    {
        return $this->id;
    }


    /**
     * Summary of withTags
     * @param array $tags
     * @return Section
     */
    public function withTags(array $tags): self
    {
        $clone = clone $this;
        $clone->tags = array_values(array_unique($tags));

        return $clone;
    }

    /**
     * Summary of withTtl
     * @param mixed $seconds
     * @return Section
     */
    public function withTtl(?int $seconds): self
    {
        $clone = clone $this;
        $clone->ttl = $seconds;

        return $clone;
    }

    /**
     * Summary of tags
     * @return array
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Summary of ttl
     * @return int
     */
    public function ttl(): ?int
    {
        return $this->ttl;
    }

    /**
     * Summary of load
     * @param AnalyticsQuery $query
     * @return AnalyticsResponse
     */
    public function load(AnalyticsQuery $query): AnalyticsResponse
    {
        if ($this->resolver === null) {
            throw InvalidSectionResultException::noResolver($this->id);
        }

        return $this->normalize(($this->resolver)($query));
    }

    /**
     * Summary of normalize
     * @param mixed $result
     * @return AnalyticsResponse|null
     */
    private function normalize(mixed $result): AnalyticsResponse
    {
        return match (true) {
            $result instanceof AnalyticsResponse => $result,
            $result instanceof StatItem => new AnalyticsResponse(stats: [$result]),
            $result instanceof Chart => new AnalyticsResponse(charts: [$result]),
            $result instanceof Table => new AnalyticsResponse(tables: [$result]),
            is_array($result) => array_reduce(
                $result,
                fn(AnalyticsResponse $carry, mixed $item) => $carry->merge($this->normalize($item)),
                new AnalyticsResponse(),
            ),
            default => throw InvalidSectionResultException::invalidType($this->id, $result),
        };
    }
}
