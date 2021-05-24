<?php

/**
 * Extends HTMLPurifier_Injector_SafeObject to allow autoplay, autostart,
 * classid and type params.
 */
class Alaress_HTMLPurifier_Injector_SafeObject extends HTMLPurifier_Injector_SafeObject
{

    protected $allowedParam = array(
        'wmode' => true,
        'movie' => true,
        'flashvars' => true,
        'src' => true,
        'allowFullScreen' => true, // if omitted, assume to be 'false'
        'autoplay' => true,
        'autostart' => true,
        'classid'   => true,
        'type'      => true,
    );
}

// vim: et sw=4 sts=4
