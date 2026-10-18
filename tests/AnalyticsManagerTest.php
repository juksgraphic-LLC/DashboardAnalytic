<?php

use Juksgraphic\DashboardAnalytic\AnalyticsManager;
use Juksgraphic\DashboardAnalytic\Cache\SectionCache;
use Juksgraphic\DashboardAnalytic\Contracts\ProviderInterface;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\Table;
use Juksgraphic\DashboardAnalytic\Exceptions\ProviderNotFoundException;
use Juksgraphic\DashboardAnalytic\Section\Section;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

function newManager(): AnalyticsManager
{
    return new AnalyticsManager(new SectionCache(new TagAwareAdapter(new ArrayAdapter())));
}

function newCounter(): stdClass
{
    return (object) ['orders' => 0, 'products' => 0];
}

function shopProvider(stdClass $counter): ProviderInterface
{
    return new class ($counter) implements ProviderInterface {
        public function __construct(private stdClass $counter)
        {
        }

        public function key(): string
        {
            return 'shop';
        }

        public function available(): bool
        {
            return true;
        }

        public function sections(): array
        {
            return [
                Section::make('orders_chart')->withTags(['orders'])->using(function () {
                    $this->counter->orders++;

                    return new Chart('orders', 'line', ['Jan'], [['label' => 'Orders', 'data' => [1]]]);
                }),
                Section::make('top_products')->withTags(['products'])->using(function () {
                    $this->counter->products++;

                    return new Table('top_products', [['key' => 'name', 'label' => 'Name']], [['name' => 'A']]);
                }),
            ];
        }
    };
}

it('caches each section after the first call', function () {
    $counter = newCounter();
    $manager = newManager()->register(shopProvider($counter));

    $manager->get('shop');
    $response = $manager->get('shop');

    expect($counter->orders)->toBe(1)
        ->and($counter->products)->toBe(1)
        ->and($response->charts)->toHaveCount(1)
        ->and($response->tables)->toHaveCount(1);
});

it('invalidates only the sections linked to a tag', function () {
    $counter = newCounter();
    $manager = newManager()->register(shopProvider($counter));

    $manager->get('shop');
    $manager->forgetTag('orders');
    $manager->get('shop');

    expect($counter->orders)->toBe(2)
        ->and($counter->products)->toBe(1);
});

it('invalidates a single section by id', function () {
    $counter = newCounter();
    $manager = newManager()->register(shopProvider($counter));

    $manager->get('shop');
    $manager->forget('shop', 'top_products');
    $manager->get('shop');

    expect($counter->products)->toBe(2)
        ->and($counter->orders)->toBe(1);
});

it('bypasses the cache for a tag on one call only', function () {
    $counter = newCounter();
    $manager = newManager()->register(shopProvider($counter));

    $manager->get('shop');
    $manager->withoutCache(tags: ['orders'])->get('shop');
    $manager->get('shop');

    expect($counter->orders)->toBe(2)   // refreshed once, then served from cache
        ->and($counter->products)->toBe(1);
});

it('loads only the requested sections', function () {
    $counter = newCounter();
    $manager = newManager()->register(shopProvider($counter));

    $response = $manager->get('shop', only: ['orders_chart']);

    expect($response->charts)->toHaveCount(1)
        ->and($response->tables)->toBeEmpty()
        ->and($counter->products)->toBe(0);
});

it('isolates a failing section', function () {
    $provider = new class () implements ProviderInterface {
        public function key(): string
        {
            return 'broken';
        }

        public function available(): bool
        {
            return true;
        }

        public function sections(): array
        {
            return [
                Section::make('bad')->using(fn () => throw new RuntimeException('boom')),
                Section::make('good')->using(fn () => new Chart('ok', 'bar', [], [])),
            ];
        }
    };

    $response = newManager()->register($provider)->get('broken');

    expect($response->charts)->toHaveCount(1)
        ->and($response->errors)->toHaveKey('broken.bad');
});

it('reports unknown providers in a dashboard instead of throwing', function () {
    $response = newManager()->dashboard(['nope']);

    expect($response->errors)->toHaveKey('nope');
});

it('throws when getting an unknown provider directly', function () {
    newManager()->get('nope');
})->throws(ProviderNotFoundException::class);
