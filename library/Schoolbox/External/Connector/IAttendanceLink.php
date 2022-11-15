<?php

/**
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector;

use Schoolbox\External\Event\Model;
use Schoolbox\User;

interface IAttendanceLink
{
    /**
     * Generates a link for $user to record Attendance information about $event
     *
     * @param Model $event
     * @param User  $user
     * @return string
     * @throws \InvalidArgumentException
     */
    public function generate(
        \Schoolbox\External\Event\Model $event,
        \Schoolbox\User $user
    ): string;
}
