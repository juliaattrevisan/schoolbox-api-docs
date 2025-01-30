#!/usr/bin/php
<?php

/**
 * This purges all notification cache data from Redis
 * It is used in the acceptance tests to ensure a clean slate
 */

echo "Removing Redis data";
`redis-cli keys "msg:*" | xargs redis-cli del`;
echo "Finished.\n";
exit;
