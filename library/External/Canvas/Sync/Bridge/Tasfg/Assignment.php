<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace External\Canvas\Sync\Bridge\Tasfg;

class Assignment extends \External\Canvas\Sync\Bridge\Assignment
{
    /**
     * @inheritDoc
     */
    public function syncCanvasQuizzes(): bool
    {
        return false;
    }
}
