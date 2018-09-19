<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use InvalidArgumentException;
use Schoolbox\GenericCollection;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use Schoolbox\Resource\Folder;

class CanModerateAudience extends ArticleAttribute
{

    /** @var Folder\Mapper */
    private $folderMapper;

    /**
     * Just compute this once per user. Indexed by user.id, folder.id
     *
     * @var GenericCollection[]|Folder\Model[][]
     */
    private $folderCache = [];


    /**
     * @param Folder\Mapper $folderMapper
     */
    public function __construct(Folder\Mapper $folderMapper)
    {
        $this->folderMapper = $folderMapper;
    }


    /**
     * @param array     $params
     * @param User|null $user
     * @throws InsufficientHydrationException
     * @throws InvalidArgumentException
     * @throws Unauthorized
     * @throws \UnexpectedValueException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new InvalidArgumentException('$user must be provided');
        }

        $article = $this->findArticleInParams($params);

        // Articles that don't have audiences can only be moderated by Comms Officers and SUs
        if (0 === $article->getAudience()->count()) {
            throw new Unauthorized('Only CommsOfficers and SUs can moderate an article without an audience');
        }

        // Since root-level segments are a UNION, you must have moderation permission to ALL root segments.
        foreach ($article->getAudience() as $segment) {
            if (!$this->canModerate($user, $segment)) {
                throw new Unauthorized("User {$user->getId()} cannot moderate audience segment {$segment->getId()}");
            }
        }
    }


    /**
     * @param User           $user
     * @param Audience\Model $segment
     * @return bool
     * @throws InsufficientHydrationException
     */
    private function canModerate(User $user, Audience\Model $segment): bool
    {
        // Recursive case
        if ($segment->isComposite()) {
            // Since composite children are an INTERSECT, the user must have moderation permission to ANY of the children.
            foreach ($segment->getChildren() as $child) {
                if ($this->canModerate($user, $child)) {
                    return true;
                }
            }
            return false;
        }

        // Base Cases

        // Implicit folder moderation permission if you have WRITE+ permission to the folder
        if (Audience\Model::ENTITY_TYPE_FOLDER === $segment->getEntityType()) {
            if (!\array_key_exists($user->getId(), $this->folderCache)) {
                $this->folderCache[$user->getId()] = $this->folderMapper->findForUser($user, true, UserAccess::ACCESS_WRITE)
                                                                        ->indexBy($this->folderMapper->getIdentifierProperty());
            }

            return $this->folderCache[$user->getId()]->keyExists($segment->getEntityValue());
        }

        // TODO add tests for other implicit moderation permissions.
        // TODO add tests for explicitly-assigned moderation permissions.

        return false;
    }
}
