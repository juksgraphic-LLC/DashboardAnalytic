<?php

declare(strict_types=1);

namespace Juksgraphic\DashboardAnalytic\Example;

use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\Chart;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\DTO\Table;
use Juksgraphic\DashboardAnalytic\Providers\AbstractProvider;

/**
 * Demo provider with static data. Use it as a template for your own providers.
 */
final class DemoShopProvider extends AbstractProvider
{
    public function key(): string
    {
        return 'demo_shop';
    }

    public function sections(): array
    {
        return [
            // One section may return several widgets (an array)
            $this->section('kpis')
                ->withTags(['orders', 'products'])
                ->withTtl(300)
                ->using(fn (AnalyticsQuery $q) => $this->kpis()),

            // Or a single widget
            $this->section('orders_chart')
                ->withTags(['orders'])
                ->withTtl(600)
                ->using(fn (AnalyticsQuery $q) => $this->ordersChart()),

            $this->section('top_products')
                ->withTags(['products'])
                ->withTtl(3600)
                ->using(fn (AnalyticsQuery $q) => $this->topProducts($q->limit ?? 5)),
        ];
    }

    /** @return StatItem[] */
    private function kpis(): array
    {
        return [
            new StatItem('orders', 'Orders', 1284, 12.4, 'number', 'orders'),
            new StatItem('revenue', 'Revenue', 48250.75, -3.1, 'currency', 'earnings', ['currency' => 'USD']),
            new StatItem('products', 'Products', 342, null, 'number', 'products'),
        ];
    }

    private function ordersChart(): Chart
    {
        return new Chart(
            id: 'orders',
            type: 'line',
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [
                ['label' => 'Orders', 'data' => [120, 150, 170, 140, 210, 260]],
            ],
            options: ['tension' => 0.4],
        );
    }

    private function topProducts(int $limit): Table
    {
        $rows = [
            ['name' => 'Wireless Headphones', 'sales' => 320, 'revenue' => 12800.0],
            ['name' => 'Smart Watch', 'sales' => 210, 'revenue' => 15750.0],
            ['name' => 'Laptop Stand', 'sales' => 190, 'revenue' => 5700.0],
            ['name' => 'USB-C Hub', 'sales' => 160, 'revenue' => 4800.0],
            ['name' => 'Mechanical Keyboard', 'sales' => 120, 'revenue' => 9600.0],
            ['name' => 'Webcam HD', 'sales' => 90, 'revenue' => 4500.0],
        ];

        return new Table(
            id: 'top_products',
            columns: [
                ['key' => 'name', 'label' => 'Product'],
                ['key' => 'sales', 'label' => 'Sales'],
                ['key' => 'revenue', 'label' => 'Revenue'],
            ],
            rows: array_slice($rows, 0, $limit),
        );
    }
}
