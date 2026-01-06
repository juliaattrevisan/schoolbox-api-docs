<?php

/**
 * @copyright Copyright 2020, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Calendar\Component\Homepage;

use Component_Homepage_ControllerAbstract;
use Schoolbox\Learning\Assessment\Redirect;
use Schoolbox\Permission\Access\UserAccess;
use RuntimeException;
use Twig\Error;

/**
 * Homepage calendar component.
 */
class Controller extends Component_Homepage_ControllerAbstract
{
    /**
     * @param string $templateHandle
     *
     * @return bool
     * @throws Error\LoaderError
     * @throws Error\RuntimeError
     * @throws Error\SyntaxError
     * @throws RuntimeException
     */
    public function renderTemplate($templateHandle): bool
    {
        $endOfNextSchoolYear = (new \Alaress_Date_Format())->endOfSchoolYear()->addYears(1);

        return $this->renderTwigTemplate(
            $templateHandle,
            '@calendar/component/homepage.twig',
            [
                'component'         => $this,
                'user'              => $this->getUser(),
                'folder'            => $this->getContext(),
                //Maximum days should be set to the number of remaining days until the end of the following school year
                'maxCalendarDays'   => $endOfNextSchoolYear->diffInDays(\Alaress_Date_Format::now()) + 1,
                'canCreateEvent'    => ($this->getContentPermission() >= UserAccess::ACCESS_WRITE),
                'returnUrl'         => Redirect::encode(sprintf('/homepage/%d', $this->getInstance()->getIdentifier()))
            ],
            !$this->getConfig('compactMode')
        );
    }
}
