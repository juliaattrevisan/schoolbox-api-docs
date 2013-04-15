<?php
/**
 * Extends HTMLPurifier_HTMLModule_SafeEmbed to:
 * * set ContentType as type attribute
 * * not set codebase attribute
 * * set value of 'classid' attribute to 'text'
 */
class Alaress_HTMLPurifier_HTMLModule_SafeObject extends HTMLPurifier_HTMLModule_SafeObject {

    public function setup($config) {

        // These definitions are not intrinsically safe: the attribute transforms
        // are a vital part of ensuring safety.

        $max = $config->get('HTML.MaxImgLength');
        $object = $this->addElement(
            'object',
            'Inline',
            'Optional: param | Flow | #PCDATA',
            'Common',
            array(
                // While technically not required by the spec, we're forcing
                // it to this value.
                'type' => 'ContentType',
                'width' => 'Pixels#' . $max,
                'height' => 'Pixels#' . $max,
                'data' => 'URI#embedded',
                'classid' => 'Text',
            )
        );
        $object->attr_transform_post[] = new HTMLPurifier_AttrTransform_SafeObject();

        $param = $this->addElement(
            'param', false, 'Empty', false,
            array(
                'id' => 'ID',
                'name*' => 'Text',
                'value' => 'Text',
            )
        );
        $param->attr_transform_post[] = new HTMLPurifier_AttrTransform_SafeParam();
        $this->info_injector[] = 'SafeObject';

    }

}

// vim: et sw=4 sts=4
