<?php 
$output = shell_exec("sudo -u root -S ../tools/updater.sh"); 
echo "<pre>".$output."</pre>";
?>
