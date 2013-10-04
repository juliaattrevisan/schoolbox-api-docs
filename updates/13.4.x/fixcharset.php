<?php

/* This is a special fix for UTF-8 charset in the SQL DB
 * As per SB-2039
 */

echo shell_exec('../../tools/fixCharSet.sh');
