<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\DTO;

use JsonSerializable;

/**
 * Immutable container of dashboard widgets. Every "with*" / merge() returns a new instance.
 */
final readonly class AnalyticsResponse implements JsonSerializable
{
    /**
     * @param StatItem[] $stats
     * @param Chart[] $charts
     * @param Table[] $tables
     * @param array<string, string> $errors Error messages indexed by provider/section key
     */
    public function __construct(
        public array $stats = [],
        public array $charts = [],
        public array $tables = [],
        public array $errors = [],
    ) {}

    /**
     * Summary of withErrorOnly
     * @param string $key
     * @param string $message
     * @return AnalyticsResponse
     */
    public static function withErrorOnly(string $key, string $message): self
    {
        return new self(errors: [$key => $message]);
    }

    /**
     * Summary of merge
     * @param AnalyticsResponse $other
     * @return AnalyticsResponse
     */
    public function merge(self $other): self
    {
        return new self(
            stats: [...$this->stats, ...$other->stats],
            charts: [...$this->charts, ...$other->charts],
            tables: [...$this->tables, ...$other->tables],
            errors: [...$this->errors, ...$other->errors],
        );
    }

    /**
     * Summary of withError
     * @param string $key
     * @param string $message
     * @return AnalyticsResponse
     */
    public function withError(string $key, string $message): self
    {
        return new self($this->stats, $this->charts, $this->tables, [...$this->errors, $key => $message]);
    }

    /**
     * Summary of isEmpty
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->stats === [] && $this->charts === [] && $this->tables === [];
    }

    /**
     * Summary of hasErrors
     * @return bool
     */
    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * Summary of fromArray
     * @param array<string, mixed> $data
     * @return AnalyticsResponse
     */
    public static function fromArray(array $data): self
    {
        return new self(
            stats: array_map(StatItem::fromArray(...), $data['stats'] ?? []),
            charts: array_map(Chart::fromArray(...), $data['charts'] ?? []),
            tables: array_map(Table::fromArray(...), $data['tables'] ?? []),
            errors: $data['errors'] ?? [],
        );
    }


    /**
     * Summary of toArray
     * @return array{charts: array, errors: string[], stats: array, tables: array}
     */
    public function toArray(): array
    {
        return [
            'stats'  => array_map(static  fn(StatItem $i) => $i->toArray(), $this->stats),
            'charts' => array_map(static fn(Chart $i)    => $i->toArray(), $this->charts),
            'tables' => array_map(static fn(Table $i)    => $i->toArray(), $this->tables),
            'errors' => $this->errors,
        ];
    }


    /**
     * Summary of jsonSerialize
     * @return array{charts: array, errors: string[], stats: array, tables: array}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Summary of stat
     * @param string $id
     * @return object|null
     */
    public function stat(string $id): ?StatItem
    {
        return self::firstWithId($this->stats, $id);
    }

    /**
     * Summary of chart
     * @param string $id
     * @return object|null
     */
    public function chart(string $id): ?Chart
    {
        return self::firstWithId($this->charts, $id);
    }

    /**
     * Summary of table
     * @param string $id
     * @return object|null
     */
    public function table(string $id): ?Table
    {
        return self::firstWithId($this->tables, $id);
    }

    /**
     * Summary of hasStat
     * @param string $id
     * @return bool
     */
    public function hasStat(string $id): bool
    {
        return $this->stat($id) !== null;
    }

    /**
     * Summary of hasChart
     * @param string $id
     * @return bool
     */
    public function hasChart(string $id): bool
    {
        return $this->chart($id) !== null;
    }

    /**
     * Summary of hasTable
     * @param string $id
     * @return bool
     */
    public function hasTable(string $id): bool
    {
        return $this->table($id) !== null;
    }

    /**
     * @template T of StatItem|Chart|Table
     * @param T[] $items
     * @return T|null
     */
    private static function firstWithId(array $items, string $id): ?object
    {
        foreach ($items as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }

        return null;
    }
}
