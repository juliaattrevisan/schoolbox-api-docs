<?php
/**
 * ParticipantMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Fixtures\Coach\Participant;

use PDOStatement;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Match\Mapper as MatchMapper;
use Schoolbox\Fixtures\Coach\Participant\Mapper as CoachMapper;

class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_participantcoach');
        $this->hasModel(Model::class);
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
            LEFT JOIN {$this->getJoinSql('user', '', 'u')}
        ";
    }

    /**
     * Hydrate joins added in `getSelectString`.
     *
     * @param  PDOStatement $statement
     * @return GenericCollection|Model[]
     */
    protected function hydrateSelect(PDOStatement $statement)
    {
        $collection = $this->createCollection();

        foreach ($statement as $row) {
            $coach = CoachMapper::getInstance()->createModel();
            $coach->setName($row['name']);

            // set user and team only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');

            if ($user instanceof User) {
                $coach->setUser($user);
            }

            $collection->add($coach);
        }

        return $collection;
    }
}
