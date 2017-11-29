<?php

namespace ElcoBvg\Opcache;

use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Cache\RetrievesMultipleKeys;

/**
 * OpcacheStore - cache driver for Laravel
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 *
 * @author  Elco Brouwer von Gonzenbach <elco.brouwer@gmail.com>
 * @version 0.1.0
 *
 */
class OpcacheStore implements Store
{
    use RetrievesMultipleKeys;

    /**
     * The file cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * A string that should be prepended to keys.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Is OPcache enabled or not.
     *
     * @var boolean
     */
    protected $enabled = false;

    /**
     * Create a new OPcache store.
     *
     * @param  string    $prefix
     * @param  string    $directory
     * @return void
     */
    public function __construct(string $prefix = '', string $directory = '')
    {
        // Graceful degradation: if OPcache is not enabled, we're just loading cache files.
        if (extension_loaded('Zend OPcache')) {
            $this->enabled = true;
        } elseif (config('app.debug')) {
            Log::error('You do not have the Zend OPcache extension loaded!');
        }

        $this->prefix = str_slug($prefix ?: config('cache.prefix', 'opcache'), '-');
        $this->directory = $directory ?: config('cache.stores.file.path');
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        @include $this->filePath($key);

        if (isset($exp) && $exp < time()) {
            // $this->forget($key);
            return null;
        }
        return isset($val) ? $val : null;
    }

    /**
     * Store an item in the cache for a given number of minutes.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return void
     */
    public function put($key, $value, $minutes = 0)
    {
        $val = var_export($value, true);

        // HHVM fails at __set_state, so just use object cast for now
        $val = str_replace('stdClass::__set_state', '(object)', $val);

        return $this->writeFile($key, $this->expiration($minutes), $val);
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $minutes
     * @return bool
     */
    public function add($key, $value, $minutes = 0)
    {
        if ($this->enabled && opcache_is_script_cached($this->filePath($key))
            || file_exists($this->filePath($key))) {
            return false;
        }

        $this->put($key, $value, $minutes);
        return true;
    }

    /**
     * Increment the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function increment($key, $value = 1)
    {
        $val = (int) $this->get($key) + $value;
        $this->put($key, $val);
        return $val;
    }

    /**
     * Decrement the value of an item in the cache.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return int|bool
     */
    public function decrement($key, $value = 1)
    {
        return $this->increment($key, $value * -1);
    }

    /**
     * Store an item in the cache indefinitely.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function forever($key, $value)
    {
        $this->put($key, $value, 0);
    }

    /**
     * Remove an item from the cache.
     *
     * @param  string  $key
     * @return bool
     */
    public function forget($key)
    {
        if ($this->enabled) {
            opcache_invalidate($this->filePath($key), true);
        }
        return unlink($this->filePath($key));
    }

    /**
     * Remove all items from the cache.
     * NOTE: This method resets the entire opcode cache.
     *
     * @return bool
     */
    public function flush()
    {
        if ($this->enabled) {
            opcache_reset();
        }
        return (bool) array_map('unlink', glob($this->directory . '/' . $this->prefix . '*'));
    }

    /**
     * Get the cache key prefix.
     *
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Get fully qualified file path
     *
     * @param  string  $key
     * @return string
     */
    protected function filePath(string $key)
    {
        return $this->directory . '/' . $this->prefix . '-' . sha1($key);
    }

    /**
     * Write the cache file to disk
     *
     * @param   string $key
     * @param   int    $exp
     * @param   mixed  $val
     * @return  boolean
     */
    protected function writeFile(string $key, int $exp, $val)
    {
        // Write to temp file first to ensure atomicity
        $tmp = $this->directory . '/' . sha1($key) . '-' . uniqid('', true) . '.tmp';
        file_put_contents($tmp, '<?php $exp = ' . $exp . '; $val = ' . $val . ';', LOCK_EX);
        return rename($tmp, $this->filePath($key));
    }

    /**
     * Get the expiration time based on the given minutes.
     *
     * @param  float|int  $minutes
     * @return int
     */
    protected function expiration($minutes)
    {
        return $minutes === 0 ? 9999999999 : strtotime('+' . $minutes . ' minutes');
    }

    /**
     * Extend expiration time with given minutes
     *
     * @param  string $key
     * @param  int    $seconds
     * @return boolean
     */
    public function extendExpiration(string $key, int $minutes)
    {
        @include $this->filePath($key);

        if (isset($exp)) {
            return $this->writeFile($key, strtotime('+' . $minutes . ' minutes', $exp), $val);
        }
        Log::warning('No expiration time found for: ' . $key);
    }
}
