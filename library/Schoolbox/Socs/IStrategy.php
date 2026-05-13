<?php

/**
 * @author    Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright Copyright 2026, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Socs;

/**
 * SOCS import strategy contract.
 *
 * Each SOCS import step (fixtures, teams, results, preparation, cleanup, etc.)
 * implements this interface and can be executed by the SOCS factory pipeline.
 */
interface IStrategy
{
    /**
     * Execute this portion of the SOCS import.
     */
    public function import(): void;
}
