#!/usr/bin/php
<?php

passthru('php ' . __DIR__ .  '/../../tools/plagscan/insert-missing-plagscan-document.php -p', $returnVar);
exit($returnVar);
