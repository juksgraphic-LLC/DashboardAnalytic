<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\DTO;

use JsonSerializable;

final readonly class Chart implements JsonSerializable
{
    /**
     * @param string $id Chart identifier (e.g. 'orders')
     * @param string $type 'line', 'bar', 'doughnut', 'candlestick'...
     * @param array<int, string> $labels
     * @param array<int, array<string, mixed>> $datasets Each dataset uses the key 'label' (not 'name')
     * @param array<string, mixed> $options Free options passed to the UI library
     */
    public function __construct(
        public string $id,
        public string $type,
        public array $labels,
        public array $datasets,
        public array $options = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:       $data['id'],
            type:     $data['type'],
            labels:   $data['labels']   ?? [],
            datasets: $data['datasets'] ?? [],
            options:  $data['options']  ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'type'     => $this->type,
            'labels'   => $this->labels,
            'datasets' => $this->datasets,
            'options'  => $this->options,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
