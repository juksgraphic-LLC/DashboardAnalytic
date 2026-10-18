<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Exceptions;

final class ProviderNotFoundException extends AnalyticsException
{
    /**
     * Summary of forKey
     * @param string $key
     * @return ProviderNotFoundException
     */
    public static function forKey(string $key): self
    {
        return new self("Analytics provider [{$key}] not found.");
    }
}
