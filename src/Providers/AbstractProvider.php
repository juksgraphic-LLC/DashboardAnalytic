<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Providers;

use Juksgraphic\DashboardAnalytic\Contracts\ProviderInterface;
use Juksgraphic\DashboardAnalytic\Contracts\SectionInterface;
use Juksgraphic\DashboardAnalytic\Section\Section;

abstract class AbstractProvider implements ProviderInterface
{
    /** Unique provider identifier (e.g. 'shop'). */
    abstract public function key(): string;

    /**
     * The sections exposed by this provider.
     *
     * @return SectionInterface[]
     */
    abstract public function sections(): array;

    /** Override to check credentials, DB connection, etc. */
    public function available(): bool
    {
        return true;
    }

    /** Shortcut so that subclasses don't need to import Section. */
    protected function section(string $id): Section
    {
        return Section::make($id);
    }
}
