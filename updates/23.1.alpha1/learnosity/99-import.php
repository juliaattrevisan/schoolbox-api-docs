#!/usr/bin/php
<?php

/**
 * Migrate all known quizzes into learnosity land
 *
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

/* FIXME but...this requires credentials in order to proceed
 *  and the credentials aren't populated until afterwards.
 *  That makes migration a two-step process, not one.
 */
passthru('php ' . __DIR__ .  '/../../../tools/learnosity/import.php --perform');
exit();
