<?php

use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\DTO\Table;

function sampleResponse(): AnalyticsResponse
{
    return new AnalyticsResponse(
        stats: [new StatItem('users', 'Users', 1200, 16.24, 'number', 'users')],
        charts: [new Chart('orders', 'line', ['Jan', 'Feb'], [['label' => 'Orders', 'data' => [1, 2]]])],
        tables: [new Table('top', [['key' => 'name', 'label' => 'Name']], [['name' => 'A']])],
    );
}

it('round-trips a response through toArray/fromArray', function () {
    $response = sampleResponse();

    expect(AnalyticsResponse::fromArray($response->toArray()))->toEqual($response);
});

it('serializes to json', function () {
    $json = json_decode(json_encode(sampleResponse()), true);

    expect($json)->toHaveKeys(['stats', 'charts', 'tables', 'errors'])
        ->and($json['stats'][0]['id'])->toBe('users');
});

it('merges without mutating the originals', function () {
    $a = sampleResponse();
    $b = new AnalyticsResponse(errors: ['x' => 'failed']);

    $merged = $a->merge($b);

    expect($merged->stats)->toHaveCount(1)
        ->and($merged->errors)->toBe(['x' => 'failed'])
        ->and($a->errors)->toBe([]);
});

it('adds errors immutably', function () {
    $a = new AnalyticsResponse();
    $b = $a->withError('shop', 'down');

    expect($a->hasErrors())->toBeFalse()
        ->and($b->hasErrors())->toBeTrue()
        ->and($b->isEmpty())->toBeTrue();
});

it('produces the same query hash regardless of key order', function () {
    $a = new AnalyticsQuery(filters: ['status' => 'paid', 'country' => 'HT']);
    $b = new AnalyticsQuery(filters: ['country' => 'HT', 'status' => 'paid']);
    $c = new AnalyticsQuery(filters: ['country' => 'US', 'status' => 'paid']);

    expect($a->hash())->toBe($b->hash())
        ->and($a->hash())->not->toBe($c->hash());
});
