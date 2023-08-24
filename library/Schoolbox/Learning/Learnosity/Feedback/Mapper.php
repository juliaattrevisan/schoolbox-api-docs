<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Feedback;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Quiz\Attempt\Mapper as AttemptMapper;

/**
 * Class Mapper
 *
 * @method Model createModel($idValue = null)
 * @method Model findById($identifierValue)
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 * @method Model[]|GenericCollection findByColumn($columnName, $columnValue, $options = [])
 * @method Model[]|GenericCollection createCollection($contents = [])
 */
class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('quiz_feedback');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('attempt_id', AttemptMapper::class, 'attempt_id', null, null, 'attempt', 'attempt');
        $this->uses('comment', self::TYPE_STRING);
        $this->uses('learnosity_reference', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }


    /**
     * Find all the comments for this attempt,
     * indexed by learnosity reference
     */
    public function findForAttempt(int $attemptId): array
    {
        $feedbacks = $this->findByColumn('attempt_id', $attemptId)
            ->indexBy('learnosityReference');

        $comments = [];

        foreach ($feedbacks as $feedback) {
            /* Perform oembed replacements in advance because this gets
             * injected into the dom directly
             */
            $feedback->setComment(\Scrub::html($feedback->getComment()));

            $comments[$feedback->getLearnosityReference()] = $feedback->getComment();
        }

        return $comments;
    }


    /**
     * Find all the comments for this set of learnosity references (response ids)
     */
    public function findByLearnosityReferences(array $learnosityReferences): array
    {
        $feedbacks = $this->findByColumn('learnosity_reference', $learnosityReferences)
            ->indexBy('learnosityReference');

        $comments = [];

        foreach ($feedbacks as $feedback) {
            /* Perform oembed replacements in advance because this gets
             * injected into the dom directly
             */
            $feedback->setComment(\Scrub::html($feedback->getComment()));

            $comments[$feedback->getLearnosityReference()] = $feedback->getComment();
        }

        return $comments;
    }
}
