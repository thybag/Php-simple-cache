<?php
namespace thybag\PhpSimpleCache;

use \Exception as Exception;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;

/**
 * Php Simple Cache - quick file caching helper
 */
class Cache implements LoggerAwareInterface
{
	use LoggerAwareTrait;

	// Cache storage
	protected $cache_path;
	protected $default_ttl;
	// Cache bypass
	protected $allow_cache_bypass;
	protected $cache_bypass_keyword;

	/**
	 * Setup new cache helper
	 * 
	 * @param array $options
	 */
	public function __construct($options = [])
	{
		$this->setOptions($options);
	}

	/**
	 * Update settings
	 * 
	 * @param array $options
	 */
	public function setOptions($options = [])
	{
		// Set options
		$this->cache_path = isset($options['cache_path']) ? $options['cache_path'] : $this->getDefaultCachePath();
		$this->allow_cache_bypass = isset($options['allow_cache_bypass']) ? $options['allow_cache_bypass'] : false;
		$this->cache_bypass_keyword = isset($options['cache_bypass_keyword']) ? $options['cache_bypass_keyword'] : 'disablecache';
		$this->default_ttl = isset($options['default_ttl']) ? $options['default_ttl'] : false; // no limit
	}

	/**
	 * Work out a semi-sane place to write cache files if none is specified.
	 * @return $path
	 */
	private function getDefaultCachePath()
	{
		// Use composer to guess the app root
		$reflection = new \ReflectionClass(\Composer\Autoload\ClassLoader::class);
		$app_root = dirname(dirname(dirname($reflection->getFileName())));
		return $app_root.'/tmp';
	}

	/**
	 * Log - pass to log handler or do nothing.
	 * 
	 * @param  string $message message to log
	 * @return boolean Log success
	 */
	protected function log($message)
	{
		if ($this->logger) {
			$this->logger->debug($message);
			return true;
		}

		// No logger?
		return false;
	}

	/**
	 * Find out if we are trying to bypass cache & if we allow this.
	 * @return boolean is cache to be bypassed
	 */
	protected function bypassingCache()
	{
		return ($this->allow_cache_bypass === true && isset($_GET[$this->cache_bypass_keyword]));
	}

	/**
	 * Read cache file
	 *
	 * @param $key - unique identifier for cache (use of .'s will create additional folders')
	 * @param $cachetime - Time in minutes cache needs to be newer than.
	 * @param $fallback - if data is to old
	 *
	 * @return cache data|false
	 */
	public function read($key, $cachetime = null, $fallback = false)
	{
		$path = $this->get_cache_path($key);

		if($this->_check($path, $cachetime))
		{
			return $this->_read($path);
		}

		$this->log("[cache][read][fail] {$key}");
		return $fallback;
	}

	/**
	 * Write cache file
	 *
	 * @param $key - unique identifier for cache (use of .'s will create additional folders')
	 * @param $payload - data to store
	 *
	 * @return success:true|false
	 */
	public function write($key, $payload)
	{
		$path = $this->get_cache_path($key);
		$success = $this->_write($path, $payload);

		$this->log("[cache][write][".($success ? 'true' : 'false')."] {$key}");
		return $success;
	}

	/**
	 * Delete
	 *
	 * @param $key - unique identifier for cache (use of .'s will create additional folders')
	 *
	 * @return success:true|false
	 */
	public function delete($key, $deletDir = false)
	{
		$path = $this->get_cache_path($key);

		$maybedir = substr($path,0,strlen($path)-5);
		if (!empty($maybedir) && is_dir($maybedir) && $deletDir) {
			$this->log("[cache][delete][folder] $key");
			return $this->rrmdir($maybedir);
		}
		if (file_exists($path)) {
			$this->log("[cache][delete][file] $key");
			return unlink($path);
		}

		return true;
	}

	/**
	 * Get age of cache item
	 * 
	 * @param  string key
	 * @return int | false
	 */
	public function age($key)
	{
		$path = $this->get_cache_path($key);

		if (file_exists($path)) {
			return (time() - filemtime($path));
		}
		
		return false;
	}

	/**
	 * do we have this key?
	 * 
	 * @param  string key
	 * @return int | false
	 */
	public function has($key, $cachetime = null)
	{
		$path = $this->get_cache_path($key);
		return $this->_check($path, $cachetime);
	}

	/**
	 * rrmdir - clear directory tree
	 * https://stackoverflow.com/questions/9760526/in-php-how-do-i-recursively-remove-all-folders-that-arent-empty#9760541 
	 * @param  [type] $dir [description]
	 * @return [type]      [description]
	 */
	protected function rrmdir($dir) {
		if (is_dir($dir)) {
			$objects = scandir($dir);
			foreach ($objects as $object) {
				if ($object != "." && $object != "..") {
					if (filetype($dir."/".$object) == "dir") $this->rrmdir($dir."/".$object); else unlink($dir."/".$object);
				}
			}
			reset($objects);
			return rmdir($dir);
		}
		return true;
	}

	/**
	 * get data from cache - or if unable to reload data using callback
	 *
	 * @param $key - unique identifier for cache (use of .'s will create additional folders')
	 * @param $callback - function that will load required data if cache has expired/doesn't exist
	 * @param $cachetime - Time in minutes cache needs to be newer than.
	 *
	 * @return cache data | callback data | expired cache data | false (if unable to fail over)
	 */
	public function get($key, $callback, $cachetime = null)
	{
		$path = $this->get_cache_path($key);

		// If cache is all happy, lets just return it
		if($this->_check($path, $cachetime))
		{
			$this->log("[cache][get][cached] $key");
			return $this->_read($path);
		}

		// Cache is bad, so lets reload the data
		try	{
			$data = $callback();
		}
		catch (\Exception $e) {
			// Don't recover if in cache bypass mode
			if ($this->bypassingCache())
			{
				$this->log("[cache][get][fatal error - unable to load cache in bypass mode] $key : " . $e->getMessage());
				print_r($e);
				die();
			}
			$this->log("[cache][get][fail - return from expired cache] $key");
			return $this->_force_read($path);
		}
		
		// if data returned is no good (or an error value)
		if ($data === null || $data === false)
		{	
			$this->log("[cache][get][fail - return from expired cache] $key");
			return $this->_force_read($path);
		}

		$this->log("[cache][get][un-cached] $key");
		// else write new data & return it
		$success = $this->_write($path, $data);

		return $data;
	}

	/**
	 * Generate usable filepath from key
	 * 
	 * @param $key Unique name for cache
	 * @return $path valid path name
	 */
	private function get_cache_path($key)
	{
		// trim it
		$key = trim($key);

		// Clean it 
		// - convert spaces & '/' to -
		// - convert . to folders
		// - remove unwanted chars,
		// - append json
		$key = str_replace(array(' ','/'), '-', $key);
		$key = str_replace('.', '/', $key);
		$key = preg_replace("/[^A-Za-z0-9_\-\/]/", '', $key).'.json';

		// return path to cacheFile.
		return rtrim($this->cache_path.'/') . '/' . $key;
	}

	/**
	 * Force read.
	 * If something went wrong repopulating a cache, attempt to fall back to expired cache if possible. 
	 * (then back away for cache period in order to give issue chance to resolve itself)
	 * 
	 * @param $path - Full path to file.
	 * @return $data | false
	 */
	private function _force_read($path)
	{
		if(file_exists($path)){
			// touch file so we dont check again until next expire)
			touch($path); 
			return $this->_read($path);
		}

		// nothing we can do, can't reload and we have no cache.
		return false;
	}


	/**
	 * Check cache is Valid (exists / isn't expired / isn't disabled)
	 * true = valid, false = bad
	 * 
	 * @param $path - Full path to file.
	 * @return $data | false
	 */
	private function _check($path, $cache_time = null)
	{
		// If not TTL set, fallback to default
		if ($cache_time == null) {
			$cache_time = $this->default_ttl;
		}

		// if the file exists, and disable cache is NOT enabled
		if ($cache_time === false) {
			// ignore cache time, so long as the file exists and disablecache is turned off its fine
			return (file_exists($path) && !$this->bypassingCache());
		}
		else
		{
			// ensure even cache has not expired
			return (file_exists($path) && !$this->bypassingCache() && (time() - filemtime($path)) < ($cache_time*60));
		}
	}

	/**
	 * Write data to file
	 * 
	 * @param $path - Valid cache file
	 * @param $payload - Data to store
	 * @return success: true|false
	 */
	private function _write($path, $payload)
	{
		//Get cache folder.
		$f = pathinfo($path);
		$folder = $f['dirname'].'/';
		
		// Ensure folder exists - if not, create it.
		if (!file_exists($folder)) mkdir($folder,0777,true);

		// Write payload
		$success = file_put_contents($path, json_encode($payload));

		// If everything worked, chmod and return true
		if($success !== false){
			chmod($path, 0777);
			return true;
		}
		
		// somthing went wrong :(
		return false;
	}

	/**
	 * read data from file
	 * 
	 * @param $path - Valid cache file
	 * @return data | falase
	 */
	private function _read($path)
	{
		$data = file_get_contents($path);

		if($data !== false){
			return json_decode($data, true);
		}

		return false;
	}
}
