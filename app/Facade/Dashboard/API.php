<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Dashboard_API extends Generic_API {

    public function Display($component = 'all') {

        $response = new Response_HTTP();
        $s = new Authenticator();
        $user = $s->getSessionUser();

        // TODO replace this with a database call to find out what to render
        $dashDefinition = array(
            'student' => array(
                'Salutation',
                'CurrentPeriod',
                'SchoolNews',
                'YourNews',
                'SchoolEvents',
            ),
            'staff'   => array(
                'Salutation',
                'CurrentPeriod',
                'SchoolEvents',
                'SchoolNews',
            )
        );

        if(isset($dashDefinition[$user->getGroupType()])) {
            $userDashboard = $dashDefinition[$user->getGroupType()];
        }
        // you are not allowed to be using this system!!!!!!!11!!!!1!!
        else {
            $response->addInternalRedirectHeader('GO_AWAY');
            return $response;
        }

        $articles = array();

        // plz render mine component(s)
        if($component == 'all') {
            foreach($userDashboard as $component) {
                $articles[$component] = $this->renderComponent($component, $user);
            }
        }
        else {
            $articles[$component] = $this->renderComponent($component, $user);
        }

        if(isset($articles['Salutation'])) {
            $response->setPage('title', $articles['Salutation']['title']);
            unset($articles['Salutation']);
        }

        $response->setResponse('articles', $articles);
        return $response;
    }

    private function renderComponent($component, $user) {
        $className = "Facade_Component_$component";
        if(class_exists($className)) {
            $instance = new $className();
            if(is_a($instance, 'Facade_Component_Generic')) {
               return $instance->render($user);
            }
            else {
                // just ignore it? or should we throw an error?
            }
        }
        return '';
    }

}
