<?php

/**
 * This authorization method checks whether the user is a student in the given
 * class code or combination of.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Alaress\Authorizer\Rule;

use External_UserInterface;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Role;

class IsStudentOfClass extends All
{
    /**
     * Constructor (obviously)
     *
     * @param External_UserInterface  $externalDb
     * @param array|callable|Folder $classCodeSource
     */
    public function __construct(External_UserInterface $externalDb, $classCodeSource)
    {
        parent::__construct(
            new IsLoggedIn(),
            new HasRole(Role::STUDENT),
            new IsInvolvedInClass($externalDb, $classCodeSource)
        );
    }
}
