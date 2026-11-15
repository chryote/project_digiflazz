<?php

use Spatie\Prometheus\Actions\RenderCollectorsAction;
use Spatie\Prometheus\Http\Middleware\AllowIps;

return [
    'enabled' => true,

    /*
     * The urls that will return metrics.
     */
    'urls' => [
        'default' => 'metrics',
        'prometheus' => 'prometheus',
    ],

    /*
     * Only these IP's will be allowed to visit the above urls.
     * All IP's are allowed when empty.
     */
    'allowed_ips' => [
        // '1.2.3.4',
    ],

    /*
     * This is the default namespace that will be
     * used by all metrics
     */
    'default_namespace' => 'laravel',

    /*
     * The middleware that will be applied to the urls above
     */
    'middleware' => [
        AllowIps::class,
    ],

    /*
     * You can override these classes to customize low-level behaviour of the package.
     * In most cases, you can just use the defaults.
     */
    'actions' => [
        'render_collectors' => RenderCollectorsAction::class,
    ],

    /**
     * Allow storage to be wiped after a render of data in metrics controller.
     */
    'wipe_storage_after_rendering' => false,

    /**
     * Select a cache to store gauges, counters, summaries and histograms between requests.
     */
    'cache' => null,

    /*
     * Lock each metric type while updating cached metrics to prevent concurrent
     * writers from overwriting each other.
     */
    'cache_lock' => false,
];
