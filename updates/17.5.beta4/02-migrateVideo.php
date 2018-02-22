<?php

// Run the video migrator
exec(__DIR__  . '/../../tools/video/migrator.php', $output, $return);
foreach($output as $line) {
    print $line."\n";
}
exit($return);
