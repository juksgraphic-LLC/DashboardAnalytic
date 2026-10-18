<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Contracts;

interface ProviderInterface
{
    /** Unique provider identifier (e.g. 'shop', 'google_analytics'). */
    public function key(): string;

    /** Whether the provider can be used right now (credentials present, DB reachable...). */
    public function available(): bool;

    /**
     * The sections this provider exposes. Each one is cached independently.
     *
     * @return SectionInterface[]
     */
    public function sections(): array;
}