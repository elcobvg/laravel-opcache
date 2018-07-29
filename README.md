# laravel-opcache [![Build Status](https://travis-ci.org/elcobvg/laravel-opcache.svg?branch=master)](https://travis-ci.org/elcobvg/laravel-opcache)
Custom OPcache Cache Driver for Laravel. Faster than Redis, Memcache or APC.

This package adds a cache driver to your Laravel project that uses PHP engine’s in-memory file caching (OPcache) to cache application data.

This method is faster than Redis, Memcache, APC, and other PHP caching solutions because all those solutions must serialize and unserialize objects. By storing PHP objects in file cache memory across requests, this driver can avoid serialization completely!

## Installation

Require this package with composer.

```shell
composer require elcobvg/laravel-opcache
```

Laravel 5.5 uses Package Auto-Discovery, so doesn't require you to manually add the ServiceProvider.

If you don't use auto-discovery or Laravel version 5.4 or older, add the ServiceProvider to the providers array in config/app.php

```php
ElcoBvg\Opcache\ServiceProvider::class,
```

Then, make sure you add the driver option to the stores array in config/cache.php

```php
    'opcache' => [
        'driver' => 'opcache',
    ],
```

And enable the driver in your `.env` file or in config/cache.php

### OPcache configuration

*OPcache can only be compiled as a shared extension. You must compile PHP with the `--enable-opcache` option for OPcache to be available.*

OPcache must be enabled and configured in your php.ini. Look for the section starting with `[OPcache]` and enter the desired values. The more memory you can assign to OPcache, the faster your cache will be. The `opcache.max_accelerated_files` value should be high enough to hold all objects that need to be cached. 

Since *all* PHP files will be cached with OPcache, it is not advisable to use it in a development environment, so only enable it in production. Alternatively, you can exclude PHP files from being cached by specifying a blacklist file with the `opcache.blacklist_filename` option. 

```shell
  opcache.enable=1
  opcache.memory_consumption=512
  opcache.interned_strings_buffer=64
  opcache.max_accelerated_files=32500
  opcache.validate_timestamps=1
  opcache.save_comments=1
  opcache.revalidate_freq=60
  opcache.fast_shutdown=1
  opcache.enable_cli=1
```

**Graceful degradation:** when OPcache is not enabled or installed, or memory is insufficient, this driver will still work but will read from the cached files instead of from memory. Since there's no unserialization required, it will still be faster than a regular file cache driver.

### Caching Eloquent models

Depending on how you've configured caching in your project, you may want to cache full Eloquent models. If so, base your model classes on `ElcoBvg\Opcache\Model` instead of the regular Eloquent base model class, so they can be retrieved correctly from cache.

### References

- [500X Faster Caching than Redis/Memcache/APC in PHP & HHVM](https://blog.graphiq.com/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad)
- [Make your Laravel App Fly with PHP OPcache](https://medium.com/appstract/make-your-laravel-app-fly-with-php-opcache-9948db2a5f93)
- [PHP OPcache Documentation](http://php.net/manual/en/book.opcache.php)
- [Pecl::Package::ZendOpcache](http://pecl.php.net/package/ZendOpcache)
