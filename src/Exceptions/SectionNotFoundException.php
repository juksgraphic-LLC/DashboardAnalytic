<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Exceptions;

final class SectionNotFoundException extends AnalyticsException
{
    /**
     * Summary of forKey
     * @param string $provider
     * @param string $section
     * @return SectionNotFoundException
     */
    public static function forKey(string $provider, string $section): self
    {
        return new self("Section [{$section}] not found in provider [{$provider}].");
    }
}
