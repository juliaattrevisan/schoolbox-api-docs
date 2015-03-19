<?php
// Bootstrap is needed so I can get site_location
require_once __DIR__ . '/../../include/Bootstrap.php';

// Is caching switched off? 404 (thus ignoring application cache)
if (!Config::get('mobile_application_cache_enabled')) {
    header(':', null, 404);
    exit;
}

$location = Config::get('site_location');

header('Content-Type: text/cache-manifest');
header('Cache-Control: no-cache');
header('Expires: Thu, 01 Dec 1969 16:00:00 GMT');

$isiPad   = (bool) strpos($_SERVER['HTTP_USER_AGENT'],'iPad');
$isiPod   = (bool) strpos($_SERVER['HTTP_USER_AGENT'],'iPod');
$isiPhone = (bool) strpos($_SERVER['HTTP_USER_AGENT'],'iPhone');
$hashes = '';

echo "CACHE MANIFEST\n";

$includes = array(
    '/templates/m/core/include/_hercules.html',
    '/templates/m/core/include/head.html',
    '/templates/m/core/include/loadjs.html',
    '/templates/m/core/include/loadstyle.html',
    '/templates/m/core/include/main.html',
    '/templates/m/core/include/js/cache.html',
    '/templates/m/core/include/js/init.html',
    '/templates/m/core/include/js/loadfile.html',
    '/templates/m/core/include/js/menu.html',
    '/templates/m/core/include/js/qtap.html',
    '/templates/m/core/include/js/render.html',
    '/templates/m/core/include/js/variables.html',
    "/templates/$location/m/hercules.html"
);
foreach($includes as $file){
    $actual_file = __DIR__.'/../..'.$file;
    if (is_readable($actual_file)) {
        $hashes .= md5_file($actual_file);
    }
}

$files = array(
    # Styles
    "/css/$location/mchrome.css",
    # Scripts - will eventually bower all of these.
    '/javascript/components/jquery/jquery.min.js',
    '/javascript/iscroll/iscroll.js',
    # Images
    "/images/$location/favicon.ico",
    "/images/$location/hercules.png",
    "/images/$location/apple-touch-icon-precomposed.png",
    # Fonts
    '/m/inc/font/sil.ttf'
);

echo "\nCACHE:\n";
foreach ($files as $file) {
    $actual_file = __DIR__.'/..'.$file;
    if (is_readable($actual_file)) {
        echo $file . "\n";
        $hashes .= md5_file($actual_file);
    }
}

echo "\nFALLBACK:\n";
echo "# / /"."\n";

echo "\nNETWORK:\n";
echo '*' . "\n";

echo "\n# " . md5($hashes) . "\n";

// FOR TESTING. Ensures we'll get an appcache update (every minute).
//echo '# ' . date("n/j/Y H:i");
