<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Discussion\Permission;

use Alaress\Authorizer\Authorizer;
use Schoolbox\Comms\News;
use Schoolbox\Discussion\Thread\Context\IContext;
use Schoolbox\User;

/**
 * Authorization rule for whether a user may read a discussion thread for a
 * comms article.
 *
 * If a user can view a comms article, they can read comments.
 */
class CanGet extends BaseStrategy
{
    /**
     * @var Authorizer
     */
    private $authorizer;


    /**
     * Constructor
     *
     * @param Authorizer $authorizer
     * @param IContext   $context
     */
    public function __construct(Authorizer $authorizer, IContext $context)
    {
        parent::__construct($context);
        $this->authorizer = $authorizer;
    }


    /**
     * @param mixed $object
     * @param User|null $user
     * @return bool
     */
    public function hasPermission($object, ?User $user): bool
    {
        if (!$object instanceof News\Model) {
            return false;
        }
        return $this->authorizer->isPathAuthorizedForUser(
            'commsNews.getDetail',
            ['articleId' => $object->getId(), 'article' => $object],
            $user
        );
    }
}
