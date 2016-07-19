<?php
/**
 * ParticipantMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Position\Participant;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\User as UserModel;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Position\Model as Position;
use Schoolbox\SportsFixtures\Team\Position\Model as TeamPosition;
use Schoolbox\SportsFixtures\Team\Position\Participant\Model as ParticipantPosition;

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
        $this->joins('match_id', MatchMapper::class, 'id', null, null, 'match', 'match');
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
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return Collection
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = new Collection();
        foreach ($statement as $row) {
            $teamPosition = new TeamPosition();
            $teamPosition->setId($row['id']);

            // set user, team and position only if retrieved properly

            $position = $this->hydrateJoin($row, 'position', 'p');

            if ($position instanceof Position) {
                $teamPosition->setPosition($position);
            }

            $collection->add($teamPosition);
        }

        return $collection;
    }
}
