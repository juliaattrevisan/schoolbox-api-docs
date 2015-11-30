<?php
/**
 * Adds this child's campuses to the parent.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use External_User;
use Schoolbox\Campus;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\External\Normalise\User\Guardian as ExternalParent;
use Schoolbox\Sync\Hydrator\HydratorInterface;
use Schoolbox\User as InternalUser;
use Schoolbox\UserMapper;

class AddParentToAllChildCampuses implements HydratorInterface
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
            // Don't attempt to parse campuses of children from $input.
            // Just get all this parent's children, and their campi.
            $childIds = $this->externalUserHandler->getEnrolledStudents($previous->getExternalId());
            foreach ($childIds as $childId) {
                try {
                    $childUser = $this->userMapper->findByExternalId($childId);
                } catch (\Exception $e) {
                    continue;
                }
                foreach ($childUser->getCampusCollection() as $campus) {
                    $campusId = $campus->getId();
                    if (empty($previous->getCampusCollection()->filterBy('id', $campusId))) {
                        $campusCopy = new Campus();
                        $campusCopy->setId($campusId);
                        $previous->getCampusCollection()->add($campusCopy);
                    }
                }
            }
        }
        return $previous;
    }

    /**
     * @inheritDoc
     */
    public function getName()
    {
        return 'Add Parent to All Children\'s Campuses';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'This custom rule adds each parent to all the campuses that their children belong to.';
    }
}
