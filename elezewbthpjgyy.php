<?php

date_default_timezone_set('America/New_York');
define('PR_PUB_INTEGRATION_CACHE_EXPIRATION_TIME_IN_SECONDS', 60*60);
define('INTEGRATION_BASE_URL', 'http://prscripts.com/d/?resource=pubJS');
define('CURL_TIMEOUT', 5);
define('DOMAIN_ID', "316416");
define('SERVER_PROTOCOL', 'HTTP/1.1');
define('SECRET_KEY', "1058628b0703204edd8b9f3d23e755d413e70e4516ca4d6a2449663cf082eeb7");
define('CREATED_TIMESTAMP', "1535033480");

/**
* The key to store the script in cache, or the name of the local cache file.
*/
define('CACHEKEY', 'prCachedPRIntegrationScriptFor316416');
/**
* @var          boolean         Whether or not the plugrush integration script should be cached
*                          in either Memcached, Memcache or in a local file. (Cache will be updated remotely when necessary)
*/
$cache = true;

if (isset($_GET['created'])) {
output(CREATED_TIMESTAMP);
}


$generatedHash = hash('sha256', SECRET_KEY . getIfExists($_GET, 'timestamp'));
$clearCache = false;
if (getIfExists($_GET, 'timestamp') > strtotime('-1 day') && $generatedHash == getIfExists($_GET, 'clearCacheHash')) {
$clearCache = true;
}

if (!$clearCache && $cache) {
$cachedScript = getCachedScript();
if ($cachedScript) {
    output($cachedScript);
}
}

$currentTimestamp = time();
$adblockSafeHash = hash('sha256', SECRET_KEY . $currentTimestamp);
$urlQueryParams = "&t=" . $currentTimestamp . "&i=" . $adblockSafeHash;

$userAgent = '';
if (isset($_SERVER['HTTP_USER_AGENT']) && !empty($_SERVER['HTTP_USER_AGENT'])) {
$userAgent = $_SERVER['HTTP_USER_AGENT'];
}
$curl = curl_init();
curl_setopt_array($curl, array(
CURLOPT_URL => INTEGRATION_BASE_URL . $urlQueryParams,
CURLOPT_RETURNTRANSFER => true,
CURLOPT_TIMEOUT => CURL_TIMEOUT,
CURLOPT_USERAGENT => $userAgent,
CURLOPT_REFERER => !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "fantasy.sundfjord.com",
));

$response = curl_exec($curl);

if ($cache && curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200 && isValidDomain($response)) {
setCachedScript($response);
}

output($response);

function getCacheExtension()
{
if (class_exists('Memcached')) {
$memcached = new Memcached();
$serverExists = $memcached->getServerList();
if (!$serverExists) {
$memcached->addServer('localhost', 11211);
}
return $memcached;
} else if (class_exists('Memcache')) {
$memcache = new ExtendedMemcache();
$serverExists = $memcache->getextendedstats();
if (!$serverExists) {
$memcache->addServer('localhost', 11211);
}
return $memcache;
} else {
return new WriteFile();
}
}

function setCachedScript($content)
{
$cache = getCacheExtension();

return $cache->set(CACHEKEY, $content, PR_PUB_INTEGRATION_CACHE_EXPIRATION_TIME_IN_SECONDS);
}

function getCachedScript()
{
$cache = getCacheExtension();
return $cache->get(CACHEKEY);
}

function output($script)
{
    header('Content-Type: application/javascript');
    echo $script;
    die();
}

function isValidDomain($response)
{
    if (!preg_match("/#domainIdString-(\d+)-domainIdString#/", $response, $matches)) {
        return false;
    }
    if (!isset($matches[1]) || $matches[1] != DOMAIN_ID) {
        return false;
    }
    return true;
}


class WriteFile
{
function set($filename, $content, $expire)
{
try {
$file = fopen("./$filename",'w');
fwrite($file, $content);
return fclose($file);
} catch (Exception $e) {
return false;
}
}

function get($filename)
{
try {
if (!file_exists("./$filename")) {
    return false;
}
$content = file_get_contents("./$filename");
if (!$content) {
return false;
}
if ($this->isFileExpired($filename)) {
unlink("./$filename");
return false;
}
return $content;
} catch (Exception $e) {
return false;
}
}

function isFileExpired($filename)
{
return time() - filemtime("./$filename") > PR_PUB_INTEGRATION_CACHE_EXPIRATION_TIME_IN_SECONDS;
}
}

function getIfExists($input, $key)
{
    return isset($input[$key]) ? $input[$key] : null;
}

class ExtendedMemcache extends Memcache
{
public function set ($key, $var, $expire)
{
return parent::set($key, $var, 0, $expire);
}
}
