<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\DTO;

/**
 * Parameters of an analytics request. Replaces the free-form array $params.
 */
final readonly class AnalyticsQuery
{
    /**
     * @param string|null $from Start of period (ISO date or provider-specific, e.g. '30daysAgo')
     * @param string|null $to End of period
     * @param array<string, mixed> $filters
     * @param array<string, mixed> $options Provider-specific extras
     */
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
        public array $filters = [],
        public ?int $limit = null,
        public array $options = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            from:    $data['from'] ?? null,
            to:      $data['to'] ?? null,
            filters: $data['filters'] ?? [],
            limit:   $data['limit'] ?? null,
            options: $data['options'] ?? [],
        );
    }

    /**
     * Summary of toArray
     * @return array
     * {
     *  filters: array,
     *  from: string|null,
     *  limit: int|null,
     *  options: array,
     *  to: string|null
     *  }
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'filters' => $this->filters,
            'limit' => $this->limit,
            'options' => $this->options,
        ];
    }

    /**
     * Stable hash, independent of array key order. Safe to use in cache keys.
     * @return string
     */
    public function hash(): string
    {
        return md5((string) json_encode(self::normalize($this->toArray())));
    }

    /**
     * Summary of normalize
     * @param mixed $value
     * @return mixed
     */
    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(self::normalize(...), $value);

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
