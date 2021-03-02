#!/usr/bin/php
<?php

passthru('php ' . __DIR__ .  '/../../tools/kami/insert-missing-submission-file-kami.php -p', $returnVar);
exit($returnVar);
