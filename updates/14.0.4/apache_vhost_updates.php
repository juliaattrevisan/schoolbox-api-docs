<?php

/* Update apache vhosts with fixes for [SB-5205]
 */

echo shell_exec('../../tools/apache-vhost-schoolbox-14.0.4-updates.sh');
