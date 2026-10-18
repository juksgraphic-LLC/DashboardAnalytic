# Dashboard Analytic

A framework-agnostic PHP library that aggregates data from any source into unified dashboard widgets (stats, charts, tables), with **granular, tag-based caching**.

You write **providers** (database, Google Analytics, a REST API, anything). The library gives you:

- a small contract for providers and sections,
- immutable DTOs ready to be sent as JSON to your front-end,
- a manager that merges several providers into one dashboard and isolates failures,
- per-section caching on top of Symfony Cache, with invalidation by section, tag, provider, or globally.

## Requirements

- PHP 8.2+
- `symfony/cache` and `symfony/cache-contracts`
- `psr/log` (optional logger)

## Installation

```bash
composer require juksgraphic/dashboardanalytic
```

## Quick start

```php
use Juksgraphic\DashboardAnalytic\AnalyticsManager;
use Juksgraphic\DashboardAnalytic\Cache\SectionCache;
use Juksgraphic\DashboardAnalytic\Example\DemoShopProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

// 1. A tag-aware cache. The adapter is YOUR choice (Filesystem, Redis, ...).
$store = new TagAwareAdapter(new FilesystemAdapter('analytics', 0, __DIR__ . '/storage/cache'));

// 2. The manager
$analytics = new AnalyticsManager(new SectionCache($store, defaultTtl: 3600));

// 3. Register providers
$analytics->register(new DemoShopProvider());

// 4. Get dashboard data
$data = $analytics->dashboard(['demo_shop'])->toArray();
```

`$data` has this shape:

```php
[
    'stats'  => [ ['id' => 'orders', 'label' => 'Orders', 'value' => 1284, 'change' => 12.4, 'format' => 'number', 'icon' => 'orders', 'meta' => []], ... ],
    'charts' => [ ['id' => 'orders', 'type' => 'line', 'labels' => [...], 'datasets' => [['label' => 'Orders', 'data' => [...]]], 'options' => []], ... ],
    'tables' => [ ['id' => 'top_products', 'columns' => [...], 'rows' => [...]], ... ],
    'errors' => [],
]
```

## Core concepts

| Concept | Description |
|---|---|
| **Provider** | A data source. Has a unique `key()`, an `available()` check, and a list of `sections()`. |
| **Section** | A cacheable unit of work. Has an `id`, optional `tags`, an optional TTL, and a resolver that returns widgets. |
| **Widget** | A `StatItem`, `Chart` or `Table` DTO. |
| **Response** | An immutable `AnalyticsResponse` holding stats, charts, tables and errors. |
| **Manager** | Registers providers, loads sections through the cache, merges results, and handles invalidation. |

## Writing a provider

Extend `AbstractProvider`. You only have to implement `key()` and `sections()`.

```php
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\Provider\AbstractProvider;

final class ShopProvider extends AbstractProvider
{
    public function __construct(private OrderStats $stats) {}

    public function key(): string
    {
        return 'shop';
    }

    // Optional: defaults to true
    public function available(): bool
    {
        return $this->stats->isReachable();
    }

    public function sections(): array
    {
        return [
            $this->section('kpis')
                ->withTags(['orders'])
                ->withTtl(300)
                ->using(fn (AnalyticsQuery $q) => [
                    new StatItem('orders', 'Orders', $this->stats->count(), $this->stats->monthlyChange(), 'number'),
                    new StatItem('revenue', 'Revenue', $this->stats->revenue(), null, 'currency'),
                ]),

            $this->section('orders_chart')
                ->withTags(['orders'])
                ->withTtl(600)
                ->using(fn (AnalyticsQuery $q) => new Chart(
                    id: 'orders',
                    type: 'line',
                    labels: $this->stats->months(),
                    datasets: [['label' => 'Orders', 'data' => $this->stats->perMonth()]],
                )),
        ];
    }
}
```

A section resolver may return a `StatItem`, `Chart`, `Table`, an `AnalyticsResponse`, or an **array of any of these**. The library normalizes everything into an `AnalyticsResponse`.

### Providers without a class

```php
use Juksgraphic\DashboardAnalytic\Provider\CallableProvider;
use Juksgraphic\DashboardAnalytic\Section\Section;

$analytics->register(new CallableProvider(
    key: 'quick',
    sections: [
        Section::make('visitors')->withTtl(60)->using(fn () => new StatItem('visitors', 'Visitors', 42)),
    ],
    available: fn () => true, // optional
));
```

### Data conventions

- Keep DTOs **raw**: put numbers in `value` and `change` (e.g. `16.24`, not `"+16.24%"`) and use `format` (`number`, `percent`, `duration`, `currency`, ...) so the front-end decides how to display them.
- Chart datasets use the key `label` (Chart.js style).
- Every widget has a stable `id`.
- Put UI-specific extras in `Chart::$options` or `StatItem::$meta`.

## Querying

Use `AnalyticsQuery` instead of free-form arrays:

```php
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;

$query = new AnalyticsQuery(
    from: '2026-01-01',
    to: '2026-01-31',
    filters: ['country' => 'HT'],
    limit: 10,
);

$response = $analytics->get('shop', $query);
```

Each distinct query gets its own cache entry (the hash ignores array key order).

## Loading data

```php
// One provider
$analytics->get('shop');

// Only some sections of a provider
$analytics->get('shop', only: ['orders_chart']);

// Several providers merged into one response
$analytics->dashboard(['shop', 'google_analytics'], $query);
```

## Finding widgets

`AnalyticsResponse` gives you typed accessors, so you don't need your own lookup helpers:

```php
$response = $analytics->get('shop');

$response->chart('orders');     // ?Chart
$response->table('top');        // ?Table
$response->stat('revenue');     // ?StatItem

$response->hasChart('orders');  // bool
```

Ids are unique **per widget type**: a `StatItem` and a `Chart` can both be called `orders`. If several providers are merged into one dashboard, prefix your ids (e.g. `shop.orders`) to avoid collisions; the first match wins.

**Failures are isolated.**

- If a section throws, the other sections still load. The error appears in `errors` under `provider.section`.
- If a provider is unavailable or unknown, `dashboard()` reports it in `errors` and continues.
- `get()` throws `ProviderNotFoundException` for an unknown key, and `SectionNotFoundException` for an unknown id in `only`.

## Cache

Every section is cached **independently** and tagged automatically with:

- a global tag,
- its provider,
- its section id,
- your business tags (the ones you set with `withTags()`).

### Invalidation (persistent)

| Method | Effect |
|---|---|
| `$analytics->forget('shop', 'orders_chart')` | One section |
| `$analytics->forgetTag('orders')` | Every section tagged `orders`, across all providers |
| `$analytics->forgetProvider('shop')` | Every section of a provider |
| `$analytics->flush()` | Everything |

Typical usage: hook invalidation to your business events.

```php
// When an order is created
$analytics->forgetTag('orders');
```

### Bypass (one call only)

`withoutCache()` returns a copy of the manager that recomputes the data **right now** and refreshes the cache with the fresh value. The original manager is not modified.

```php
$analytics->withoutCache()->dashboard(['shop']);                        // everything
$analytics->withoutCache(sections: ['orders_chart'])->get('shop');      // by section id
$analytics->withoutCache(tags: ['orders'])->dashboard(['shop']);        // by business tag
```

Use `forgetTag()` when reacting to an event, and `withoutCache()` when a user explicitly asks for fresh data (a "Refresh" button).

### TTL

Set it per section with `withTtl()`. Sections without a TTL use the default given to `SectionCache` (3600 seconds).

### Choosing a cache backend

The library depends only on `Symfony\Contracts\Cache\TagAwareCacheInterface`. Wrap any Symfony adapter in a `TagAwareAdapter`:

```php
new TagAwareAdapter(new RedisAdapter($redis));
```

The cache stores plain arrays, not objects, and keys include a schema version, so changing a DTO in a future release will not break existing entries.

## Logging

Pass any PSR-3 logger as the second constructor argument. Section and provider failures are logged instead of being silently swallowed.

```php
$analytics = new AnalyticsManager($sectionCache, $logger);
```

## Framework integration example (Flight)

The library has no framework dependency. Build the manager once and share it:

```php
Flight::register('analytics', AnalyticsManager::class, [
    new SectionCache(new TagAwareAdapter(new FilesystemAdapter('analytics', 0, __DIR__ . '/storage/cache'))),
    $logger,
], function (AnalyticsManager $manager) {
    $manager->registers([new ShopProvider(new OrderStats())]);
});

Flight::route('GET /api/dashboard', function () {
    Flight::json(Flight::analytics()->dashboard(['shop'])->toArray());
});

Flight::route('POST /api/dashboard/refresh/orders', function () {
    Flight::json(Flight::analytics()->withoutCache(tags: ['orders'])->dashboard(['shop'])->toArray());
});
```

## Exceptions

All exceptions extend `Juksgraphic\DashboardAnalytic\Exceptions\AnalyticsException`.

| Exception | Thrown when |
|---|---|
| `ProviderNotFoundException` | `get()` is called with an unregistered key |
| `SectionNotFoundException` | `only` contains an unknown section id |
| `ProviderFailedException` | Wraps a section failure (used in `errors` and logs) |
| `InvalidSectionResultException` | A section has no resolver, or returns an unsupported type |

## Testing

```bash
composer test
```

## Code style

```bash
composer format
```

## Changelog

See [CHANGELOG](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).