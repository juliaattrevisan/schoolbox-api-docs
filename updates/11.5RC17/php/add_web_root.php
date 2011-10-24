<?php
$myFile = "../../www/conf/conf.php";
$fh = fopen($myFile, 'a') or die("can't open file to write Dsn to");
$stringData = "<?php define(\"WEB_root\", SITE_root . '/www');?>";
fwrite($fh, $stringData);
fclose($fh);
?>