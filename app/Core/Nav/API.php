<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Core_Nav_API extends Generic_API {

    /**
     * Render the Sidebar for the current context
     * @return Response_HTTP
     */
    public function Sidebar() {
        $response = new Response_HTTP();

        $a = new Authenticator();
        $user = $a->getSessionUser();

        $sidebar = new Core_Nav_Sidebar($user);
        $sidebar->createAgenda();
        $sidebar->createSubjectList();
        $sidebar->createGroupList();
        $sidebar->createUpcomingWork();
        $sidebar->createSettings();

        $response->setResponse('menu', $sidebar->getAll());

        return $response;
    }

}
