<?php

class HTMLPurifier_AttrTransform_SafeEmbed extends HTMLPurifier_AttrTransform
{
    public $name = "SafeEmbed";

    public function transform($attr, $config, $context) {
        $attr['allowscriptaccess'] = 'never';
        $attr['allownetworking'] = 'internal';
        return $attr;
    }
}

// vim: et sw=4 sts=4
