<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Facade_Component_Salutation extends Facade_Component_Generic {

    // pretty easy one
    public function render(GizaUser $user) {

        $r = array(
            'title'   => TextFormatter::greeting() . ', ' . $user->getName(),
            'aside'   => '',
            'class'   => 'salutation',
            'icon'    => 'salutation',
            'content' => '',
        );

        return $r;
    }
}
