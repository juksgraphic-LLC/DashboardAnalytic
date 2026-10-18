<?php

use Juksgraphic\DashboardAnalytic\AnalyticsManager;
use Juksgraphic\DashboardAnalytic\Cache\SectionCache;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\DTO\Table;
use Juksgraphic\DashboardAnalytic\Exceptions\InvalidSectionResultException;
use Juksgraphic\DashboardAnalytic\Providers\CallableProvider;
use Juksgraphic\DashboardAnalytic\Section\Section;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

it('throws when loading a section without a resolver', function () {
    Section::make('orders')->load(new AnalyticsQuery());
})->throws(InvalidSectionResultException::class, 'Section [orders] has no resolver');

it('throws on an unsupported return type', function (mixed $value) {
    Section::make('orders')
        ->using(fn () => $value)
        ->load(new AnalyticsQuery());
})->throws(InvalidSectionResultException::class, 'unsupported type')->with([
    'string' => ['nope'],
    'int'    => [42],
    'null'   => [null],
    'object' => [new stdClass()],
]);

it('throws when an array contains an unsupported item', function () {
    Section::make('mixed')
        ->using(fn () => [new Chart('c', 'bar', [], []), 'oops'])
        ->load(new AnalyticsQuery());
})->throws(InvalidSectionResultException::class, '[string]');

it('wraps a single widget into a response', function () {
    $section = Section::make('s');

    $stat = $section->using(fn () => new StatItem('u', 'Users', 10))->load(new AnalyticsQuery());
    $chart = $section->using(fn () => new Chart('c', 'bar', [], []))->load(new AnalyticsQuery());
    $table = $section->using(fn () => new Table('t', [], []))->load(new AnalyticsQuery());

    expect($stat->stats)->toHaveCount(1)
        ->and($chart->charts)->toHaveCount(1)
        ->and($table->tables)->toHaveCount(1);
});

it('returns an AnalyticsResponse untouched', function () {
    $response = new AnalyticsResponse(errors: ['x' => 'y']);

    $loaded = Section::make('s')->using(fn () => $response)->load(new AnalyticsQuery());

    expect($loaded)->toBe($response);
});

it('merges an array of widgets, including nested arrays', function () {
    $loaded = Section::make('s')
        ->using(fn () => [
            new StatItem('a', 'A', 1),
            [new StatItem('b', 'B', 2), new Chart('c', 'line', [], [])],
        ])
        ->load(new AnalyticsQuery());

    expect($loaded->stats)->toHaveCount(2)
        ->and($loaded->charts)->toHaveCount(1);
});

it('passes the query to the resolver', function () {
    $received = null;

    Section::make('s')
        ->using(function (AnalyticsQuery $q) use (&$received) {
            $received = $q;

            return new AnalyticsResponse();
        })
        ->load($query = new AnalyticsQuery(limit: 7));

    expect($received)->toBe($query);
});

it('is immutable: fluent methods return new instances', function () {
    $base = Section::make('s');
    $tagged = $base->withTags(['a', 'a', 'b'])->withTtl(60);

    expect($base->tags())->toBe([])
        ->and($base->ttl())->toBeNull()
        ->and($tagged->tags())->toBe(['a', 'b'])
        ->and($tagged->ttl())->toBe(60)
        ->and($tagged->id())->toBe('s');
});

it('reports an invalid section result as an error through the manager', function () {
    $provider = new CallableProvider('p', [
        Section::make('bad')->using(fn () => 'not a widget'),
        Section::make('good')->using(fn () => new Chart('ok', 'bar', [], [])),
    ]);

    $manager = new AnalyticsManager(new SectionCache(new TagAwareAdapter(new ArrayAdapter())));
    $response = $manager->register($provider)->get('p');

    expect($response->charts)->toHaveCount(1)
        ->and($response->errors)->toHaveKey('p.bad')
        ->and($response->errors['p.bad'])->toContain('unsupported type');
});