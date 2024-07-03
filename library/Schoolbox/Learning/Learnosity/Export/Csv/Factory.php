<?php

/**
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Learning\Learnosity\Export\Csv;

use Schoolbox\Learning\Learnosity\Export\Csv\IStrategy;

class Factory
{
    /**
     * Each genus of learnosity question type needs its own special attention
     *
     * @param string $questionType
     * @return IStrategy|null
     */
    public function produce(string $questionType): ?IStrategy {

    }
}
