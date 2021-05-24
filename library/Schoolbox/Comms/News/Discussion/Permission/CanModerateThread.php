<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Permission;

use Alaress\Authorizer\Authorizer;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread\Authorizer\IHasPermission;
use Schoolbox\Discussion\Thread\Context\IThreadLocation;
use Schoolbox\User;

/**
 * Authorization rule for whether a user may moderate a discussion thread for
 * a comms article.
 *
 * Moderation responsibilities are:
 * * enabling/disabling posting of comments
 * * deleting comments
 *
 * A user may moderate the thread if any of the following are true:
 * * they authored the article
 * * they approved the article
 * * they have modify rights on the article
 */
class CanModerateThread implements IHasPermission
{
    /**
     * @var Authorizer
     */
    private $authorizer;


    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     */
    public function __construct(Authorizer $authorizer)
    {
        $this->authorizer = $authorizer;
    }


    /**
     * @param IThreadLocation  $object
     * @param User|null $user
     * @return bool
     */
    public function hasPermission(IThreadLocation $object, ?User $user): bool
    {
        if (!$object instanceof News\Model) {
            return false;
        }

        if ($user) {
            $author = $object->getAuthor();
            if ($author && $user->getId() === $author->getId()) {
                return true;
            }
            $acceptor = $object->getAcceptedBy();
            if ($acceptor && $user->getId() === $acceptor->getId()) {
                return true;
            }
        }

        return $this->authorizer->isPathAuthorizedForUser(
            'commsNews.postUpdate',
            ['articleId' => $object->getId(), 'article' => $object],
            $user
        );
    }
}
