<?php

namespace Schoolbox\User\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;

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

        foreach ($array as $label => $value) {
            //is function?
            //just need the build setFunction function, which exists in the mapper, so lets hit it from the side
            $function = 'set' . ucfirst($label);
            /**
             * specific functions that have more than just a `setValue` or have a weird setValue
             */
            if ('isEmailDerived' === $label) {
                $model->setLocalEmail($value);
            } elseif ('yearLevel' === $label) {
                $model->setYear($value);
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
            } else {
                $this->setScalar([$label => $value], $label, $model, $function);
            }
        }
        return $model;
    }
}
