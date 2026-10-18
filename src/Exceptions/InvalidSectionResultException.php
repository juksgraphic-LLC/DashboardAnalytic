<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Exceptions;

final class InvalidSectionResultException extends AnalyticsException
{
    /**
     * No Resolver for section 
     * @param string $sectionId
     * @return InvalidSectionResultException
     */
    public static function noResolver(string $sectionId): self
    {
        return new self("Section [{$sectionId}] has no resolver. Call using() first.");
    }

    /**
     * Invalid type for section
     * @param string $sectionId
     * @param mixed $given
     * @return InvalidSectionResultException
     */
    public static function invalidType(string $sectionId, mixed $given): self
    {
        $type = get_debug_type($given);

        return new self(
            "Section [{$sectionId}] returned an unsupported type [{$type}]. "
                . 'Expected StatItem, Chart, Table, AnalyticsResponse or an array of these.'
        );
    }
}
