<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Schoolbox\GenericCollection;
use Schoolbox\UserMapper;
use Schoolbox\Learning\Assessment;

/**
 * Class Mapper
 *
 * @method Model createModel($id=null)
 * @method Model[]|GenericCollection findByColumnList($columnList, array $options = [])
 * @method Model findOneByColumnList($columnList, array $options = [])
 */
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
        $this->uses('assessment_id', self::TYPE_INTEGER);
        $this->uses('participant_id', self::TYPE_INTEGER);
//        $this->uses('user_id', self::TYPE_INTEGER);
//        $this->joins('assessment_id', Assessment\IAssessable::class, 'id', null, null, 'assessment', 'assessment');
//        $this->joins('participant_id', UserMapper::class, 'id', null, null, 'participant', 'participant');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('last_visit', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }
}
