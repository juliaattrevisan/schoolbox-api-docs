<?php

/* Run apache vhost fixes for [SB-6208]
 */

echo shell_exec('../../tools/apache-vhost-schoolbox-14.5-updates.sh');
