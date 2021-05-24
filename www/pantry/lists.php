<?php

/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * @var SitePage $this
 */


$this->setFile(TEMPLATE_content, 'output.html');

$data = [
    'items' => [
        [ ],
        [ 'overflow' ],
        [ 'plus', 'minus' ]
    ],
    'subnav' => [
        '/' => 'Generic Content',
        '/cards' => 'Cards',
        '/buttons' => 'Buttons',
        '/colours' => 'Colours',
        '/icons' => 'Icons',
        '/typography' => 'Typography',
        '/editor' => 'Editor',
        '/form' => 'Form',
        '/form-progressive' => 'Form Progressive',
        '/tables' => 'Tables',
        '/lists' => 'Lists',
        '/feature-tour' => 'Feature Tour',
        '/reveal' => 'Reveal',
        '/reference' => 'Visibility Classes'

    ]
];

$this->setVar('OUTPUT', $this->getTwig()->render('pantry/lists.twig', $data));
