<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Result;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Status\Model as Status;
use Schoolbox\Fixtures\Status\Mapper as StatusMapper;

/**
 * Class Mapper
 * @method Model createModel($idValue = null)
 * @package Schoolbox\Fixtures\Result
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_result');
        $this->hasModel(Model::class);

        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('match_id', MatchMapper::class, 'id', null, null, 'match', 'match');
        $this->joins('status_id', StatusMapper::class, 'id', null, null, 'status', 'status');
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->uses('description', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('team', 't')},
                {$this->getJoinColumnString('status', 's')}
        ";
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    protected function getFromString()
    {
        return "
            FROM {$this->getTable()}
            LEFT JOIN {$this->getJoinSql('team', null, 't')}
            LEFT JOIN {$this->getJoinSql('status', null, 's')}
        ";
    }

    /**
     * {@inheritdoc}
     *
     * @param PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws Exception
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            /** @var Model $result */
            $result = $this->hydrate($row);

            /** @var Team $team */
            $team = $this->hydrateJoin($row, 'team', 't');

            $result->setTeam($team);

            /** @var Status $status */
            $status = $this->hydrateJoin($row, 'status', 's');
            $result->setStatus($status);

            $collection->setOffset($result->getId(), $result);
        }

        return $collection;
    }
}
