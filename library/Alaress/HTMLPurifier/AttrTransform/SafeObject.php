<?php

/**
 * Extends HTMLPurifier_AttrTransform_SafeObject to not set type attribute.
 */
class Alaress_HTMLPurifier_AttrTransform_SafeObject extends HTMLPurifier_AttrTransform_SafeObject
{
    function transform($attr, $config, $context)
    {
        return $attr;
    }
}

// vim: et sw=4 sts=4
