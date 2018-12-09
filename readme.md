# Php simple cache

A quick simple PHP file caching library. Not recommended for production use.
License: MIT

#### Features:

* Optional ability to bypass cache (for debugging)
* Human readable cache keys/paths
* Fallback to old cache on failure to load new data
* Talkative logging (Requires PSR logger)


## Usage

`composer require thybag/php-simple-cache`

```
// Standard
$cache = new \thybag\PhpSimpleCache\Cache;
$result = $cache->get("key");

// static
$result = \thybag\PhpSimpleCache\StaticCache::get("key")
```

#### Methods

 * **read**($cache_key, $max_age, $fallback) - read an item from the cache. Return fallback if item is to old.
 * **write**($cache_key, $data) - write data to cache
 * **delete**($cache_key, $recursivedDelete) - delete item from cache (or entire folder optionally)
 * **has**($key, $max_age) - do we have a no stale version cached
 * **age**($cache_key) - current age of cache item (false if no cache found)
 * **get**($cache_key, $callback, $max_page) - run a callback, caching contents

Settings can be changed via

 * **setLogger**(PSR logger)
 * **setOptions**(['cache_path'=>, 'allow_cache_bypass', 'cache_bypass_keyword', 'default_ttl'])

