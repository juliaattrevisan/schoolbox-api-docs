<?php

namespace Schoolbox\User\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Application\Exception\Exception as SchoolboxException;
use Schoolbox\Campus;
use Schoolbox\GenericCollection;
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
        foreach ($array as $label => $value) {
            //is function?
            //just need the build setFunction function, which exists in the mapper, so lets hit it from the side
            $function = $yearLevelMapper->getSetMethodName($label);
            /**
             * specific functions that have more than just a `setValue` or have a weird setValue
             */
            if ('isEmailDerived' === $label) {
                $model->setLocalEmail($value);
            } elseif ('email' === $label) {
                //noop if email field with either be based on the username `isEmailDerived` or will be the `altEmail`
            } elseif ('role' === $label) {
                $model->getRole()->setId($value);
            } elseif ('campus' === $label) {
                //build the campus
                $model->setCampusCollection(new GenericCollection(Campus::class));
                foreach ($value as $campusId) {
                    $campus = new Campus();
                    $campus->setId($campusId);
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
                $model->setSchoolHouse($houseMapper->createModel($value));
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
            } else {
                try {
                    $model->$function($value);
                } catch (\Throwable $e) {
                    throw new SchoolboxException("Model does not implement $function");
                }
            }
        }
        return $model;
    }
}
