<?php

use Juksgraphic\DashboardAnalytic\AnalyticsManager;
use Juksgraphic\DashboardAnalytic\Cache\SectionCache;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\Example\DemoShopProvider;
use Juksgraphic\DashboardAnalytic\Providers\AbstractProvider;
use Juksgraphic\DashboardAnalytic\Providers\CallableProvider;
use Juksgraphic\DashboardAnalytic\Section\Section;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

function providerTestManager(): AnalyticsManager
{
    return new AnalyticsManager(new SectionCache(new TagAwareAdapter(new ArrayAdapter())));
}

it('is available by default when extending AbstractProvider', function () {
    $provider = new class () extends AbstractProvider {
        public function key(): string
        {
            return 'x';
        }

        public function sections(): array
        {
            return [];
        }
    };

    expect($provider->available())->toBeTrue();
});

it('lets a subclass override availability', function () {
    $provider = new class () extends AbstractProvider {
        public function key(): string
        {
            return 'x';
        }

        public function available(): bool
        {
            return false;
        }

        public function sections(): array
        {
            return [];
        }
    };

    $response = providerTestManager()->register($provider)->get('x');

    expect($response->errors)->toHaveKey('x');
});

it('builds a provider from callables', function () {
    $provider = new CallableProvider('quick', [
        Section::make('c')->using(fn () => new Chart('c', 'bar', [], [])),
    ]);

    $response = providerTestManager()->register($provider)->get('quick');

    expect($provider->key())->toBe('quick')
        ->and($response->charts)->toHaveCount(1);
});

it('respects the availability callback of CallableProvider', function () {
    $provider = new CallableProvider('quick', [], available: fn () => false);

    expect($provider->available())->toBeFalse();
});

it('serves the demo provider through the manager', function () {
    $response = providerTestManager()
        ->register(new DemoShopProvider())
        ->get('demo_shop');

    expect($response->stats)->toHaveCount(3)
        ->and($response->charts)->toHaveCount(1)
        ->and($response->tables)->toHaveCount(1)
        ->and($response->errors)->toBeEmpty();
});

it('passes the query limit to the demo provider', function () {
    $response = providerTestManager()
        ->register(new DemoShopProvider())
        ->get('demo_shop', new AnalyticsQuery(limit: 2), only: ['top_products']);

    expect($response->tables[0]->rows)->toHaveCount(2);
});

it('uses a different cache entry per query', function () {
    $manager = providerTestManager()->register(new DemoShopProvider());

    $two = $manager->get('demo_shop', new AnalyticsQuery(limit: 2), only: ['top_products']);
    $four = $manager->get('demo_shop', new AnalyticsQuery(limit: 4), only: ['top_products']);

    expect($two->tables[0]->rows)->toHaveCount(2)
        ->and($four->tables[0]->rows)->toHaveCount(4);
});
