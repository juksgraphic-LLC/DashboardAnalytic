<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Providers;

use Closure;
use Juksgraphic\DashboardAnalytic\Contracts\SectionInterface;

/**
 * Build a provider without writing a class:
 *
 *   new CallableProvider('shop', [
 *       Section::make('orders')->withTags(['orders'])->using(fn () => ...),
 *   ], available: fn () => $db->isConnected());
 */
final class CallableProvider extends AbstractProvider
{
    private ?Closure $availability;

    /**
     * @param SectionInterface[] $sections
     * @param (callable(): bool)|null $available Null = always available
     */
    public function __construct(
        private string $key,
        private array $sections,
        ?callable $available = null,
    ) {
        $this->availability = $available === null ? null : Closure::fromCallable($available);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function available(): bool
    {
        return $this->availability === null || (bool) ($this->availability)();
    }

    public function sections(): array
    {
        return $this->sections;
    }
}
