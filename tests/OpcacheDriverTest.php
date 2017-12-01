<?php

namespace ElcoBvg\Opcache\Test;

use ElcoBvg\Opcache\Store;
use ElcoBvg\Opcache\Repository;
use ElcoBvg\Opcache\ServiceProvider;

use Mockery;
use Orchestra\Testbench\TestCase;

use Illuminate\Cache\TagSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class OpcacheDriverTest extends TestCase
{
    protected $application;

    public function setUp()
    {
        parent::setUp();
        $this->application = $this->createApplication();
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cache.default', 'opcache');
        $app['config']->set('cache.stores.opcache', ['driver' => 'opcache']);
    }

    protected function getPackageProviders($app)
    {
        return [ServiceProvider::class];
    }

    public function testStoreConstructor()
    {
        $store = $this->getStore();
        $this->assertTrue(is_a($store, 'ElcoBvg\Opcache\Store'));
    }

    public function testStoreConfig()
    {
        $store = $this->getStore();
        $this->assertEquals(sys_get_temp_dir() . '/opcache', $store->prefixPath());
    }

    public function testStorePutText()
    {
        $store = $this->getStore();
        $this->assertTrue($store->put('text-test', 'some text'));
    }

    public function testFlushCache()
    {
        $store = $this->getStore();
        $this->assertTrue($store->flush());
    }

    public function testStorePutArray()
    {
        $store = $this->getStore();
        $this->assertTrue($store->put('array-test', ['foo' => 'bar', 'baz' => 'boom']));
    }

    public function testStoreGet()
    {
        $store = $this->getStore();
        $this->assertEquals(['foo' => 'bar', 'baz' => 'boom'], $store->get('array-test'));
    }

    public function testStoreGetExpired()
    {
        $store = $this->getStore();
        $store->put('expired', 'wait until expired', 0.1);
        $this->assertEquals('wait until expired', $store->get('expired'));
        $timeout = strtotime('+10 seconds');
        while (time() < $timeout) {
            // Waiting....
        }
        $this->assertNull($store->get('expired'));
    }

    public function testStoreAddSuccess()
    {
        $store = $this->getStore();
        $this->assertTrue($store->add('add-test', 'add some value'));
    }

    public function testStoreAddFail()
    {
        $store = $this->getStore();
        // method returns false if cache key already exists
        $this->assertFalse($store->add('array-test', ['add' => 'nothing']));
    }

    public function testStoreIncrementNumber()
    {
        $store = $this->getStore();
        $store->put('number', 10, 2);
        $this->assertEquals(11, $store->increment('number'));
    }

    public function testStoreDecrementNumber()
    {
        $store = $this->getStore();
        $store->put('number', 10, 2);
        $this->assertEquals(9, $store->decrement('number'));
    }

    public function testStoreIncrementText()
    {
        $store = $this->getStore();
        $store->put('some-text', 'foo', 2);
        $this->assertEquals(1, $store->increment('some-text'));
    }

    public function testStoreIncrementArray()
    {
        $store = $this->getStore();
        $this->assertEquals(2, $store->increment('array-test'));
    }

    public function testStoreForever()
    {
        $store = $this->getStore();
        $this->assertTrue($store->forever('forever', ['foo' => 'bar', 'baz' => 'boom']));
    }

    public function testStoreForget()
    {
        $store = $this->getStore();
        $this->assertTrue($store->forget('forever'));
    }

    public function testStoreExtendExpiration()
    {
        $store = $this->getStore();
        $store->put('expiration-test', 'two minutes', 2);
        $this->assertTrue($store->extendExpiration('expiration-test', 10));
    }

    public function testStoreTaggable()
    {
        $tags = ['people', 'animals'];
        $store = $this->getStore();
        $repo = $store->tags($tags);
        $this->assertTrue(is_a($repo, 'ElcoBvg\Opcache\Repository'));
    }

    public function testStoreTagFlush()
    {
        $store = $this->getStore();
        $store->tags(['people', 'animals'])->put('tags-test', ['foo' => 'bar', 'baz' => 'boom']);
        $this->assertNull($store->tags(['people', 'animals'])->flush());
    }

    public function testStoreRemember()
    {
        $array = ['foo' => 'bar', 'baz' => 'boom'];
        $repo = $this->getRepository();
        $result = $repo->remember('remember', 2, function () use ($array) {
            return $array;
        });
        $this->assertEquals($array, $result);
    }

    public function testStoreRememberFromCache()
    {
        $this->testStoreRemember();
    }

    protected function getStore()
    {
        return new Store('opcache', sys_get_temp_dir());
    }

    protected function getRepository()
    {
        $store = $this->getStore();
        return new Repository($store, new TagSet($store));
    }
}
