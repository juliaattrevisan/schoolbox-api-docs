<?php

/* Automatically creates the instance ID
 * SB-4586
 */

echo shell_exec('../../tools/update-instance-id.sh -f');
