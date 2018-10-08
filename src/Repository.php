<?php

namespace ElcoBvg\Opcache;

use Closure;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository as CacheContract;

class Repository extends TaggedCache implements CacheContract
{
    /**
     * Get an item from the cache, or store the default value.
     * Override parent method to avoid cache slamming ('thundering herd problem')
     *
     * @param  string  $key
     * @param  \DateTimeInterface|\DateInterval|float|int  $minutes
     * @param  \Closure  $callback
     * @return mixed
     */
    public function remember($key, $minutes, Closure $callback)
    {
        if (is_null($value = $this->get($key))) {
            // Extend expiration of cache file so we have time to generate a new one
            $this->store->extendExpiration($this->itemKey($key), 10);
            $this->put($key, $value = $callback(), $minutes);
        }
        return $value;
    }

    /**
     * Remove all items from the cache. If called with tags, only reset them.
     *
     * @return void
     */
    public function flush()
    {
        $this->tags->getNames() ? $this->store->flushSub() : $this->store->flush();
    }
}
