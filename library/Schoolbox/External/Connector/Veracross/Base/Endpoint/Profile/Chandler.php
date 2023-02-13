<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Veracross\Base\Endpoint\Profile;

/**
 * A subclass of Chandler where getIdentifierValue() can be computed
 * from the base profile's id, which we need for sis_user_base_profile
 * (the primary identifier for student/parent/staff subprofiles)
 */
class Chandler extends \Schoolbox\External\Connector\Veracross\Base\Endpoint\Chandler
{
    /**
     * Returns a scalar corresponding to the identifier for $newObject
     *
     * @param $newObject
     * @return int|null
     */
    protected function getIdentifierValue($newObject): ?int
    {
        $identifierGetter = $this->mapper->getGetIdentifierMethodName();

        return $newObject->$identifierGetter()?->getId();
    }
}