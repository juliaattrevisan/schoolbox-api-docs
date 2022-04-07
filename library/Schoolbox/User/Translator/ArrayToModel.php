<?php

namespace Schoolbox\User\Translator;

use Schoolbox\API\Translator\ArrayToModel\Base;
use Schoolbox\Application\Exception\Exception as SchoolboxException;

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
                $this->setScalar($value, 'showGradeGraph', $model, 'setDisplayGradeGraph');
                $this->setScalar($value, 'enableEportfolio', $model, 'setEportfolioEnable');
                $this->setScalar($value, 'useInternalMail', $model, 'setUseInternalMail');
                $this->setScalar($value, 'calendarView', $model, 'setCalendarView');
                /**
                 * weird reverse magic
                 */
                if (array_key_exists('showContacts', $value)) {
                    $value['showContacts'] = !$value['showContacts'];
                }
                if (array_key_exists('enableTimetable', $value)) {
                    $value['enableTimetable'] = !$value['enableTimetable'];
                }
                if (array_key_exists('showSideNav', $value)) {
                    $value['showSideNav'] = !$value['showSideNav'];
                }
                if (array_key_exists('showNotificationsPanel', $value)) {
                    $value['showNotificationsPanel'] = !$value['showNotificationsPanel'];
                }
                $this->setScalar($value, 'showContacts', $model, 'setHideContacts');
                $this->setScalar($value, 'enableTimetable', $model, 'setHideTimetable');
                $this->setScalar($value, 'showSideNav', $model, 'setHideSidenav');
                $this->setScalar($value, 'showNotificationsPanel', $model, 'setHideNotificationsTray');
            } elseif ('isBoarder' === $label) {
                $model->setBoardingStatus($value);
            } else {
                if (method_exists($model, $function)) {
                    $this->setScalar([$label => $value], $label, $model, $function);
                } else {
                    throw new SchoolboxException("$label is not a field you are able to set on the User Object");
                }
            }
        }
        return $model;
    }
}
