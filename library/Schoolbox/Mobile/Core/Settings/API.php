<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Core_Settings_API {

    public function Preferences() {

        $response = new Response_HTTP();

        $response->setPage('title', 'Your Settings');

        $response->setResponse('articles', array(
            "Preferences" => array(
                'title'   => 'Preferences',
                'aside'   => '',
                'class'   => 'prefs',
                'icon'    => 'settings',
                'content' => array('this data is being ignored'),
            ),
            "About" => array(
                'title'   => 'About',
                'aside'   => '',
                'class'   => 'about',
                'icon'    => 'settings',
                'content' => array('site_version' => Cfg::get('site_version')),
            )
        ));
        return $response;
    }

    public function Skin() {
        $response = new Response_HTTP();
        $response->setResponse('skin', Mediator::detectSite());
        return $response;

    }



    // TODO create About function here
}
