<?php

/**
 * Extends HTMLPurifier_AttrTransform_SafeParam to:
 * * allow 'url' attribute, as long as URI is valid
 * * allow 'autoplay', 'autostart', 'classid' attributes
 */
class Alaress_HTMLPurifier_AttrTransform_SafeParam extends HTMLPurifier_AttrTransform_SafeParam
{
    /**
     * Screw you HTMLPurifier for using private variables - it's not for you to
     * decide whether I'm going to extend your code or not.
     *
     * @see steve-yegge.blogspot.com.au/2010/07/wikileaks-to-leak-5000-open-source-java.html
     *
     * @var HTMLPurifier_AttrDef_URI
     */
    protected $uri;

    /**
     * @var HTMLPurifier_AttrDef_Enum
     */
    protected $wmode;

    public function __construct()
    {
        $this->uri   = new HTMLPurifier_AttrDef_URI(true); // embedded
        $this->wmode = new HTMLPurifier_AttrDef_Enum(['window', 'opaque', 'transparent']);
    }

    public function transform($attr, $config, $context)
    {
        // If we add support for other objects, we'll need to alter the
        // transforms.
        switch ($attr['name']) {
            // application/x-shockwave-flash
            // Keep this synchronized with Injector/SafeObject.php
            case 'allowScriptAccess':
                $attr['value'] = 'never';
                break;
            case 'allowNetworking':
                $attr['value'] = 'internal';
                break;
            case 'allowFullScreen':
                if ($config->get('HTML.FlashAllowFullScreen')) {
                    $attr['value'] = ($attr['value'] == 'true') ? 'true' : 'false';
                } else {
                    $attr['value'] = 'false';
                }
                break;
            case 'wmode':
                $attr['value'] = $this->wmode->validate($attr['value'], $config, $context);
                break;
            case 'movie':
            case 'url':
            case 'src':
                //$attr['name'] = "movie";
                $attr['value'] = $this->uri->validate($attr['value'], $config, $context);
                break;
            case 'flashvars':
            case 'autoplay':
            case 'autostart':
            case 'classid':
                // we're going to allow arbitrary inputs to the SWF, on
                // the reasoning that it could only hack the SWF, not us.
                break;
            // add other cases to support other param name/value pairs
            default:
                $attr['name'] = $attr['value'] = null;
        }
        return $attr;
    }
}

// vim: et sw=4 sts=4
