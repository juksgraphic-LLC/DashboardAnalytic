<?php

use Juksgraphic\DashboardAnalytic\DTO\AnalyticsResponse;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\DTO\Table;

function findersResponse(): AnalyticsResponse
{
    return new AnalyticsResponse(
        stats: [new StatItem('orders', 'Orders', 10)],
        charts: [new Chart('orders', 'line', [], []), new Chart('visits', 'bar', [], [])],
        tables: [new Table('top', [], [])],
    );
}

it('finds a widget by id and type', function () {
    $r = findersResponse();

    expect($r->stat('orders'))->toBeInstanceOf(StatItem::class)
        ->and($r->chart('visits'))->toBeInstanceOf(Chart::class)
        ->and($r->table('top'))->toBeInstanceOf(Table::class);
});

it('does not mix types sharing the same id', function () {
    $r = findersResponse();

    expect($r->stat('orders')->label)->toBe('Orders')
        ->and($r->chart('orders')->type)->toBe('line')
        ->and($r->table('orders'))->toBeNull();
});

it('returns null for an unknown id', function () {
    expect(findersResponse()->chart('nope'))->toBeNull();
});

it('reports presence with has*', function () {
    $r = findersResponse();

    expect($r->hasChart('visits'))->toBeTrue()
        ->and($r->hasStat('visits'))->toBeFalse()
        ->and($r->hasTable('top'))->toBeTrue();
});

it('returns the first widget when ids are duplicated', function () {
    $r = new AnalyticsResponse(charts: [
        new Chart('dup', 'line', [], []),
        new Chart('dup', 'bar', [], []),
    ]);

    expect($r->chart('dup')->type)->toBe('line');
});
