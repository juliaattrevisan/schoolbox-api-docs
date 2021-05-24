<?php

// execute this before any of the new tables are created
exec(__DIR__  . '/../../tools/schema/collation2.sh', $output, $return);
foreach ($output as $line) {
    print $line . "\n";
}
exit($return);
