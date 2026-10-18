<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic;

use Juksgraphic\DashboardAnalytic\Cache\SectionCache;
use Juksgraphic\DashboardAnalytic\Contracts\ProviderInterface;
use Juksgraphic\DashboardAnalytic\Contracts\SectionInterface;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;
use Juksgraphic\DashboardAnalytic\Exceptions\ProviderFailedException;
use Juksgraphic\DashboardAnalytic\Exceptions\ProviderNotFoundException;
use Juksgraphic\DashboardAnalytic\Exceptions\SectionNotFoundException;
use Psr\Log\LoggerInterface;
use Throwable;

final class AnalyticsManager
{
    /** @var array<string, ProviderInterface> */
    private array $providers = [];

    private bool $bypassAll = false;

    /** @var string[] */
    private array $bypassSections = [];

    /** @var string[] */
    private array $bypassTags = [];

    public function __construct(
        private SectionCache $cache,
        private ?LoggerInterface $logger = null,
    ) {}

    

    /**
     * Summary of register
     * @param ProviderInterface $provider
     * @return AnalyticsManager
     */
    public function register(ProviderInterface $provider): self
    {
        $this->providers[$provider->key()] = $provider;

        return $this;
    }


    /**
     * Summary of registers
     * @param ProviderInterface[] $providers
     * @return AnalyticsManager
     */
    public function registers(array $providers): self
    {
        foreach ($providers as $provider) {
            $this->register($provider);
        }

        return $this;
    }

   

    /**
     * Load one provider. A failing section does not break the others:
     * its error is reported in the response under "provider.section".
     *
     * @param string[]|null $only Restrict to these section ids
     *
     * @throws ProviderNotFoundException
     * @throws SectionNotFoundException
     */
    public function get(string $key, ?AnalyticsQuery $query = null, ?array $only = null): AnalyticsResponse
    {
        $provider = $this->providers[$key] ?? throw ProviderNotFoundException::forKey($key);

        if (! $provider->available()) {
            return AnalyticsResponse::withErrorOnly($key, "Provider [{$key}] is unavailable.");
        }

        $query ??= new AnalyticsQuery();
        $response = new AnalyticsResponse();

        foreach ($this->selectSections($key, $provider, $only) as $section) {
            try {
                $response = $response->merge(
                    $this->cache->remember($key, $section, $query, $this->shouldBypass($section))
                );
            } catch (Throwable $e) {
                $failure = ProviderFailedException::forKey(
                    $key,
                    sprintf('section [%s]: %s', $section->id(), $e->getMessage()),
                    $e,
                );

                $this->logger?->error($failure->getMessage(), ['exception' => $e]);
                $response = $response->withError($key . '.' . $section->id(), $failure->getMessage());
            }
        }

        return $response;
    }

    /**
     * Merge several providers into one response. Never throws:
     * unknown or failing providers end up in "errors".
     *
     * @param string[] $keys
     */
    public function dashboard(array $keys, ?AnalyticsQuery $query = null): AnalyticsResponse
    {
        $dashboard = new AnalyticsResponse();

        foreach ($keys as $key) {
            try {
                $dashboard = $dashboard->merge($this->get($key, $query));
            } catch (Throwable $e) {
                $this->logger?->error($e->getMessage(), ['exception' => $e]);
                $dashboard = $dashboard->withError($key, $e->getMessage());
            }
        }

        return $dashboard;
    }

   

    /**
     *   Return a copy of the manager that ignores (and refreshes) the cache.
     *   $analytics->withoutCache()->dashboard([...]);                       // everything
     *   $analytics->withoutCache(sections: ['orders_chart'])->get('shop');  // by section id
     *   $analytics->withoutCache(tags: ['orders'])->dashboard([...]);       // by business tag
     *
     * @param string[] $sections
     * @param string[] $tags
     */
    public function withoutCache(array $sections = [], array $tags = []): self
    {
        $clone = clone $this;

        if ($sections === [] && $tags === []) {
            $clone->bypassAll = true;

            return $clone;
        }

        $clone->bypassSections = [...$this->bypassSections, ...$sections];
        $clone->bypassTags = [...$this->bypassTags, ...$tags];

        return $clone;
    }

    
    /**
     * Forget section invaldation
     * @param string $providerKey
     * @param string $sectionId
     * @return void
     */
    public function forget(string $providerKey, string $sectionId): void
    {
        $this->cache->forgetSection($providerKey, $sectionId);
    }

    /**
     * Summary of forgetTag
     * @param string $tag
     * @return void
     */
    public function forgetTag(string $tag): void
    {
        $this->cache->forgetTag($tag);
    }

    /**
     * Summary of forgetProvider
     * @param string $providerKey
     * @return void
     */
    public function forgetProvider(string $providerKey): void
    {
        $this->cache->forgetProvider($providerKey);
    }

    /**
     * Summary of flush
     * @return void
     */
    public function flush(): void
    {
        $this->cache->flush();
    }


    /**
     * Summary of selectSections
     * @param string $key
     * @param ProviderInterface $provider
     * @param mixed $only
     * @return array
     */
    private function selectSections(string $key, ProviderInterface $provider, ?array $only): array
    {
        $all = [];
        foreach ($provider->sections() as $section) {
            $all[$section->id()] = $section;
        }

        if ($only === null) {
            return array_values($all);
        }

        $selected = [];
        foreach ($only as $id) {
            $selected[] = $all[$id] ?? throw SectionNotFoundException::forKey($key, $id);
        }

        return $selected;
    }

    /**
     * Summary of shouldBypass
     * @param SectionInterface $section
     * @return bool
     */
    private function shouldBypass(SectionInterface $section): bool
    {
        return $this->bypassAll
            || in_array($section->id(), $this->bypassSections, true)
            || array_intersect($section->tags(), $this->bypassTags) !== [];
    }
}