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
use Schoolbox\User as UserModel;

/**
 * The tag cloud for a sports fixtures coach.
 *
 * @package Schoolbox\SportsFixtures\Coach
 */
class TagCloud implements BaseMapper\UsableAsTagSource
{
    /**
     * @var Mapper
     */
    protected $mapper;

    /**
     * Constructor
     *
     * @param Mapper $mapper
     */
    public function __construct(Mapper $mapper)
    {
        $this->mapper = $mapper;
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
            list($tagType, $identifier) = $tagTuple;
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
                    /** @var UserModel $user */
                    $user = $this->mapper->getJoinMapper('user')->findById($identifier);
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
     * @return array|int[] A list of IDs of Model objects which match the tags.
     */
    public function saveTagCloud($tags)
    {
        $tagIds = [];
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
            $tagIds[] = $model->getId();
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
    public function findForTag(UserModel $user, $keyword, $offset = 0, $limit = 0)
    {
        if (strlen($keyword) === 0) {
            return new Collection();
        }
        // This could retrieve a list of tags which like-match this keyword
        // + a list of users which like-match
        // Leave out user tags which are already found
        return $this->mapper->findForTag($user, $keyword, $offset, $limit);
    }

    /**
     * Retrieves coaches for the given team.
     *
     * @param Team\Model $team
     * @return Collection|Model[]
     */
    public function findForTeam(Team\Model $team)
    {
        return $this->mapper->findForTeam($team);
    }
}
