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
 * If a user can modify a comms article, they can delete its comments.
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
     * @param mixed $object
     * @param User  $user
     * @return bool
     */
    public function hasPermission($object, User $user): bool
    {
        if (!$object instanceof News\Model) {
            return false;
        }
        return $this->authorizer->isPathAuthorizedForUser(
            'commsNews.postUpdate',
            ['articleId' => $object->getId(), 'article' => $object],
            $user
        );
    }
}
