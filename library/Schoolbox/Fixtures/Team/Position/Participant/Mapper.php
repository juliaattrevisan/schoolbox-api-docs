<?php

/**
 * ParticipantMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Position\Participant;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\UserMapper;
use Schoolbox\Fixtures\Matchup\Mapper as MatchupMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Team\Position\Participant\Model as ParticipantPosition;

/**
 * @method Model hydrate(array $row, $alias = '', $callback = null)
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_participantposition');
        $this->hasModel(ParticipantPosition::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('match_id', MatchupMapper::class, 'id', null, null, 'matchup', 'match');
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('name', self::TYPE_STRING);
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
    }

    /**
     * This mapper's find* methods always hydrate the user and team.
     *
     * @return string
     */
    protected function getSelectString()
    {
        return "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('team', 't')}
            FROM {$this->database->getQuoter()->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('team', '', 't')}

        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return GenericCollection|Model[]
     * @throws BaseMapper\DefinitionException
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        /** @var UserMapper $userMapper */
        $userMapper = $this->getJoinMapper('user');
        /** @var TeamMapper $teamMapper */
        $teamMapper = $this->getJoinMapper('team');

        $collection = $this->createCollection();
        foreach ($statement as $row) {
            $teamPosition = $this->hydrate($row);
            $teamPosition->setUser($userMapper->hydrate($row, 'u'));
            $teamPosition->setTeam($teamMapper->hydrate($row, 't'));
            $collection->add($teamPosition);
        }

        return $collection;
    }
}
