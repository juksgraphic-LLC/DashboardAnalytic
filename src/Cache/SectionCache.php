<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Cache;

use Juksgraphic\DashboardAnalytic\Contracts\SectionInterface;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Caches each section independently and tags it so it can be invalidated
 * by section, by business tag, by provider, or globally.
 *
 * The cache stores plain arrays (not objects): safe across class changes.
 */
final class SectionCache
{
    /**
     * Increment when the DTO structure changes, to ignore old cache entries.
     * @var string
     */
    private const SCHEMA = 'v1';

    /**
     * Summary of GLOBAL_TAG
     * @var string
     */
    private const GLOBAL_TAG = 'analytics';

    /**
     * Summary of __construct
     * @param TagAwareCacheInterface $cache
     * @param int $defaultTtl
     */
    public function __construct(
        private TagAwareCacheInterface $cache,
        private int $defaultTtl = 3600,
    ) {
    }

    /**
     * Return the cached section, or compute and store it.
     *
     * @param bool $fresh Drop the existing entry first, so data is recomputed
     * and the cache is refreshed with the new value.
     */
    public function remember(
        string $providerKey,
        SectionInterface $section,
        AnalyticsQuery $query,
        bool $fresh = false,
    ): AnalyticsResponse {

        $key = $this->cacheKey($providerKey, $section->id(), $query);

        if ($fresh) {
            $this->cache->delete($key);
        }

        $data = $this->cache->get(
            $key,
            function (ItemInterface $item) use ($providerKey, $section, $query): array {
                $item->tag($this->tagsFor($providerKey, $section));
                $item->expiresAfter($section->ttl() ?? $this->defaultTtl);

                return $section->load($query)->toArray();
            }
        );

        return AnalyticsResponse::fromArray($data);
    }

    /**
     * Summary of forgetSection
     * @param string $providerKey
     * @param string $sectionId
     * @return void
     */
    public function forgetSection(string $providerKey, string $sectionId): void
    {
        $this->cache->invalidateTags([$this->sectionTag($providerKey, $sectionId)]);
    }

    /**
     * Summary of forgetTag
     * @param string $tag
     * @return void
     */
    public function forgetTag(string $tag): void
    {
        $this->cache->invalidateTags([$this->businessTag($tag)]);
    }

    /**
     * Summary of forgetProvider
     * @param string $providerKey
     * @return void
     */
    public function forgetProvider(string $providerKey): void
    {
        $this->cache->invalidateTags([$this->providerTag($providerKey)]);
    }

    /**
     * Summary of flush
     * @return void
     */
    public function flush(): void
    {
        $this->cache->invalidateTags([self::GLOBAL_TAG]);
    }

    /**
     * Summary of tagsFor
     * @param string $providerKey
     * @param SectionInterface $section
     * @return array<mixed|string>
     */
    private function tagsFor(string $providerKey, SectionInterface $section): array
    {
        return [
            self::GLOBAL_TAG,
            $this->providerTag($providerKey),
            $this->sectionTag($providerKey, $section->id()),
            ...array_map($this->businessTag(...), $section->tags()),
        ];
    }

    /**
     * Summary of cacheKey
     * @param string $providerKey
     * @param string $sectionId
     * @param AnalyticsQuery $query
     * @return string
     */
    private function cacheKey(string $providerKey, string $sectionId, AnalyticsQuery $query): string
    {
        return sprintf(
            'analytics.%s.%s.%s.%s',
            self::SCHEMA,
            self::clean($providerKey),
            self::clean($sectionId),
            $query->hash(),
        );
    }

    /**
     * Summary of providerTag
     * @param string $providerKey
     * @return string
     */
    private function providerTag(string $providerKey): string
    {
        return 'provider.' . self::clean($providerKey);
    }

    /**
     * Summary of sectionTag
     * @param string $providerKey
     * @param string $sectionId
     * @return string
     */
    private function sectionTag(string $providerKey, string $sectionId): string
    {
        return 'section.' . self::clean($providerKey) . '.' . self::clean($sectionId);
    }

    /**
     * Summary of businessTag
     * @param string $tag
     * @return string
     */
    private function businessTag(string $tag): string
    {
        return 'tag.' . self::clean($tag);
    }

    /**
     * Symfony Cache forbids the characters {}()/\@: in keys and tags.
     * @param string $value
     * @return string
     */
    private static function clean(string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_.\-]/', '_', $value);
    }
}
