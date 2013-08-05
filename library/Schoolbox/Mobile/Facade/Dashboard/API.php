<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Mobile\Facade\Dashboard;

use Schoolbox\Mobile\Facade\Component\Generic as GenericComponent;
use Schoolbox\Mobile\Generic;
use Schoolbox\Resource\CategoryMapper;
use Schoolbox\Resource\CategoryLogMapper;
use Authenticator;
use Component_Dashboard_Factory;
use SitePage;
use Config;
use ClientDevice;
use Scrub;
use Response_HTTP;
use User;

class API extends Generic\API {

    public function Display($component = 'all') {

        $response = new Response_HTTP();
        $s = new Authenticator();
        $user = $s->getSessionUser();

        // 2n is better than n^2, so iterate across the dash and index by controller name
        $componentry = array();
	    $dashFactory = new Component_Dashboard_Factory(new User(), new SitePage(false));
	    $userComponents = $dashFactory->buildComponentList($user->getRole()->getId());
        foreach($userComponents as $comp) {
            $componentry[get_class($comp)] = $comp;
        }

        if($component == 'all') {
            // map desktop components to mobile equivalents
            $dashMap = array(
                'Greeting' => 'Salutation',
                'Timetable' => 'CurrentPeriod',
                'News' => 'SchoolNews',
                'HomepageNews' => 'YourNews',
                'Calendar' => 'SchoolEvents',
            );

            $pattern = "Component_Dashboard_%sController";
            // order this based on the order in the map, rather than the order specified on the desktop dashboard
            foreach($dashMap as $desktop => $mobile) {
                if(isset($componentry[sprintf($pattern, $desktop)])) {
                    $articles[$mobile] = $this->renderComponent($mobile, $user);
                }
            }
        }
        else {
            $articles[$component] = $this->renderComponent($component, $user);
        }

        if(isset($articles['Salutation'])) {
            $response->setPage('title', $articles['Salutation']['title']);
            unset($articles['Salutation']);
        }
        // it may be that there's no greeting component present, in which case the page still needs a title
        else {
            $response->setPage('title', 'Your Dashboard');
        }

        $response->setResponse('articles', $articles);
        return $response;
    }

    private function renderComponent($component, $user) {
        $className = "Facade_Component_$component";
        if(class_exists($className)) {
            $instance = new $className();
            if($instance instanceof GenericComponent) {
               return $instance->render($user);
            }
            else {
                // just ignore it? or should we throw an error?
            }
        }
        return '';
    }

}
