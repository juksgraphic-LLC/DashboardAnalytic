<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Contracts;

use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;

interface SectionInterface
{
    /** Identifier, unique inside its provider (e.g. 'orders_chart'). */
    public function id(): string;

    /**
     * Business tags used for cache invalidation (e.g. ['orders', 'payments']).
     *
     * @return string[]
     */
    public function tags(): array;

    /** Cache lifetime in seconds. Null = use the manager's default TTL. */
    public function ttl(): ?int;

    /** Produce the widgets of this section. */
    public function load(AnalyticsQuery $query): AnalyticsResponse;
}
