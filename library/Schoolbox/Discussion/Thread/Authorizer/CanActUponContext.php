<?php
/**
 * @copyright Copyright 2019, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Discussion\Thread\Authorizer;

use Alaress\Authorizer\Failure;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Schoolbox\Discussion\Thread;
use Schoolbox\User;

/**
 * Authorization rule for discussion threads.
 *
 * This rule checks whether the user has permission to act upon the thread's
 * context.
 */
class CanActUponContext implements IAuthorizationRule
{
    /**
     * @var IHasPermission[]
     */
    private $strategies;


    /**
     * Constructor
     *
     * @param IHasPermission ...$strategies
     */
    public function __construct(IHasPermission... $strategies)
    {
        $this->strategies = $strategies;
    }


    /**
     * @inheritdoc
     */
    public function authorize(array $params = [], User $user = null): void
    {
        // Extract context
        $context = $params['context'] ?? null;
        if (!$context instanceof Thread\Context\IContext) {
            throw new \InvalidArgumentException('"context" param is not an instance of ' . Thread\Context\IContext::class);
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
