<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.com.au>
 * @copyright   Copyright 2022, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Assessment\Views;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

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

        /* TODO Misleading naming SA-14957
         */
        $this->joins('participant_id', UserMapper::class, 'id', null, null, 'participant', 'participant');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');

        $this->uses('last_visit', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('feedback_was_visible', self::TYPE_BOOLEAN);
    }


    /**
     * Return the first part of the string required to select from this mapper
     *
     * @return string
     */
    protected function getSelectString(): string
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('participant', 'participant')},
                {$this->getJoinColumnString('user', 'user')}
        ";
    }


    /**
     * The Next Pieces
     *
     * @return string
     * @throws DefinitionException
     */
    protected function getFromString(): string
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('participant', null, 'participant', true)}
            LEFT JOIN {$this->getJoinSql('user', null, 'user', true)}
        ";
    }


    /**
     * @inheritdoc
     *
     * @param \PDOStatement $statement
     * @return GenericCollection
     * @throws DefinitionException
     * @throws Exception
     * @throws \UnexpectedValueException
     */
    protected function hydrateSelect(\PDOStatement $statement): GenericCollection
    {
        /** @var UserMapper $vieweeMapper */
        $vieweeMapper = $this->getJoinMapper('participant');

        /** @var UserMapper $viewerMapper */
        $viewerMapper = $this->getJoinMapper('user');

        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $view = $this->hydrate($row);

            $viewee = $vieweeMapper->hydrate($row, 'participant');
            $viewer = $viewerMapper->hydrate($row, 'user');

            /* TODO Misleading naming SA-14957
             */
            $view->setParticipant($viewee);
            $view->setUser($viewer);

            $collection->add($view);
        }

        return $collection;
    }
}
