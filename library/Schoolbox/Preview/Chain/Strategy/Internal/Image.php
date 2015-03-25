<?php
/**
 * Image
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain\Strategy\Internal;


class Image extends Base {

    /**
     * Test
     *
     * @return mixed
     */
    public function test() {
        return [
            'type' => 'html-fragment',
            'twig' => [
                'template' => '@preview/Strategy/Image.twig',
                'data' => [
                    'src' => $this->getUrl()
                ]
            ]
        ];

        return $this->testNext();
    }
} 