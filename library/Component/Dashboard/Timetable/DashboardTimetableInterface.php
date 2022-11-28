<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

/**
 * This old area doesn't have a namespace, making this interface name
 * a bit annoying to decide
 */
interface DashboardTimetableInterface
{
    public function execute();
    public function renderTemplate(string $templateHandle);
}