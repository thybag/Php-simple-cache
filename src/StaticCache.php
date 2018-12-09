<?php

namespace thybag\PhpSimpleCache;

/**
 * Php Simple Cache - singleton version of Cache
 */
class StaticCache
{
	// Cache singleton
	protected static $cache;

	/**
	 * Get cache instance of make new one
	 * 
	 * @return [type] [description]
	 */
	public static function instance()
	{
		return static::$cache ? static::$cache : static::fresh();
	}

	/**
	 * Set up a new instance of cache
	 * 
	 * @return Cache instance
	 */
	public static function fresh($options = [])
	{
		return static::$cache = new Cache($options);
	}

	/**
	 * Path methods to instance
	 */
	public static function __callStatic($method, $args)
	{	
		return static::instance()->$method(...$args);
	}
}