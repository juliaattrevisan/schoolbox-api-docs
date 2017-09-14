<?php
/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Custom\Loyola\Survey\Component\Homepage;

use Component_Definition;
use Schoolbox\User;

class Definition extends Component_Definition
{
    /**
     * Hide the component from non-staff
     *
     * @return bool
     */
    public function renderInPalette()
    {
        /** @var User $user */
        $user = $_SESSION["user"];

        return $user->getRole()->isStaff();
    }
}
