<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\DTO;

use JsonSerializable;

/**
 * A KPI card. Holds raw data: formatting is the front-end's job (see $format).
 */
final readonly class StatItem implements JsonSerializable
{
    /**
     * @param string $id Stable identifier (e.g. 'users', 'revenue')
     * @param int|float|string $value Raw value
     * @param float|null $change Variation in percent (e.g. 16.24, -3.96)
     * @param string|null $format Hint for the UI: 'number' | 'percent' | 'duration' | 'currency'...
     * @param array<string, mixed> $meta Free extra data
     */
    public function __construct(
        public string $id,
        public string $label,
        public int|float|string $value,
        public ?float $change = null,
        public ?string $format = null,
        public ?string $icon = null,
        public array $meta = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:     $data['id'],
            label:  $data['label'],
            value:  $data['value'],
            change: isset($data['change']) ? (float) $data['change'] : null,
            format: $data['format'] ?? null,
            icon:   $data['icon'] ?? null,
            meta:   $data['meta'] ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'value' => $this->value,
            'change' => $this->change,
            'format' => $this->format,
            'icon' => $this->icon,
            'meta' => $this->meta,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
