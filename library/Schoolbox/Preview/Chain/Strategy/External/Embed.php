<?php

/**
 * Embed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

use Embera\Embera;

class Embed extends Base
{

    /**
     * Test the oEmbed capability
     * http://www.oembed.com/
     *
     * @return string
     */
    public function test()
    {
        $embera = new Embera();
        $embedCode = $embera->autoEmbed($this->getUrl());

        /* If the url is the same as $embedCode, nothing to replace
         */
        if ($embedCode != $this->getUrl()) {
            return [
                'type' => 'inline',
                'src'  => $embedCode,
                'twig' => [
                    'template' => '@preview/Strategy/External/Embed.twig',
                    'data' => [
                        'src' => $embedCode
                    ]
                ]
            ];
        }

        return $this->testNext();
    }
}
