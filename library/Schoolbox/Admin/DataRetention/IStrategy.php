<?php

/**
 * Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\DataRetention;

/**
 * Interface IStrategy
 *
 * Every data retention class must provide this method
 *
 * @package Schoolbox\Admin\DataRetention
 */
interface IStrategy
{
    /**
     * find and remove all soft deleted data
     */
    public function process(): void;
}
