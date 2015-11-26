<?php
/**
 * Common interface for sync hydrators.
 * 
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator;

use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\User as InternalUser;

interface HydratorInterface
{
    /**
     * Hydrates an internal user,
     *
     * @param ExternalUser      $input
     * @param InternalUser|null $previous
     *
     * @return InternalUser
     */
    public function hydrate(ExternalUser $input, InternalUser $previous = null);
}
