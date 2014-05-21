<?php

/* Rerun apache vhost fixes as now include changes for [SB-4798]
 */

echo shell_exec('../../tools/apache-vhost-schoolbox-14.0-updates.sh');
