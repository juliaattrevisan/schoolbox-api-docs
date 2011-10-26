<?php
$myFile = "../../../www/conf/conf.php";
$fh = fopen($myFile, 'a') or die("can't open file to write Dsn to");
$stringData = "<?php define(\"SQL_dsn\", \"mysql:host=\".SQL_host.\";dbname=\".SQL_db);?>";
fwrite($fh, $stringData);
fclose($fh);
?>