<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DialogAgent\Lookup;

/**
 * Helpfulness levels for dialog agent activities
 *
 * @see /library/Schoolbox/Learning/Assessment/templates/dialogAgent/vue/configure/dacTypes.ts
 */
enum Helpfulness: string
{
    case EXPLORATORY = 'exploratory';
    case GUIDED = 'guided';
    case DIRECTED = 'directed';

    /**
     * The human-readable label for the helpfulness level
     *
     * @return string
     */
    public function label(): string
    {
        // This is a valid use of $this:
        // see https://www.php.net/manual/en/language.enumerations.methods.php
        // phpcs:ignore
        return match ($this) {
            Helpfulness::EXPLORATORY => 'Exploratory',
            Helpfulness::GUIDED => 'Guided',
            Helpfulness::DIRECTED => 'Directed',
        };
    }

    /**
     * The prompt that will be used to instruct the AI on the helpfulness level
     *
     * @return string
     */
    public function prompt(): string
    {
        // TODO

        // This is a valid use of $this:
        // see https://www.php.net/manual/en/language.enumerations.methods.php
        // phpcs:ignore
        return $this->label();
    }
}
