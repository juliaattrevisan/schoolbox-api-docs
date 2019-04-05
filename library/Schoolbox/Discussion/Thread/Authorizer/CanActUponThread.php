<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Discussion\Comment;
use Schoolbox\Discussion\Thread;
use Schoolbox\User;

/**
 * Authorization rule for discussion threads.
 *
 * This rule checks where the thread is in use, and verifies whether the
 * requesting user has permission to act upon that place.
 */
class CanActUponThread implements IAuthorizationRule
{
    /**
     * @var IStrategy[]
     */
    private $strategies;


    /**
     * Constructor
     *
     * @param IStrategy ...$strategies
     */
    public function __construct(IStrategy... $strategies)
    {
        $this->strategies = $strategies;
    }


    /**
     * @inheritdoc
     */
    public function authorize(array $params = [], User $user = null): void
    {
//        // Extract thread from params: first "thread", then out of "comment"
//        $thread = $params['thread'] ?? null;
//        if (!$thread instanceof Thread\Model) {
//            $comment = $params['comment'] ?? null;
//            if (!$comment instanceof Comment\Model) {
//                throw new \InvalidArgumentException('Could not find thread in either "thread" or "comment" param');
//            }
//            $thread = $comment->getThread();
//        }

        // Extract context
        $context = $params['context'] ?? null;
        if (!$context instanceof Thread\IAssociable) {
            throw new \InvalidArgumentException('"context" param is not an instance of ' . Thread\IAssociable::class);
        }

        // Search for the thread in all the strategies.
        // (A thread should only be used in one place, but it's possible to
        //  check multiples.)
        foreach ($this->strategies as $strategy) {
            if ($strategy->hasPermission($context, $user)) {
                return;
            }
        }

        throw new Failure\Unauthorized('Could not authorize access to thread');
    }
}
