<?php

/**
 * @copyright Copyright 2025, Schoolbox Pty Ltd
 * @link      https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response\Message;

use Schoolbox\GenericCollection;
use Schoolbox\Learning\Assessment\DataModel\DialogAgent\Response;
use Schoolbox\Mapper as BaseMapper;

/**
 * Mapper for dialog agent response messages.
 * @method Model createModel($idValue = null)
 * @method GenericCollection<Model> findByColumn($columnName, $columnValue, $options = [])
 */
class Mapper extends BaseMapper
{
    /**
     * @inheritdoc
     */
    protected function mapping()
    {
        $this->hasTable('dialog_agent_response_message');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins(
            'dialog_agent_response_id',
            Response\Mapper::class,
            'id',
            null,
            null,
            'dialogAgentResponse',
            'dialogAgentResponse',
        );
        $this->uses('ai_response_id', self::TYPE_STRING);
        $this->uses(
            'role',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            array_map(fn (Role $x) => $x->value, Role::cases()),
        );
        $this->uses(
            'status',
            self::TYPE_STRING,
            self::COLUMN_ENUM,
            null,
            array_map(fn (Status $x) => $x->value, Status::cases()),
        );
        $this->uses('content', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
    }

    /**
     * Find all messages for a given dialog agent response, in order of creation time (ASC)
     *
     * @param int $dialogAgentResponseId
     * @return GenericCollection<Model>
     */
    public function findForResponse(int $dialogAgentResponseId): GenericCollection
    {
        return $this->findByColumn(
            'dialog_agent_response_id',
            $dialogAgentResponseId,
            [
                'order' => [ 'created_at' => BaseMapper::SORT_ORDER_ASC ]
            ]
        );
    }
}
