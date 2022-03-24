<?php

namespace Schoolbox\User\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Campus;
use Schoolbox\CampusMapper;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\Mapper\Exception\NotFound;
use Schoolbox\User;
use Schoolbox\UserMapper;
use Schoolbox\YearLevel\Mapper;

class ArrayToModel extends Base
{

    /**
     * @inheritDoc
     */
    public function __invoke(array $array, object $model = null): object
    {
        /**
         * loop through array lines
         * check if they have a 'set function' for the model if so, use it, or use the defined function in the if statements
         * else return error that the model does not match the array
         */
        $yearLevelMapper = Mapper::getInstance();
        $houseMapper = \HouseMapper::getInstance();
        $campusMapper = CampusMapper::getInstance();
        $userMapper = UserMapper::getInstance();
        foreach ($array as $label => $value) {
            //check if what the user is attempting to edit is valid?
            if (in_array($label, ["guardians", "students", "groups"])) {
                throw new SchoolboxException("'$label' field is unable to be updated at this endpoint");
            }
            if ('fullName' === $label) {
                throw new SchoolboxException("'fullName' is unable to be updated, please use title, firstName, lastname.");
            }
            if ('email' === $label) {
                //noop if email field with either be based on the username `isEmailDerived` or will be the `altEmail`
                throw new SchoolboxException("'email' is unable to be updated. 'email' field is based on isEmailDerived, or altEmail.");
            }
            //is function?
            //just need the build setFunction function, which exists in the mapper, so lets hit it from the side
            $function = $yearLevelMapper->getSetMethodName($label);
            /**
             * specific functions that have more than just a `setValue` or have a weird setValue
             */
            if ('isEmailDerived' === $label) {
                $model->setLocalEmail($value);
            } elseif ('role' === $label) {
                $model->getRole()->setId($value);
            } elseif ('campus' === $label) {
                //build the campus
                $model->setCampusCollection(new GenericCollection(Campus::class));
                foreach ($value as $campusId) {
                    try {
                        $campus = $campusMapper->findById($campusId);
                    } catch (NotFound | Deleted $e) {
                        throw new SchoolboxException("Campus '$campusId' Not Found or has been Deleted");
                    }
                    $model->getCampusCollection()->add($campus);
                }
            } elseif ('yearLevel' === $label) {
                //find the year level or set to null.
                try {
                    $model->setYear($yearLevelMapper->findById($value));
                } catch (\Schoolbox\Mapper\Exception $e) {
                    /* noop - went looking for a deleted or nonexistent year_id
                     * just use null
                     */
                    $model->setYear(null);
                }
            } elseif ('schoolHouse' === $label) {
                $model->setSchoolHouse($houseMapper->findById($value));
            } elseif ('flags' === $label) {
                    //set all the flags...
                    $model->setWebmail($value['showWebmailTab'])
                        ->setHideContacts($value['showContacts'])
                        ->setHideTimetable($value['enableTimetable'])
                        ->setHideSidenav($value['showSideNav'])
                        ->setHideNotificationsTray($value['showNotificationsPanel'])
                        ->setDisplayGradeGraph($value['showGradeGraph'])
                        ->setEportfolioEnable($value['enableEportfolio'])
                        ->setUseInternalMail($value['useInternalMail'])
                        ->setCalendarView($value['calendarView']);
            } elseif ('isBoarder' === $label) {
                $model->setBoardingStatus($value);
            } elseif ('impersonableUser' === $label) {
                $model->setLinkedUser(new GenericCollection(User::class));
                foreach ($value as $userId) {
                    try {
                        $user = $userMapper->findById($userId);
                    } catch (NotFound | Deleted $e) {
                        throw new SchoolboxException("User '$userId' Not Found or has been Deleted");
                    }
                    $model->getLinkedUser()->add($user);
                }
            } else {
                if (!method_exists($model, $function)) {
                    throw new SchoolboxException("Unable to set '$label' field from this endpoint");
                }
                $model->$function($value);
            }
        }
        return $model;
    }
}
