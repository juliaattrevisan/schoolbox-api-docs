<?php
/**
 *
 * @author      James Leckie <jleckie@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 */

namespace Schoolbox\User;

use Alaress\Web\Controller\Base;
use \Schoolbox\User;


class Controller extends Base
{

    /**
     * Function to transfer to another user (impersonate), stores your previous session for later return
     *
     * @param User $user
     */
    public function impersonate(User $user) {
        $_SESSION['previous'] = $_SESSION;
        $session = new \Session();
        $session->create($user->getUsername());

        $this->getResponse()->redirect->found('/');
    }

    /**
     * Return to your previous session, after imper
     */
    public function sessionReturn() {
        $_SESSION = $_SESSION['previous'];
    }

} 