<?php

/**
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Custom\Loyola\Survey\Component\Homepage;

use Component_Definition;
use Schoolbox\User;

class Definition extends Component_Definition
{
    /**
     * Hide the component from non-staff
     *
     * @param string $context
     * @param int    $contextId
     * @return bool
     */
    public function renderInPalette($context, $contextId)
    {
        /** @var User $user */
        $user = $_SESSION["user"];

        return $user->getRole()->isStaff();
    }
}
