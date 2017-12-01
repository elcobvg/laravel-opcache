<?php

namespace ElcoBvg\Opcache;

use Illuminate\Cache\TagSet;
use Illuminate\Cache\TaggableStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Cache\Store as StoreContract;
use Illuminate\Cache\RetrievesMultipleKeys;

class Store extends TaggableStore implements StoreContract
{
    use RetrievesMultipleKeys;

    /**
     * The file cache directory.
     *
     * @var string
     */
    protected $directory;

    /**
     * String that should be prepended to keys.
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
            Log::warning('You do not have the Zend OPcache extension loaded!');
        }

        $this->prefix = str_slug($prefix ?: config('app.name', 'opcache'), '-');
        $this->directory = $directory ?: config('cache.stores.file.path');
    }
    
    /**
     * Begin executing a new tags operation.
     *
     * @param  array|mixed  $names
     * @return \Illuminate\Cache\TaggedCache
     */
    public function tags($names)
    {
        return new Repository($this, new TagSet($this, is_array($names) ? $names : func_get_args()));
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
     * @return bool
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

        return $this->put($key, $value, $minutes);
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
        return $this->put($key, $val) ? $val : false;
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
        return $this->put($key, $value, 0);
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
        return @unlink($this->filePath($key));
    }

    /**
     * Remove all items from the cache.
     *
     * @return bool
     */
    public function flush()
    {
        $files = glob($this->prefixPath() . '*');

        if ($this->enabled) {
            array_map('opcache_invalidate', $files);
        }
        return (bool) array_map('unlink', $files);
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
        return $this->prefixPath() . '-' . sha1($key);
    }

    /**
     * Get directory path with prefix
     *
     * @return string
     */
    public function prefixPath()
    {
        return $this->directory . '/' . $this->prefix;
    }

    /**
     * Write the cache file to disk
     *
     * @param   string $key
     * @param   int    $exp
     * @param   mixed  $val
     * @return  bool
     */
    protected function writeFile(string $key, int $exp, $val)
    {
        // Write to temp file first to ensure atomicity. Use crc32 for speed
        $tmp = $this->directory . '/' . crc32($key) . '-' . uniqid('', true) . '.tmp';
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
        $seconds = (int) $minutes * 60;
        return $minutes === 0 ? 9999999999 : strtotime('+' . $seconds . ' seconds');
    }

    /**
     * Extend expiration time with given minutes
     *
     * @param  string $key
     * @param  int    $seconds
     * @return bool
     */
    public function extendExpiration(string $key, int $minutes)
    {
        @include $this->filePath($key);

        if (isset($exp)) {
            return $this->writeFile($key, strtotime('+' . $minutes . ' minutes', $exp), $val);
        }
    }
}
