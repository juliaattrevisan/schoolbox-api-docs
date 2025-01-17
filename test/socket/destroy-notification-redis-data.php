#!/usr/bin/php
<?php

/**
 * This purges all notification cache data from Redis
 * It is used in the acceptance tests to ensure a clean slate
 */

if (posix_getuid() !== 0) {
    // this method can only succeed if the user is root - don't even try otherwise
    echo "Make me a sandwich.\n";
    exit(1);
}

echo "Removing Redis data";
`redis-cli keys "msg:*" | xargs redis-cli del`;
echo "Finished.\n";
exit;
