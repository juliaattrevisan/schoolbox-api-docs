<?php
/**
 * Skips this parent if they have no children currently attending the school.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use External_User;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\External\Normalise\User\Guardian as ExternalParent;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use Schoolbox\User as InternalUser;
use Schoolbox\UserMapper;

class SkipParentIfNoChildren implements HydratorInterface
{
    /**
     * @var External_User
     */
    protected $externalUserHandler;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * Constructor (obviously)
     */
    public function __construct()
    {
        // TODO these should be injectable for all custom hydrators
        $this->externalUserHandler = new External_User();
        $this->userMapper          = new UserMapper();
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(ExternalUser $input, InternalUser $previous = null)
    {
        if ($input instanceof ExternalParent) {
            if (empty($this->externalUserHandler->getEnrolledStudents($previous->getExternalId()))) {
                throw new \Exception('Parents with no children are not synced');
            }
        }
        return $previous;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'Skip Parents with no enrolled Children';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'This custom rule skips any parents who have no children currently enrolled.';
    }
}
