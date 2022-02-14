<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment;

class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('assessment_view');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('assessment_id', Assessment\DataModel\Mapper::class, 'id', null, null, 'assessmentId', 'assessmentId');
        $this->joins('participant_id', UserMapper::class, 'id', null, null, 'participantId', 'participantId');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'userId', 'userId');
        $this->uses('last_visit', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }
}
