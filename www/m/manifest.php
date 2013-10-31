<?php
// Bootstrap is needed so I can get site_location
require_once __DIR__ . '/../../include/Bootstrap.php';

header('Content-Type: text/cache-manifest');
header('Cache-Control: no-cache');

$isiPad   = (bool) strpos($_SERVER['HTTP_USER_AGENT'],'iPad');
$isiPod   = (bool) strpos($_SERVER['HTTP_USER_AGENT'],'iPod');
$isiPhone = (bool) strpos($_SERVER['HTTP_USER_AGENT'],'iPhone');
$hashes = '';

echo "CACHE MANIFEST\n";

$includes = array(
    '/../templates/core/include/head.html',
    '/../templates/core/include/hercules.html',
    '/../templates/core/include/loadjs.html',
    '/../templates/core/include/loadstyle.html',
    '/../templates/core/include/main.html',
    '/../templates/core/include/js/cache.html',
    '/../templates/core/include/js/init.html',
    '/../templates/core/include/js/loadfile.html',
    '/../templates/core/include/js/menu.html',
    '/../templates/core/include/js/render.html',
    '/../templates/core/include/js/variables.html'
);
foreach($includes as $file){
    if (is_readable('.'.$file)) {
        $hashes .= md5_file('.'.$file);
    }
}

$location = Config::get('site_location');
$files = array(
    # Styles
    "/css/$location/mchrome.css",
    # Scripts
    '/m/inc/js/libs/zepto.min.js',
    '/m/inc/js/libs/iscroll-lite.js',
    # Images
    "/images/$location/favicon.ico",
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
//echo '# ' . date("n/j/Y h:i");
