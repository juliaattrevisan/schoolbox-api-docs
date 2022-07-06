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
<html>
<head>
    <title>Your browser is no longer supported</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Content-Language" content="en-au" />
    <link rel="stylesheet"
          type="text/css"
          href="<?php echo Scrub::text($cdnPrefixGenerator->generate());?>/css/core/core.css?v=<?php echo Scrub::text($config->get('site_version'));?>" />
</head>
<body>
    <div class="row island">
        <h1><?php echo Scrub::text($config->get('title'));?> no longer supports Internet Explorer</h1>
        <section class="content" >
            <p>
                If you are using Windows 7 or 8 you are able to upgrade to Microsoft Edge,
                or you can install an alternate browser such as Mozilla Firefox or Google Chrome.
            </p>
            <ul>
                <li><a href="https://www.microsoft.com/en-us/edge">Microsoft Edge Download Site</a></li>
                <li><a href="https://www.mozilla.org/firefox/">Mozilla Firefox Download Site</a></li>
                <li><a href="https://www.google.com/chrome/">Google Chrome Download Site</a></li>
            </ul>
        </section>
    </div>

  <div id="footer" class="clearfix">
    <ul>
    <li class="last"><?php echo date('Y');?> &copy; Copyright <a href="<?php echo $config->get('school_address');?>"><?php echo $config->get('school_name');?></a></li>
    </ul>
  </div>

</body>
</html>
