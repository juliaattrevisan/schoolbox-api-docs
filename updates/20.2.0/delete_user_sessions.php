#!/usr/bin/php
<?php

passthru('php ' . __DIR__ .  '/../../tools/delete_user_sessions.php', $returnVar);
exit($returnVar);
