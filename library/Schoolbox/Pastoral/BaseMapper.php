<?php
/**
 * @author      Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Pastoral;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper as SchoolboxMapper;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\User;

/**
 * Class BaseMapper
 *
 * @package Schoolbox\Pastoral
 * @method GenericCollection|Model[] createCollection($contents = [])
 */
abstract class BaseMapper extends SchoolboxMapper
{

    /**
     * Does the given user have access to the pastoral service?
     *
     * @param User $user
     *
     * @return bool
     */
    protected function isUserInPastoralService(User $user): bool
    {
        return null !== $user->getAccessList()->getBy('service', UserAccess::SERVICE_PASTORAL);
    }
}
