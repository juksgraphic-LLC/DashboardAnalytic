<?php

use Juksgraphic\DashboardAnalytic\Exceptions\AnalyticsException;
use Juksgraphic\DashboardAnalytic\Exceptions\InvalidSectionResultException;
use Juksgraphic\DashboardAnalytic\Exceptions\ProviderFailedException;
use Juksgraphic\DashboardAnalytic\Exceptions\ProviderNotFoundException;
use Juksgraphic\DashboardAnalytic\Exceptions\SectionNotFoundException;

it('builds a not found exception', function () {
    $e = ProviderNotFoundException::forKey('shop');

    expect($e)->toBeInstanceOf(AnalyticsException::class)
        ->and($e->getMessage())->toBe('Analytics provider [shop] not found.');
});

it('wraps the previous throwable when a provider fails', function () {
    $previous = new RuntimeException('boom');
    $e = ProviderFailedException::forKey('shop', 'boom', $previous);

    expect($e->getPrevious())->toBe($previous)
        ->and($e->getMessage())->toBe('Analytics provider [shop] failed: boom');
});

it('builds a section not found exception', function () {
    expect(SectionNotFoundException::forKey('shop', 'orders')->getMessage())
        ->toBe('Section [orders] not found in provider [shop].');
});


it('builds a no resolver exception', function () {
    $e = InvalidSectionResultException::noResolver('orders');

    expect($e)->toBeInstanceOf(AnalyticsException::class)
        ->and($e->getMessage())->toBe('Section [orders] has no resolver. Call using() first.');
});

it('describes the unsupported type in the message', function (mixed $value, string $type) {
    $e = InvalidSectionResultException::invalidType('orders', $value);

    expect($e)->toBeInstanceOf(AnalyticsException::class)
        ->and($e->getMessage())->toContain('Section [orders]')
        ->and($e->getMessage())->toContain("[{$type}]");
})->with([
    'string' => ['hello', 'string'],
    'int'    => [42, 'int'],
    'null'   => [null, 'null'],
    'object' => [new stdClass(), 'stdClass'],
]);
