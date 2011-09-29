<?php 
$output = shell_exec("sudo -u root -S ../tools/updater.sh"); 
print_r("$output");
?>
