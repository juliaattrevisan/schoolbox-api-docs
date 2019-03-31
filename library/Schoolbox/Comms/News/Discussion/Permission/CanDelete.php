<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Permission;

use Alaress\Authorizer\Authorizer;
use Schoolbox\Comms\News;
use Schoolbox\User;

/**
 * Authorization rule for whether a user may delete from a discussion thread
 * for a comms article.
 *
 * A user may delete comments if any of the following are true:
 * * they are the author
 * * they approved the article
 * * they have modify rights on the article
 */
class CanDelete extends BaseStrategy
{
    /**
     * @var Authorizer
     */
    private $authorizer;


    /**
     * Constructor
     *
     * @param Authorizer  $authorizer
     * @param News\Mapper $mapper
     */
    public function __construct(Authorizer $authorizer, News\Mapper $mapper)
    {
        parent::__construct($mapper);
        $this->authorizer = $authorizer;
    }


    /**
     * @param mixed     $object The object that is related to the thread
     * @param User|null $user
     * @return bool
     */
    public function hasPermission($object, ?User $user): bool
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
