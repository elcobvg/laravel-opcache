<?php

namespace ElcoBvg\Opcache;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

/**
 * OpcacheServiceProvider - cache driver for Laravel
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 * @author  Elco Brouwer von Gonzenbach <elco.brouwer@gmail.com>
 * @version 0.1.0
 *
 */
class OpcacheServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Cache::extend('opcache', function () {
            return new OpcacheRepository(new OpcacheStore);
        });

        // Extend Collection to implement __set_state magic method
        if (! Collection::hasMacro('__set_state')) {
            Collection::macro('__set_state', function (array $array) {
                $obj = new Collection;
                $obj->items = $array['items'];
                return $obj;
            });
        }
    }
}
