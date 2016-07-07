<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\SportsFixtures\Coach;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\SportsFixtures\Team;
use Schoolbox\UserMapper;
use Schoolbox\User as UserModel;

/**
 * The mapper for a sports fixtures coach.
 *
 * @method Model createModel()
 * @package Schoolbox\SportsFixtures\Coach
 */
class Mapper extends BaseMapper implements BaseMapper\UsableAsTagSource
{
    /**
     * Setup mapping
     * @return void
     */
    protected function mapping()
    {
        $this->hasTable('sportsfixtures_coach');
        $this->hasModel(Model::class);
        $this->uses('id', self::TYPE_INTEGER, self::COLUMN_IDENTIFIER);
        $this->uses('name', self::TYPE_STRING);
        $this->joins('user_id', UserMapper::class, 'id', null, null, 'user', 'user');
        $this->uses('created_at', self::TYPE_DATETIME, self::COLUMN_CREATED_TIMESTAMP);
        $this->uses('updated_at', self::TYPE_DATETIME, self::COLUMN_UPDATED_TIMESTAMP);
        $this->uses('deleted_at', self::TYPE_DATETIME, self::COLUMN_SOFTDELETE);
//        $this->joinsVia(
//            'sportsfixtures_team_coach',
//            Team\Mapper::class,
//            'id',
//            'sportsfixtures_team_id',
//            'sportsfixtures_coach_id',
//            null,
//            null,
//            null,
//            'teams'
//        );
    }

    /**
     * Hydrate user as well by default.
     *
     * @param \PDOStatement $statement
     * @return Collection
     * @throws BaseMapper\Exception
     */
    public function hydrateSelect(\PDOStatement $statement)
    {
        $collection = new Collection();
        foreach ($statement as $row) {
            $model = $this->hydrate($row);
            if (isset($row['u.id'])) {
                $user = $this->hydrateJoin($row, 'user', 'u');
                if ($user instanceof UserModel) {
                    $model->setUser($user);
                }
            }
            $collection->add($model);
        }
        return $collection;
    }

    /**
     * Saves a set of coaches.
     *
     * @param array|string[] $tags A list of tag names that can be parsed into
     *                             Model objects
     * @return array|int[] A list of IDs of Model objects which match the tags.
     */
    public function saveTagCloud($tags)
    {
        $tagIds = [];
        foreach ($tags as $tag) {
            if (strpos($tag, ':') !== false) {
                list($prefix, $identifier) = explode(':', $tag, 2);
                $newModel = null;
                switch ($prefix) {
                    case Model::PREFIX_EXISTING_TAG:
                        // This tag already exists. Don't try to resave it.
                        $tagIds[] = $identifier;
                        break;

                    case Model::PREFIX_NEW_FROM_INPUT:
                        // This is a new tag, to be created from plain text input
                        $newModel = $this->createModel()->setName($identifier);
                        break;

                    case Model::PREFIX_NEW_FROM_USER:
                        // This is a new tag, to be created from an existing user
                        try {
                            /** @var UserModel $user */
                            $user = $this->getJoinMapper('user')->findById($identifier);
                        } catch (BaseMapper\Exception $e) {
                            // User don't exist, so:
                            break;
                        }
                        $newModel = $this->createModel()->setName($user->formatName())->setUser($user);
                        break;

                    default:
                        break;
                }

                // save it if necessary
                if ($newModel instanceof Model) {
                    $this->save($newModel);
                    $tagIds[] = $newModel->getId();
                }
            }
        }
        return $tagIds;
    }

    /**
     * Retrieves a list of items for a tag cloud.
     *
     * @param UserModel $user
     * @param string $keyword
     * @param int $offset
     * @param int $limit
     * @return Collection|Model[]
     */
    public function findForTag(UserModel $user, $keyword, $offset = 0, $limit = self::DEFAULT_LIMIT)
    {
        if (strlen($keyword) === 0) {
            return new Collection();
        }
        // Join side: tag w/o user is okay, so is user w/o tag - therefore FULL OUTER JOIN.
        // But that doesn't exist in MySQL! Emulate with LEFT JOIN U RIGHT JOIN
        // Search match: close enough to user AJAX search
        $selectPart = "
            SELECT
                {$this->getColumnString()},
                {$this->getJoinColumnString('user', 'u')}
            FROM {$this->getTable()}
        ";
        $wherePart = "
            WHERE {$this->getTable()}.name LIKE :search
            OR    CONCAT(u.firstname, ' ', u.lastname) LIKE :search
        ";
        $query = "
            ($selectPart LEFT JOIN {$this->getJoinSql('user', '', 'u')} $wherePart)
            UNION ALL
            ($selectPart RIGHT JOIN {$this->getJoinSql('user', '', 'u')} $wherePart)
            ORDER BY name ASC, `u.lastname` ASC, `u.firstname` ASC
            LIMIT :limit OFFSET :offset
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('search', $this->getDatabase()->quoteForLike($keyword).'%', \PDO::PARAM_STR);
        $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }

    /**
     * Retrieves coaches for the given team.
     *
     * @param Team\Model $team
     * @return Collection|Model[]
     */
    public function findForTeam(Team\Model $team)
    {
        $query = "
            {$this->getSelectString()}
            INNER JOIN {$this->getJoinSql('sportsfixtures_team_coach', '', 'stc')}
            WHERE stc.team_id = :teamId
            ORDER BY name ASC
        ";
        $statement = $this->getDatabase()->prepare($query);
        $statement->bindValue('teamId', $team->getId(), \PDO::PARAM_INT);
        $statement->execute();
        return $this->hydrateSelect($statement);
    }
}
