<?php

namespace ElcoBvg\Opcache;

use Illuminate\Support\Str;
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
     * The file cache sub directory
     *
     * @var string|null
     */
    protected $subDirectory;

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
     */
    public function __construct(string $prefix = '', string $directory = '')
    {
        // Graceful degradation: if OPcache is not enabled, we're just loading cache files.
        if (extension_loaded('Zend OPcache')) {
            $this->enabled = true;
        } elseif (config('app.debug')) {
            Log::warning('You do not have the Zend OPcache extension loaded!');
        }

        $this->prefix = Str::slug($prefix ?: config('app.name', 'opcache'), '-');

        /*
         * In case if `OpCache` file path not being set we will use `file` driver path
         */
        $this->directory = $directory ?: config('cache.stores.opcache.path', config('cache.stores.file.path'));
    }
    
    /**
     * Begin executing a new tags operation.
     *
     * @param  array|mixed  $names
     * @return \Illuminate\Cache\TaggedCache
     */
    public function tags($names)
    {
        $names = is_array($names) ? $names : func_get_args();

        /*
         * Now we are able to flush only tagged cache items
         */
        if (! empty($names)) {
            $this->setSubDirectory($this->tagsSubDir($names));
        }

        return new Repository($this, new TagSet($this, $names));
    }

    /**
     * @param array $names
     * @return string
     */
    protected function tagsSubDir(array $names)
    {
        return implode('_', $names);
    }

    /**
     * Determines whether the key exists within the cache.
     *
     * @param string $key
     * @return bool
     */
    protected function exists($key)
    {
        if ($this->enabled && opcache_is_script_cached($this->filePath($key))) {
            return true;
        }
        return file_exists($this->filePath($key));
    }

    /**
     * Retrieve an item from the cache by key.
     *
     * @param  string  $key
     * @return mixed
     */
    public function get($key)
    {
        if ($this->exists($key)) {
            @include $this->filePath($key);
        }

        if (isset($exp) && $exp < time()) {
            /*
             * In order to free disc space and memory we need to
             * delete expired file from our disc and invalidate it from OpCache
             */
            $this->forget($key);

            return null;
        }
        return isset($val) ? $val : null;
    }

    /**
     * Store an item in the cache for a given number of seconds.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $seconds
     * @return bool
     */
    public function put($key, $value, $seconds = 0)
    {
        $val = var_export($value, true);

        // HHVM fails at __set_state, so just use object cast for now
        if (defined('HHVM_VERSION')) {
            $val = str_replace('stdClass::__set_state', '(object)', $val);
        }

        return $this->writeFile($key, $this->expiration($seconds), $val);
    }

    /**
     * Store an item in the cache if the key doesn't exist.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @param  float|int  $seconds
     * @return bool
     */
    public function add($key, $value, $seconds = 0)
    {
        if ($this->exists($key)) {
            return false;
        }
        return $this->put($key, $value, $seconds);
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
     * @return bool
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
        return $this->clearCacheInDirectory($this->getDirectory());
    }

    /**
     * @return bool
     */
    public function flushSub()
    {
        return $this->clearCacheInDirectory($this->getFullDirectory(), true);
    }

    /**
     * @param $dir
     * @param bool $removeDirectory
     * @return bool
     */
    public function clearCacheInDirectory($dir, $removeDirectory = false)
    {
        /*
         * Since we now able to set sub directory to keep files
         * in separated folders we will need to flush all files recursively
         */
        $directory = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($directory as $filename => $file) {
            if ($file->isDir()) {
                @rmdir($filename);
                continue;
            }

            if ($this->enabled) {
                opcache_invalidate($filename, true);
            }
            @unlink($filename);
        }

        if ($removeDirectory) {
            return @rmdir($dir);
        }

        return true;
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
    public function filePath(string $key)
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
        return $this->getFullDirectory() . DIRECTORY_SEPARATOR . $this->prefix;
    }

    /**
     * @return string
     */
    public function getFullDirectory()
    {
        $dir = $this->getDirectory();

        $subDir = $this->getSubDirectory();

        if (is_string($subDir)) {
            return rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($subDir, DIRECTORY_SEPARATOR);
        }

        return $dir;
    }

    /**
     * @return string
     */
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * @param $subDirectory
     * @return $this
     */
    public function setSubDirectory($subDirectory)
    {
        $this->subDirectory = $subDirectory;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getSubDirectory()
    {
        return $this->subDirectory;
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
        $dir = $this->getFullDirectory();
        $this->checkDirectory($dir);
        $tmp = $dir . DIRECTORY_SEPARATOR . crc32($key) . '-' . uniqid('', true) . '.tmp';
        file_put_contents($tmp, '<?php $exp = ' . $exp . '; $val = ' . $val . ';', LOCK_EX);
        return rename($tmp, $this->filePath($key));
    }

    /**
     * @param $dir
     */
    protected function checkDirectory($dir)
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * Get the expiration time based on the given seconds.
     *
     * @param  float|int  $seconds
     * @return int
     */
    protected function expiration($seconds)
    {
        return strtotime('+' . ($seconds ?: 9999999999) . ' seconds');
    }

    /**
     * Extend expiration time with given seconds
     *
     * @param  string $key
     * @param  int    $seconds
     * @return bool
     */
    public function extendExpiration(string $key, int $seconds = 1)
    {
        @include $this->filePath($key);

        if (isset($exp)) {
            $extended = strtotime('+' . $seconds . ' seconds', $exp);
            return $this->writeFile($key, $extended, var_export($val, true));
        }
        return false;
    }
}
