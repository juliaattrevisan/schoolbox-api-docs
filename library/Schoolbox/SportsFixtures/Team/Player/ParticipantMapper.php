<?php
/**
 * ParticipantMapper
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\SportsFixtures\Team\Player;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\Mapper As BaseMapper;
use Schoolbox\Role as RoleModel;
use Schoolbox\User as UserModel;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Model as TeamModel;
use Schoolbox\SportsFixtures\Division\Model as DivisionModel;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;

class ParticipantMapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_participant');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->joins('match_id', MatchMapper::class, 'id', null, null, 'match', 'match');
        $this->uses('sequence', self::TYPE_INTEGER);
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
            FROM {$this->database->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('team', '', 't')}
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
            $model = $this->createModel()
                ->setId($row['id'])
                ->setSequence($row['sequence']);

            // set user and team only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');

            if ($user instanceof UserModel) {
                $model->setUser($user);
            }

            $team = $this->hydrateJoin($row, 'team', 't');

            if ($team instanceof TeamModel) {
                $model->setTeam($team);
            }

            $collection->add($model);
        }
        return $collection;
    }
}
