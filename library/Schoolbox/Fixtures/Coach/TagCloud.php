<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Fixtures\Coach;

use Schoolbox\Collection;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Fixtures\Team;
use Schoolbox\User as User;
use Schoolbox\UserMapper;

/**
 * The tag cloud for a sports fixtures coach.
 *
 * @package Schoolbox\Fixtures\Coach
 */
class TagCloud implements BaseMapper\UsableAsTagSource
{
    /**
     * The coach mapper
     *
     * @var Mapper
     */
    protected $mapper;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor
     *
     * @param Mapper     $mapper
     * @param UserMapper $userMapper
     */
    public function __construct(Mapper $mapper, UserMapper $userMapper)
    {
        $this->mapper     = $mapper;
        $this->userMapper = $userMapper;
    }

    /**
     * Parses tag into its tag type and identifier.
     *
     * @param string $tag
     * @return array A tag type - tag value tuple if this is a valid tag,
     *               empty array if not.
     */
    public function parseTag($tag)
    {
        if (strpos($tag, ':') !== false) {
            $tagTuple = explode(':', $tag, 2);
            if (in_array($tagTuple[0], [Model::PREFIX_EXISTING_TAG, Model::PREFIX_NEW_FROM_USER, Model::PREFIX_NEW_FROM_INPUT])) {
                return $tagTuple;
            }
        }
        return [];
    }

    /**
     * Retrieves a tag model from a tag.
     *
     * @param string $tag
     * @return Model
     * @throws \UnexpectedValueException if $tag isn't parseable.
     * @throws BaseMapper\Exception if the tag model wasn't findable from the tag
     */
    public function findByTag($tag)
    {
        $tagTuple = $this->parseTag($tag);
        if ($tagTuple) {
            [$tagType, $identifier] = $tagTuple;
            $newModel = null;
            switch ($tagType) {
                case Model::PREFIX_EXISTING_TAG:
                    // This tag already exists. Fetch it and move on.
                    // This throws an exception if no tag exists with this ID.
                    return $this->mapper->findById($identifier);

                case Model::PREFIX_NEW_FROM_INPUT:
                    // This is a new tag, to be created from plain text input
                    return $this->mapper->createModel()
                        ->setName($identifier);

                case Model::PREFIX_NEW_FROM_USER:
                    // This is a new tag, to be created from an existing user.
                    // This throws an exception if no user exists with this ID.
                    /** @var User $user */
                    $user = $this->userMapper->findById($identifier);
                    return $this->mapper->createModel()
                        ->setName($user->formatName())
                        ->setUser($user);

                default:
                    break;
            }
        }
        throw new \UnexpectedValueException(sprintf('Tag "%s" could not be parsed', $tag));
    }

    /**
     * Saves a set of coaches.
     *
     * @param array|string[] $tags A list of tag names that can be parsed into
     *                             Model objects
     * @return Collection|Model[] A list of Model objects which match the tags.
     */
    public function saveTagCloud($tags)
    {
        $tagModels = new Collection();
        foreach ($tags as $tag) {
            try {
                $model = $this->findByTag($tag);
            } catch (BaseMapper\Exception $e) {
                // Tag or user doesn't exist:
                continue;
            } catch (\UnexpectedValueException $e) {
                // Bad tag type:
                continue;
            }

            // save it if necessary
            if (!$model->getId()) {
                $this->mapper->save($model);
            }
            $tagModels->add($model);
        }
        return $tagModels;
    }

    /**
     * Retrieves a list of items for a tag cloud.
     *
     * @param User        $user
     * @param string      $keyword
     * @param int         $offset
     * @param int         $limit
     * @return Collection|Model[]
     * @throws BaseMapper\DefinitionException
     * @throws BaseMapper\Exception
     * @throws \PDOException
     */
    public function findForTag(User $user, $keyword, $offset = 0, $limit = 0)
    {
        if (strlen($keyword) === 0) {
            return new Collection();
        }
        // Join side: tag w/o user is okay, so is user w/o tag - therefore FULL OUTER JOIN.
        // But that doesn't exist in MySQL! Emulate with LEFT JOIN U RIGHT JOIN
        // Search match: close enough to user AJAX search
        $selectPart = "
            SELECT
                {$this->mapper->getColumnString()},
                {$this->mapper->getJoinColumnString('user', 'u')},
                {$this->mapper->getJoinColumnString('user.role', 'ur')}
            FROM {$this->mapper->getTable()}
        ";
        $wherePart = "
            WHERE {$this->mapper->getTable()}.name LIKE :search
            OR    CONCAT(u.firstname, ' ', u.lastname) LIKE :search
        ";
        $joinPart = "
            JOIN {$this->mapper->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->mapper->getJoinSql('user.role', 'u', 'ur')}
        ";
        $query = "
            ($selectPart LEFT $joinPart $wherePart)
            UNION ALL
            ($selectPart RIGHT $joinPart $wherePart)
            ORDER BY name ASC, `u.lastname` ASC, `u.firstname` ASC
        ";
        if ($offset > 0) {
            $query .= ' LIMIT :limit OFFSET :offset';
        } elseif ($limit > 0) {
            $query .= ' LIMIT :limit';
        }
        $statement = $this->mapper->getDatabase()->prepare($query);
        $statement->bindValue('search', '%'.$this->mapper->getDatabase()->escapeForLike($keyword).'%', \PDO::PARAM_STR);
        if ($offset > 0) {
            $statement->bindValue('offset', intval($offset), \PDO::PARAM_INT);
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        } elseif ($limit > 0) {
            $statement->bindValue('limit', intval($limit), \PDO::PARAM_INT);
        }
        $statement->execute();
        return $this->mapper->hydrateSelect($statement);
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
            SELECT
                {$this->mapper->getColumnString()},
                {$this->mapper->getJoinColumnString('user', 'u')},
                {$this->mapper->getJoinColumnString('user.role', 'ur')}
            FROM {$this->mapper->getTable()}
            INNER JOIN sportsfixtures_team_coach stc ON {$this->mapper->getTable()}.id = stc.coach_id
            LEFT JOIN {$this->mapper->getJoinSql('user', '', 'u')}
            LEFT JOIN {$this->mapper->getJoinSql('user.role', 'u', 'ur')}
            WHERE stc.team_id = :teamId
            ORDER BY name ASC
        ";
        $statement = $this->mapper->getDatabase()->prepare($query);
        $statement->bindValue('teamId', $team->getId(), \PDO::PARAM_INT);
        $statement->execute();
        return $this->mapper->hydrateSelect($statement);
    }
}
