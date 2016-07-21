<?php
/**
 * Adds a parent's children's campuses to the parent.
 *
 * Also sets the parent's default campus to their oldest child's default campus.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Sync\Hydrator\Custom;

use External_User;
use Monolog\Logger;
use Schoolbox\Campus;
use Schoolbox\CampusCollection;
use Schoolbox\CampusMapper;
use Schoolbox\Collection;
use Schoolbox\External\Normalise\User\Base as ExternalUser;
use Schoolbox\External\Normalise\User\Guardian as ExternalParent;
use Schoolbox\User as InternalUser;
use Schoolbox\UserMapper;

class AddParentToAllChildCampuses extends AbstractLoggableHydrator
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
    public function hydrate(ExternalUser $input, InternalUser $previous)
    {
        if ($input instanceof ExternalParent) {
            // Don't attempt to parse campuses of children from $input.
            // Just get all this parent's children, and their campi.
            $childIds = $this->externalUserHandler->getEnrolledStudents($input->getId());

            if (is_array($childIds)) {

                $oldCampuses = $newCampuses = $previous->getCampusCollection()->getColumnValues('id');

                $children = new Collection();

                foreach ($childIds as $childId) {
                    try {
                        $childUser = $this->userMapper->findByExternalId($childId['RelatedID']);
                    } catch (\Exception $e) {
                        // noop
                        continue;
                    }
                    $children->add($childUser);

                    foreach ($childUser->getCampusCollection() as $campus) {
                        $campusId = $campus->getId();
                        if (!in_array($campusId, $newCampuses)) {
                            $newCampuses[] = $campusId;
                        }
                    }
                }

                $previous->setCampusCollection(CampusMapper::getInstance()->findByColumn('id', $newCampuses));

                if (empty($newCampuses)) {
                    $message = "No child campuses found to assign to parent";
                    $context = [];
                } else {
                    $message = "Assigning child campuses to parent";
                    $context = [
                        'diff' => [
                            'old' => $oldCampuses,
                            'new' => $newCampuses
                        ]
                    ];
                }
                $this->log(Logger::INFO, $message, $input, $previous, $context);


                // If children have campuses, select one as default for parent
                if (!empty($newCampuses)) {

                    $children->sortBy('year','desc');

                    // Make storm happy :/
                    $defaultCampus = null;

                    /** @var InternalUser $child */
                    foreach($children as $child){

                        // Prefer default Campus
                        if($child->getDefaultCampus() instanceof Campus){
                            $defaultCampus = $child->getDefaultCampus();
                            break;
                        }

                        // Fallback to first actual campus
                        foreach($child->getCampusCollection() as $campus){
                            if($campus instanceof Campus){
                                $defaultCampus = $campus;
                                break;
                            }
                        }
                    }

                    $previous->setDefaultCampus($defaultCampus);
                    $message = "Assigning default campus to parent";
                    $context = ['campusId' => $defaultCampus->getId()];

                    $this->log(Logger::INFO, $message, $input, $previous, $context);
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
