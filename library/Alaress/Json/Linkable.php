<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Alaress\Json;

/**
 * Interface that MUST be implemented by a model for \Alaress\Json\Decorator\LinkDecorator to have any effect.
 *
 * @package Alaress\Json
 */
interface Linkable
{

    /**
     * Return an array of Api\Link objects, indexed by the desired output key
     *
     * eg, [
     *     'self' => new AuraLink( ... ),
     *     'crap' => new SitePageLink( ... ),
     * ]
     *
     * @return Api\Link[]
     */
    public function getJsonApiLinks(): array;
}
