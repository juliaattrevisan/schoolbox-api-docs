<?php

/* Automatically updates the apache vhosts with the following fixe
 * SB-4397
 */

echo shell_exec('../../tools/apache-vhost-schoolbox-14.0-updates.sh');
