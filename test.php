<?php
include 'vendor/autoload.php';


\thybag\PhpSimpleCache\StaticCache::fresh(['allow_cache_bypass' => true]);

thybag\PhpSimpleCache\StaticCache::write("key.test", "wot");
print_r(
thybag\PhpSimpleCache\StaticCache::get('name', function(){
	return ['HEllo'=>4];
}, 100)

);