<?php

use Schoolbox\Cdn\PrefixGenerator;
use Schoolbox\IConfigLoader;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/** @var IConfigLoader $config */
$config = $kernel->get('config');

/** @var PrefixGenerator $cdnPrefixGenerator */
$cdnPrefixGenerator = $kernel->get('cdnPrefixGenerator');
?>
<html lang="en">

<head>
    <title>Your browser is no longer supported</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Content-Language" content="en-au" />
    <link rel="stylesheet" type="text/css" href="<?php echo Scrub::text($cdnPrefixGenerator->generate()); ?>/css/core/core.css?v=<?php echo Scrub::text($config->get('site_version')); ?>" />
    <style>
        a {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="row island">
        <h1><?php echo Scrub::text($config->get('title')); ?> no longer supports your browser.</h1>
        <section class="content">
            <p>
                Please update your browser to the latest of one listed below.
                If you are unable to update your browser please contact your IT support.
            </p>
            <ul>
                <li><a href="https://www.google.com/chrome/">Download Google Chrome</a></li>
                <li><a href="https://www.microsoft.com/en-us/edge">Download Microsoft Edge</a></li>
                <li><a href="https://www.mozilla.org/en-US/firefox/new/">Download Mozilla Firefox</a></li>
            </ul>
            <h4>Minimum supported browser versions:</h4>
            <ul>
                <li>Apple Safari: 16.4</li>
                <li>Google Chrome: 112</li>
                <li>Microsoft Edge: 112</li>
                <li>Mozilla Firefox: 112</li>
            </ul>
        </section>
    </div>

    <div id="footer" class="clearfix">
        <ul>
            <li class="last"><?php echo date('Y'); ?> &copy; Copyright <a href="<?php echo $config->get('school_address'); ?>"><?php echo $config->get('school_name'); ?></a></li>
        </ul>
    </div>

</body>

</html>
