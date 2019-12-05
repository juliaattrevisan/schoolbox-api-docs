<?php
require_once __DIR__ . '/../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();
?>
<html>
<head>
    <title>Your browser is no longer supported</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Content-Language" content="en-au" />
    <script>
      //  if (!Function('/*@cc_on return document.documentMode<=10@*/')()) {
        //    window.location.href = '/';
        //}
    </script>
    <link rel="stylesheet" type="text/css" href="/css/<?php echo Scrub::text(Config::get('site_location'));?>/chrome-foundation.css?v=<?php echo Scrub::text(Config::get('site_version'));?>" />
</head>
<body>
    <div class="row island">
        <h1><?php echo Scrub::text(Config::get('title'));?> no longer supports Internet Explorer 10 and below</h1>
        <section class="content" >
            <p>
                If you are using Windows 7 you are able to upgrade to Internet Explorer 11,
                or you can install an alternate browser such as Mozilla Firefox or Google Chrome.
            </p>
            <ul>
                <li><a href="http://www.microsoft.com/windows/en-au/xp/default.aspx">Windows XP End Of Support announcement</a></li>
                <li><a href="https://support.microsoft.com/en-au/help/22882/windows-vista-end-of-support">Windows Vista End Of Support announcement</a></li>
                <li><a href="http://windows.microsoft.com/en-au/internet-explorer/download-ie">Internet Explorer Update Site</a></li>
                <li><a href="http://www.mozilla.org/firefox/">Mozilla Firefox Download Site</a></li>
                <li><a href="http://www.google.com/chrome/">Google Chrome Download Site</a></li>
            </ul>
        </section>
    </div>

  <div id="footer" class="clearfix">
    <ul>
    <li class="last"><?php echo date('Y');?> &copy; Copyright <a href="<?php echo Config::get('school_address');?>"><?php echo Config::get('school_name');?></a></li>
    </ul>
  </div>

</body>
</html>
