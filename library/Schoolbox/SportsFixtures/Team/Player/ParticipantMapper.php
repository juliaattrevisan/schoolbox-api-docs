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
use Schoolbox\User as UserModel;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\SportsFixtures\Team\Mapper as TeamMapper;
use Schoolbox\SportsFixtures\Team\Player\Model as TeamPlayerModel;
use Schoolbox\SportsFixtures\Match\Mapper as MatchMapper;

class ParticipantMapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_participantuser');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->joins('match_id', MatchMapper::class, 'id', null, null, 'match', 'match');
        $this->joins('team_id', TeamMapper::class, 'id', null, null, 'team', 'team');
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
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
            /** @var TeamPlayerModel $model */
            $model = $this->createModel()
                ->setId($row['id'])
                ->setSequence($row['sequence']);

            // set user and team only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');

            if ($user instanceof UserModel) {
                $model->setUser($user);
            }

            $collection->add($model);
        }

        return $collection;
    }
}
