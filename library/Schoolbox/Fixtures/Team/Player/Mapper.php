<?php

/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Team\Player;

use PDOStatement;
use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Role as Role;
use Schoolbox\User as User;
use Schoolbox\UserMapper;
use Schoolbox\Fixtures\Team\Model as Team;
use Schoolbox\Fixtures\Team\Mapper as TeamMapper;
use Schoolbox\Fixtures\Division\Model as Division;

/**
 * Mapper for the relation between teams and users.
 *
 * @method Model createModel()
 * @method Model findById($id)
 * @method Collection|Model[] findByColumn($columnName, $columnValue, $options = array())
 * @package Schoolbox\Fixtures\Team\User
 */
class Mapper extends BaseMapper
{
    /**
     * Setup mapping
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_team_user');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
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
                {$this->getJoinColumnString('user', 'u')},
                {$this->getJoinColumnString('user.role', 'ur')},
                {$this->getJoinColumnString('user.pronoun', 'up')},
                {$this->getJoinColumnString('team', 't')},
                {$this->getJoinColumnString('team.division', 'td')}
            FROM {$this->database->getQuoter()->quoteIdentifier($this->table)}
            INNER JOIN {$this->getJoinSql('user', '', 'u')}
            INNER JOIN {$this->getJoinSql('user.role', 'u', 'ur')}
            LEFT JOIN {$this->getJoinSql('user.pronoun', 'u', 'up')}
            INNER JOIN {$this->getJoinSql('team', '', 't')}
            INNER JOIN {$this->getJoinSql('team.division', 't', 'td')}
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
            $model = $this->createModel()
                ->setId($row['id'])
                ->setSequence($row['sequence']);

            // set user and team only if retrieved properly
            $user = $this->hydrateJoin($row, 'user', 'u');
            if ($user instanceof User) {
                $role = $this->hydrateJoin($row, 'user.role', 'ur');
                $user->setPronoun($this->hydrateJoin($row, 'user.pronoun', 'up'));
                if ($role instanceof Role) {
                    $user->setRole($role);
                }
                $model->setUser($user);
            }
            $team = $this->hydrateJoin($row, 'team', 't');
            if ($team instanceof Team) {
                $division = $this->hydrateJoin($row, 'team.division', 'td');
                if ($division instanceof Division) {
                    $team->setDivision($division);
                }
                $model->setTeam($team);
            }
            $collection->add($model);
        }
        return $collection;
    }


    /**
     * Find a list of teams that $user is involved with
     *
     * @param \Schoolbox\User $user
     * @return array
     * @throws \PDOException
     */
    public function findInvolvedTeamIds(\Schoolbox\User $user): array
    {
        /* $user might be involved with teams either:
         *     - as a player currently
         *     - as a coach of a team currently
         *     - as someone who was previously a coach of a team
         *
         * For now we'll just do it the same way as players
         */

        $query = "
            (SELECT DISTINCT team_id
            FROM sportsfixtures_team_user
            WHERE user_id = :userId)

            UNION

            (SELECT DISTINCT team_id
            FROM sportsfixtures_team_coach stc
            INNER JOIN sportsfixtures_coach sc ON stc.coach_id = sc.id
            WHERE sc.user_id = :userId)
        ";

        $statement = $this->getDatabase()->prepare($query);
        $statement->execute([
            'userId' => $user->getId(),
        ]);

        $ids = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $ids[$row['team_id']] = $row['team_id'];
        }

        return $ids;
    }


    /**
     * Finds all team memberships for a user in a division.
     *
     * @param User $user
     * @param Division $division
     * @return Collection|Model[]
     */
    public function findForUserInDivision(User $user, Division $division)
    {
        $query = $this->getSelectString() . ' WHERE td.id = :divisionId AND u.id = :userId';
        $stmt  = $this->getDatabase()->prepare($query);
        $stmt->execute(['userId' => $user->getId(), 'divisionId' => $division->getId(),]);
        return $this->hydrateList($stmt);
    }
}
