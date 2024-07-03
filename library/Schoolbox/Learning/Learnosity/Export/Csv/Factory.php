<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Export\Csv;

class Factory
{
    /**
     * Each genus of learnosity question type needs its own special attention
     *
     * @param string $questionType
     * @return IStrategy|null
     */
    public function produce(string $questionType): ?IStrategy {
        switch ($questionType) {
            case 'mcq': return new Mcq();
            case 'choicematrix': return new Choicematrix();
            case 'shorttext': return new Shorttext();
            default: return null;
        }
    }
}
