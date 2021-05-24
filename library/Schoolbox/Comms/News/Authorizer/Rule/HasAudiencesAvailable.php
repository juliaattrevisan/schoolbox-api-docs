<?php

/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Authorizer\Rule;

use Alaress\Authorizer\Failure\Unauthorized;
use Alaress\Authorizer\Rule\IAuthorizationRule;
use Alaress\Database\Connector\IConnector;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Comms\News\Audience\Segment\ISimpleStrategy;

/**
 * Checks that a user has 1+ audience segments they can audience to
 */
class HasAudiencesAvailable implements IAuthorizationRule
{

    /** @var GenericCollection|ISimpleStrategy[] */
    private $simpleStrategies;

    /** @var IConnector */
    private $db;


    /**
     * @param GenericCollection|ISimpleStrategy[] $simpleStrategies
     * @param IConnector                          $db
     */
    public function __construct(GenericCollection $simpleStrategies, IConnector $db)
    {
        $this->simpleStrategies = $simpleStrategies;
        $this->db               = $db;
    }


    /**
     * @inheritdoc
     */
    public function authorize(array $params = [], User $user = null): void
    {
        if (!$user) {
            throw new Unauthorized("User is required");
        }

        $pieces = [];
        foreach ($this->simpleStrategies as $strategy) {
            if ($strategy->canAudienceToEntityType($user)) {
                $sql = $strategy->getAudiencingSql($user);
                if ($sql) {
                    $pieces[] = "({$sql})";
                }
            }
        }
        if (!\count($pieces)) {
            throw new Unauthorized("User does not have any audiences types available");
        }

        // We can UNION ALL and avoid de-duplication logic as each sub-query has a distinct entity type
        $statement             = $this->db->executeQuery(\implode(' UNION ALL ', $pieces));
        $hasAudiencesAvailable = $statement->rowCount() > 0;
        $statement->close();

        if (!$hasAudiencesAvailable) {
            throw new Unauthorized("User does not have any audiences values available");
        }
    }
}
