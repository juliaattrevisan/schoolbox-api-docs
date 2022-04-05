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
                //set all the flags... if they exist
                $this->setScalar($value, 'showWebmailTab', $model, 'setWebmail');
                $this->setScalar($value, 'showContacts', $model, 'setHideContacts');
                $this->setScalar($value, 'enableTimetable', $model, 'setHideTimetable');
                $this->setScalar($value, 'showSideNav', $model, 'setHideSidenav');
                $this->setScalar($value, 'showNotificationsPanel', $model, 'setHideNotificationsTray');
                $this->setScalar($value, 'showGradeGraph', $model, 'setDisplayGradeGraph');
                $this->setScalar($value, 'enableEportfolio', $model, 'setEportfolioEnable');
                $this->setScalar($value, 'useInternalMail', $model, 'setUseInternalMail');
                $this->setScalar($value, 'calendarView', $model, 'setCalendarView');
            } elseif ('isBoarder' === $label) {
                $model->setBoardingStatus($value);
            } else {
                $this->setScalar([$label => $value], $label, $model, $function);
            }
        }
        return $model;
    }
}
