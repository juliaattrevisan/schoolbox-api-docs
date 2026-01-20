<?php

/**
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

passthru('php ' . __DIR__ .  '/../../tools/set-plan.php --reset', $returnVar);
exit($returnVar);
