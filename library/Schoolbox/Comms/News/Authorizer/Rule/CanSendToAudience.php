<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Schoolbox\Comms\News\Audience\Segment\StrategyFactory;
use Schoolbox\Comms\News\Exceptions\UnknownEntityTypeException;
use Schoolbox\Comms\News\Validation\ValidUserCanAudienceTo;
use Schoolbox\User;
use UnexpectedValueException;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;

/**
 * Checks the user is permitted to audience to the article's proposed audience
 *
 * This should not be used in module routes where the audience can change as part of the controller call.
 *
 * eg consider an author who creates a draft audienced to F:X, who them loses permission to audience to F:X
 * They must still be able to use the modify route to remove that audience. The controller is then responsible for
 * ensuring the updated audience is still valid.
 *
 * Instead, this would be used on eg, 'submit'
 */
class CanSendToAudience extends ArticleAttribute
{

    /** @var StrategyFactory */
    private $strategyFactory;


    /**
     * @param StrategyFactory $strategyFactory
     */
    public function __construct(StrategyFactory $strategyFactory)
    {
        $this->strategyFactory = $strategyFactory;
    }


    /**
     * @inheritdoc
     * @throws UnexpectedValueException If no article found in params
     * @throws UnknownEntityTypeException
     * @throws InsufficientHydrationException
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new Unauthorized('$user is required');
        }

        if (!(new ValidUserCanAudienceTo($user, $this->strategyFactory))->isSatisfiedBy($this->findArticleInParams($params))) {
            throw new Unauthorized("User is not permitted to send to this article's audience");
        }
    }
}
