<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Mobile\Core\Nav;

use Schoolbox\Mobile\Generic;
use Response_HTTP;
use Authenticator;
use Cfg;

class API extends Generic\API {

    /**
     * Render the Sidebar for the current context
     * @return Response_HTTP
     */
    public function Sidebar() {
        $response = new Response_HTTP();

        $a = new Authenticator();
        $user = $a->getSessionUser();

        $sidebar = new Sidebar($user);
        $sidebar->createAgenda();

        /* It is understood that this is an extremely quick and nasty solution. It is not intended to be permanent,
         * and is to be replaced with proper, polymorphic code at the very first opportunity available. [SB-1724] */
        if(in_array(Cfg::get('site_location'), array('mgs', 'mggs'))) {
            $sidebar->createWebmail();
        }

        $sidebar->createSubjectList();
        $sidebar->createGroupList();
        $sidebar->createUpcomingWork();
        $sidebar->createSettings();

        $response->setResponse('menu', $sidebar->getAll());

        return $response;
    }

}
