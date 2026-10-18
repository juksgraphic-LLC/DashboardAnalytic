<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Juksgraphic\DashboardAnalytic\AnalyticsManager;
use Juksgraphic\DashboardAnalytic\Cache\SectionCache;
use Juksgraphic\DashboardAnalytic\DTO\AnalyticsQuery;
use Juksgraphic\DashboardAnalytic\DTO\StatItem;
use Juksgraphic\DashboardAnalytic\Example\DemoShopProvider;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;



/** Escape text for HTML. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Encode data to put inside a SINGLE-quoted HTML attribute, as a JS object literal:
 *   x-data='apexChart(<?= jsonAttr($options) ?>)'
 *
 * - JSON_HEX_TAG / JSON_HEX_AMP / JSON_HEX_APOS: "<", ">", "&" and "'" become \uXXXX,
 *   so the value can neither close the attribute nor inject HTML.
 * - JSON_HEX_QUOT is NOT used on purpose: it would also escape the structural double
 *   quotes of the JSON, and the object literal would no longer be valid JavaScript.
 */
function jsonAttr(mixed $value): string
{
    return json_encode(
        $value,
        JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRESERVE_ZERO_FRACTION
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR
    );
}

function formatStat(StatItem $stat): string
{
    $value = $stat->value;

    return match ($stat->format) {
        'currency' => ($stat->meta['currency'] ?? 'USD') === 'USD'
            ? '$' . number_format((float) $value, 2)
            : number_format((float) $value, 2) . ' ' . ($stat->meta['currency'] ?? ''),
        'percent' => number_format((float) $value, 2) . '%',
        'number' => number_format((float) $value),
        default => (string) $value,
    };
}

// ───────────────────────── Manager ─────────────────────────

$store = new TagAwareAdapter(
    new FilesystemAdapter('analytics', 0, __DIR__ . '/storage/cache')
);

$analytics = new AnalyticsManager(new SectionCache($store, defaultTtl: 3600));
$analytics->register(new DemoShopProvider());

// Actions: ?flush=1  ?forget=orders  ?refresh=1  ?refresh=orders  ?limit=3  ?format=json
if (isset($_GET['flush'])) {
    $analytics->flush();
}

if (isset($_GET['forget'])) {
    $analytics->forgetTag((string) $_GET['forget']);
}

$manager = $analytics;
if (isset($_GET['refresh'])) {
    $manager = $_GET['refresh'] === '1'
        ? $analytics->withoutCache()
        : $analytics->withoutCache(tags: [(string) $_GET['refresh']]);
}

$limit = isset($_GET['limit']) ? max(1, min(6, (int) $_GET['limit'])) : 5;

$started = microtime(true);
$response = $manager->get('demo_shop', new AnalyticsQuery(limit: $limit));
$elapsedMs = round((microtime(true) - $started) * 1000, 2);

// JSON mode: /index.php?format=json
if (($_GET['format'] ?? '') === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['time_ms' => $elapsedMs, 'data' => $response],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

// ───────────────── Chart options built in PHP ─────────────────

$ordersChart = $response->chart('orders');
$topProducts = $response->table('top_products');

// ApexCharts (line / area / bar, switchable from the UI)
$apexOptions = null;
if ($ordersChart !== null) {
    $apexOptions = [
        'chart' => [
            'type' => 'area',
            'height' => 300,
            'toolbar' => ['show' => false],
            'fontFamily' => 'inherit',
        ],
        'series' => array_map(
            static fn (array $d) => ['name' => $d['label'], 'data' => $d['data']],
            $ordersChart->datasets
        ),
        'xaxis' => ['categories' => $ordersChart->labels],
        'stroke' => [
            'curve' => ($ordersChart->options['tension'] ?? 0) > 0 ? 'smooth' : 'straight',
            'width' => 3,
        ],
        'colors' => ['#6366f1'],
        'dataLabels' => ['enabled' => false],
        'grid' => ['borderColor' => '#e2e8f0'],
        'fill' => ['type' => 'gradient', 'gradient' => ['opacityFrom' => 0.45, 'opacityTo' => 0.05]],
    ];
}

// Chart.js bar chart (same data, other library)
$barConfig = null;
if ($ordersChart !== null) {
    $barConfig = [
        'type' => 'bar',
        'data' => [
            'labels' => $ordersChart->labels,
            'datasets' => array_map(
                static fn (array $d) => [
                    'label' => $d['label'],
                    'data' => $d['data'],
                    'backgroundColor' => '#818cf8',
                    'borderRadius' => 6,
                ],
                $ordersChart->datasets
            ),
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'grid' => ['color' => '#e2e8f0']]],
        ],
    ];
}

// Chart.js doughnut: revenue per product, taken from the table
$doughnutConfig = null;
if ($topProducts !== null) {
    $doughnutConfig = [
        'type' => 'doughnut',
        'data' => [
            'labels' => array_column($topProducts->rows, 'name'),
            'datasets' => [[
                'label' => 'Revenue',
                'data' => array_column($topProducts->rows, 'revenue'),
                'backgroundColor' => ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                'borderWidth' => 0,
            ]],
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'cutout' => '65%',
            'plugins' => ['legend' => ['position' => 'bottom']],
        ],
    ];
}

$icons = ['orders' => '🛒', 'earnings' => '💰', 'products' => '📦'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Analytic – Demo</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.54.1/dist/apexcharts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>

    <!-- Alpine components: registered BEFORE Alpine starts -->
    <script>
        document.addEventListener('alpine:init', () => {
           
            Alpine.data('apexChart', (options) => {
                let chart = null;

                return {
                    type: options.chart.type,
                    init() {
                        chart = new ApexCharts(this.$refs.canvas, options);
                        chart.render();
                    },
                    setType(type) {
                        this.type = type;
                        chart.updateOptions({ chart: { type } });
                    },
                    destroy() {
                        if (chart) chart.destroy();
                    },
                };
            });

            // Chart.js
            Alpine.data('chartJs', (config) => {
                let chart = null;

                return {
                    init() {
                        chart = new Chart(this.$refs.canvas, config);
                    },
                    destroy() {
                        if (chart) chart.destroy();
                    },
                };
            });
        });
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>

<body class="bg-slate-100 text-slate-800">
<div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-8">

    <!-- Header -->
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold">Dashboard Analytic</h1>
            <p class="text-sm text-slate-500">
                Provider <code class="rounded bg-slate-200 px-1">demo_shop</code> ·
                loaded in <span class="font-semibold"><?= e($elapsedMs) ?> ms</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-sm">
            <a href="?refresh=orders&limit=<?= e($limit) ?>" class="rounded-lg bg-indigo-600 px-3 py-2 font-medium text-white hover:bg-indigo-700">Refresh orders</a>
            <a href="?refresh=1&limit=<?= e($limit) ?>" class="rounded-lg bg-indigo-100 px-3 py-2 font-medium text-indigo-700 hover:bg-indigo-200">Refresh all</a>
            <a href="?forget=orders&limit=<?= e($limit) ?>" class="rounded-lg bg-white px-3 py-2 font-medium ring-1 ring-slate-300 hover:bg-slate-50">Forget "orders"</a>
            <a href="?flush=1" class="rounded-lg bg-white px-3 py-2 font-medium text-rose-600 ring-1 ring-slate-300 hover:bg-rose-50">Flush</a>
            <a href="?format=json" class="rounded-lg bg-white px-3 py-2 font-medium ring-1 ring-slate-300 hover:bg-slate-50">JSON</a>

            <form method="get" x-data>
                <select name="limit" @change="$el.form.submit()" class="rounded-lg border-0 bg-white px-3 py-2 ring-1 ring-slate-300">
                    <?php foreach ([2, 3, 4, 5, 6] as $n): ?>
                        <option value="<?= $n ?>" <?= $n === $limit ? 'selected' : '' ?>>Top <?= $n ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
    </header>

    <!-- Errors -->
    <?php if ($response->hasErrors()): ?>
        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
            <?php foreach ($response->errors as $key => $message): ?>
                <p><strong><?= e($key) ?></strong>: <?= e($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- KPIs -->
    <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($response->stats as $stat): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-slate-500"><?= e($stat->label) ?></p>
                    <span class="text-2xl"><?= $icons[$stat->icon ?? ''] ?? '📊' ?></span>
                </div>
                <p class="mt-2 text-3xl font-bold"><?= e(formatStat($stat)) ?></p>
                <?php if ($stat->change !== null): ?>
                    <p class="mt-1 text-sm font-medium <?= $stat->change >= 0 ? 'text-emerald-600' : 'text-rose-600' ?>">
                        <?= $stat->change >= 0 ? '▲' : '▼' ?> <?= e(number_format(abs($stat->change), 2)) ?>%
                        <span class="font-normal text-slate-400">vs last month</span>
                    </p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- Charts -->
    <section class="grid gap-4 lg:grid-cols-2">
        <?php if ($apexOptions !== null): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm" x-data='apexChart(<?= jsonAttr($apexOptions) ?>)'>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Orders <span class="text-xs font-normal text-slate-400">(ApexCharts)</span></h2>
                    <div class="flex gap-1 text-xs">
                        <template x-for="t in ['area', 'line', 'bar']" :key="t">
                            <button type="button" @click="setType(t)" x-text="t"
                                    :class="type === t ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                    class="rounded-md px-2 py-1 font-medium"></button>
                        </template>
                    </div>
                </div>
                <div x-ref="canvas"></div>
            </div>
        <?php endif; ?>

        <?php if ($barConfig !== null): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm" x-data='chartJs(<?= jsonAttr($barConfig) ?>)'>
                <h2 class="mb-3 font-semibold">Orders <span class="text-xs font-normal text-slate-400">(Chart.js)</span></h2>
                <div class="h-[300px]"><canvas x-ref="canvas"></canvas></div>
            </div>
        <?php endif; ?>

        <?php if ($doughnutConfig !== null): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm" x-data='chartJs(<?= jsonAttr($doughnutConfig) ?>)'>
                <h2 class="mb-3 font-semibold">Revenue by product <span class="text-xs font-normal text-slate-400">(Chart.js)</span></h2>
                <div class="h-[300px]"><canvas x-ref="canvas"></canvas></div>
            </div>
        <?php endif; ?>

        <!-- Table -->
        <?php if ($topProducts !== null): ?>
            <div class="rounded-xl bg-white p-5 shadow-sm">
                <h2 class="mb-3 font-semibold">Top products</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-slate-200 text-slate-500">
                        <tr>
                            <?php foreach ($topProducts->columns as $col): ?>
                                <th class="py-2 pr-4 font-medium"><?= e($col['label']) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                        <?php foreach ($topProducts->rows as $row): ?>
                            <tr>
                                <?php foreach ($topProducts->columns as $col): ?>
                                    <?php $cell = $row[$col['key']] ?? ''; ?>
                                    <td class="py-2 pr-4">
                                        <?= $col['key'] === 'revenue' ? '$' . e(number_format((float) $cell, 2)) : e($cell) ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <footer class="pb-4 text-center text-xs text-slate-400">
        Reload the page: <code>time_ms</code> should drop (cache hit).
        Use the buttons above to see tag invalidation and cache bypass.
    </footer>
</div>
</body>
</html>