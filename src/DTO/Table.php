<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\DTO;

use JsonSerializable;

final readonly class Table implements JsonSerializable
{
    /**
     * @param string $id Table identifier
     * @param array<int, array{key: string, label: string}> $columns
     * @param array<int, array<string, mixed>> $rows
     */
    public function __construct(
        public string $id,
        public array $columns,
        public array $rows,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id:      $data['id'],
            columns: $data['columns'] ?? [],
            rows:    $data['rows']    ?? [],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'columns' => $this->columns,
            'rows' => $this->rows,
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
