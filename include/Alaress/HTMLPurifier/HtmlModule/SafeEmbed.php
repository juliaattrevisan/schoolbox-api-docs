<?php
/**
 * Extends HTMLPurifier_HTMLModule_SafeEmbed to set ContentType as type
 * attribute.
 */
class Alaress_HTMLPurifier_HTMLModule_SafeEmbed extends HTMLPurifier_HTMLModule_SafeEmbed {

    public function setup($config) {

        $max = $config->get('HTML.MaxImgLength');
        $embed = $this->addElement(
            'embed', 'Inline', 'Empty', 'Common',
            array(
                'src*' => 'URI#embedded',
                'type' => 'ContentType',
                'width' => 'Pixels#' . $max,
                'height' => 'Pixels#' . $max,
                'allowscriptaccess' => 'Enum#never',
                'allownetworking' => 'Enum#internal',
                'flashvars' => 'Text',
                'wmode' => 'Enum#window,transparent,opaque',
                'name' => 'ID',
            )
        );
        $embed->attr_transform_post[] = new Alaress_HTMLPurifier_AttrTransform_SafeEmbed();

    }

}

// vim: et sw=4 sts=4
