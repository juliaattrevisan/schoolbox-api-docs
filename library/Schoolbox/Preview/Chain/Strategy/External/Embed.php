<?php

/**
 * Embed
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Preview\Chain\Strategy\External;

use Embed\Embed as EmbedLibrary;

class Embed extends Base
{
    /**
     * Test the oEmbed capability
     * http://www.oembed.com/
     *
     * @return array|null
     */
    public function test(): ?array
    {
        $embed     = new EmbedLibrary();
        $info      = $embed->get($this->url);
        $embedCode = $info->code->html;

        /* If the url is the same as $embedCode, nothing to replace
         */
        if ($embedCode != $this->getUrl()) {
            return [
                'type' => 'inline',
                'src'  => null,
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
