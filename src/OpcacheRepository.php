<?php

namespace ElcoBvg\Opcache;

use Closure;
use Illuminate\Cache\Repository;
use Illuminate\Contracts\Cache\Repository as RepositoryContract;

/**
 * OpcacheRepository - cache driver for Laravel
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 * @author  Elco Brouwer von Gonzenbach <elco.brouwer@gmail.com>
 * @version 0.1.0
 *
 */
class OpcacheRepository extends Repository implements RepositoryContract
{
    /**
     * Get an item from the cache, or store the default value.
     * Override parent method to avoid cache slamming / race conditions
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, $minutes, Closure $callback)
    {
        $value = $this->get($key);
        if (! is_null($value)) {
            return $value;
        }

        // Extend expiration of cache file so we have time to generate a new one
        $this->store->extendExpiration($key, 10);

        $this->put($key, $value = $callback(), $minutes);
        return $value;
    }
}
