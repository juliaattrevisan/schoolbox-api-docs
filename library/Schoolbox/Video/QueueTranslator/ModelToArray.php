<?php

/**
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Video\QueueTranslator;

use Schoolbox\Video\Queue;

/**
 * Translator for translating a queue model to an array for JSON output.
 */
class ModelToArray
{
    /**
     * @param Queue $queue
     * @return array
     */
    public function __invoke(Queue $queue): array
    {
        return [
            'id'       => $queue->getId(),
            'hash'     => $queue->getHash(),
            'status'   => $queue->getStatus(),
            'duration' => $queue->getDuration(),
        ];
    }
}
