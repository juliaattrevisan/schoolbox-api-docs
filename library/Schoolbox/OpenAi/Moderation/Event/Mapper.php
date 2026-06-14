<?php

/**
 * @author      Zac McWilliam <zac.mcwilliam@schoolbox.education>
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\OpenAi\Moderation\Event;

use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\UserMapper;

class Mapper extends \Schoolbox\Mapper
{
    /**
     * @inheritDoc
     */
    protected function mapping(): void
    {
        $this->hasTable('ai_moderation_event');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('ai_session_id', self::TYPE_STRING);
        $this->uses('target_ai_session_id', self::TYPE_STRING);
        $this->uses('status', self::TYPE_STRING, self::COLUMN_ENUM, null, [...array_column(Status::cases(), 'value'), null]);
        $this->uses('moderation_details', self::TYPE_STRING);
        $this->joins('reviewed_by', UserMapper::class, 'id', null, null, 'reviewedBy', 'reviewedBy');
        $this->uses('reviewed_at', self::TYPE_DATETIME);
        $this->joins('resolved_by', UserMapper::class, 'id', null, null, 'resolvedBy', 'resolvedBy');
        $this->uses('resolved_at', self::TYPE_DATETIME);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
    }


    /**
     * @inheritdoc
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function getSelectString(): string
    {
        return "
            SELECT      {$this->getColumnString()},
                        {$this->getJoinColumnString('reviewedBy', 'rv')},
                        {$this->getJoinColumnString('resolvedBy', 'rs')}
            FROM        {$this->getTable()}
            LEFT JOIN   {$this->getJoinSql('reviewedBy', '', 'rv', true)}
            LEFT JOIN   {$this->getJoinSql('resolvedBy', '', 'rs', true)}
        ";
    }


    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param \PDOStatement $statement
     * @return GenericCollection<Model>
     * @throws \Schoolbox\Mapper\DefinitionException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            /** @var Model $model */
            $model = $this->hydrate($row);

            /** @var User|null $reviewedBy */
            $reviewedBy = $this->hydrateJoin($row, 'reviewedBy', 'rv');
            $model->setReviewedBy($reviewedBy);

            /** @var User|null $resolvedBy */
            $resolvedBy = $this->hydrateJoin($row, 'resolvedBy', 'rs');
            $model->setResolvedBy($resolvedBy);

            $collection->add($model);
        }

        return $collection;
    }
}
