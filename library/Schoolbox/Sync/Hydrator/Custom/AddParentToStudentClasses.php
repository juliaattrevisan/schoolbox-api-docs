<?php

/**
 * Adds a parent's children's campuses to the parent.
 *
 * Also sets the parent's default campus to their oldest child's default campus.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use External_User;
use External_Class;
use Monolog\Logger;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\External\Normalise\User\Guardian as ExternalParent;
use Schoolbox\External\Subject;
use Schoolbox\Role;
use Schoolbox\User as InternalUser;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccessMapper as UserAccessMapper;
use Schoolbox\Permission\Access\UserAccess as UserAccessModel;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Mapper\Exception as MapperException;

class AddParentToStudentClasses extends AbstractLoggableHydrator
{
    /**
     * @var External_User
     */
    protected $externalUserHandler;

    /**
     * @var External_Class
     */
    protected $classHandler;

    /**
     * @var UserMapper
     */
    protected $userMapper;

    /**
     * @var CampusMapper
     */
    private $campusMapper;

    /**
     * @var UserAccessMapper
     */
    private $userAccessMapper;

    /**
     * @var FolderMapper
     */
    private $folderMapper;

    /**
     * Constructor (obviously)
     */
    public function __construct()
    {
        // TODO these should be injectable for all custom hydrators
        $this->externalUserHandler = new External_User();
        $this->classHandler        = new External_Class();
        $this->userMapper          = UserMapper::getInstance();
        $this->campusMapper        = CampusMapper::getInstance();
        $this->userAccessMapper    = UserAccessMapper::getInstance();
        $this->folderMapper        = FolderMapper::getInstance();
    }

    /**
     * {@inheritdoc}
     */
    public function hydrate(ExternalUser $input, InternalUser $previous)
    {
        $message = "Assigning class homepage access to parent";
        $this->log(Logger::INFO, $message, $input, $previous, []);

        if ($input instanceof ExternalParent) {
            // Get all of the parents kids

            $childIds = $this->externalUserHandler->getEnrolledStudents($input->getId());

            if (is_array($childIds)) {
                foreach ($childIds as $childId) {
                    // Get schoolbox user for the child

                    try {
                        $childUser = $this->userMapper->findByExternalId($childId['RelatedID'], Role::STUDENT);
                    } catch (MapperException $e) {
                        continue;
                    }

                    // Get their subjects

                    $subjects = $this->externalUserHandler->getSubjectList($childUser);

                    /** @var Subject $subject */
                    foreach ($subjects as $subject) {
                        $folder = $this->folderMapper->findForCampusAndClass($subject['campusCode'] ?? '', $subject['code']);
                        if (!$folder) {
                            continue;
                        }

                        try {
                            // Check if the parent already had permission, if they did- leave it alone.

                            $this->userAccessMapper->findOneByColumnList([
                                'uid' => $previous->getId(),
                                'sid' => $folder->getId()
                            ]);
                        } catch (MapperException $e) {
                            // Parent didn't have access, give them read access

                            $userAccess = new UserAccessModel();

                            if ($previous->getId() != null) {
                                $userAccess->setUser($previous);
                                $userAccess->setServiceIdentifier($folder->getId());
                                $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                                $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                                $this->userAccessMapper->save($userAccess);

                                $message = "Adding Class Homepage Access: {$folder->getTitle()}";
                                $this->log(Logger::INFO, $message, $input, $previous, []);
                            }
                        }
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
        return 'Give parents read access to their students\' class homepages';
    }

    /**
     * @inheritDoc
     */
    public function getDescription()
    {
        return 'This custom rule adds parents as read only group members to all of their students\' class homepages.';
    }
}
