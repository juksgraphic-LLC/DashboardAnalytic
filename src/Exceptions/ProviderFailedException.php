<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Exceptions;

use Throwable;

final class ProviderFailedException extends AnalyticsException
{
    /**
     * Summary of forKey
     * @param string $key
     * @param string $reason
     * @param mixed $previous
     * @return ProviderFailedException
     */
    public static function forKey(string $key, string $reason, ?Throwable $previous = null): self
    {
        return new self("Analytics provider [{$key}] failed: {$reason}", 0, $previous);
    }
}
