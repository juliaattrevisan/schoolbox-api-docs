<?php
/**
 * Extends HTMLPurifier_Injector_SafeObject to allow type attribute.
 */
class Alaress_HTMLPurifier_AttrTransform_SafeEmbed extends HTMLPurifier_AttrTransform_SafeEmbed {
    public function transform($attr, $config, $context) {
        $attr['allowscriptaccess'] = 'never';
        $attr['allownetworking'] = 'internal';
        return $attr;
    }
}

// vim: et sw=4 sts=4
