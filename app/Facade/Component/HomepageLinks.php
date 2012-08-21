<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_HomepageLinks extends Facade_Component_Generic {

    /**
     * This should return an array of [title, aside, content]
     * @param User $user
     * @return arr
     */
    public function render(GizaUser $user, $catID = 0) {

        // generate data
        try {
            $linkHandler = new Link();
            $links = $linkHandler->getAllInCategory($catID);
        }
        catch(ErrorException $e) {
            die($e);
        }


        return array(
            'title'   => 'Links',
            'aside'   => '',
            'class'   => 'homepage-links',
            'icon'    => 'next',
            'content' => $links,
        );
    }

}