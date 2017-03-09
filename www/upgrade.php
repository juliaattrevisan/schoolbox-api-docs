<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/../library/Bootstrap.php';
?>
<html>
<head>
    <title>Your browser is no longer supported</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta http-equiv="Content-Language" content="en-au" />
    <script>
        if (!Function('/*@cc_on return document.documentMode<=10@*/')()) {
            window.location.href = '/';
        }
    </script>
    <link rel="stylesheet" type="text/css" media="screen" href="/css/<?php echo Scrub::text(Cfg::get('site_location'));?>/chrome.css" />
</head>
<body>

  <div id="container" class="login" style="margin-left: 0">
    <div id="content" class="content clearfix">

    <h1><?php echo Scrub::text(Cfg::get('title'));?> no longer supports Internet Explorer 10 and below</h1>

    <p>
        If you are using Windows Vista or Windows 7 you are able to upgrade to a more
        recent Internet Explorer version, or you can install an alternate
        browser such as Mozilla Firefox or Google Chrome.
    </p>
    <ul>
        <li><a href="http://www.microsoft.com/windows/en-au/xp/default.aspx">Windows XP End Of Support announcement</a></li>
        <li><a href="http://windows.microsoft.com/en-au/internet-explorer/download-ie">Internet Explorer Update Site</a></li>
        <li><a href="http://www.mozilla.org/firefox/">Mozilla Firefox Download Site</a></li>
        <li><a href="http://www.google.com/chrome/">Google Chrome Download Site</a></li>
    </ul>

    </div>
  </div>

  <div id="footer">
    <ul>
    <li class="last"><?php echo date('Y');?> &copy; Copyright <a href="<?php echo Cfg::get('school_address');?>"><?php echo Cfg::get('school_name');?></a></li>
    </ul>
  </div>

</body>
</html>
