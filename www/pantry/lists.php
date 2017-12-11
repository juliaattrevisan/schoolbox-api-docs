<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
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
        '/buttons' => 'Buttons',
        '/colours' => 'Colours',
        '/icons' => 'Icons',
        '/typography' => 'Typography',
        '/editor' => 'Editor',
        '/layout' => 'Layouts',
        '/form' => 'Form',
        '/form-progressive' => 'Form Progressive',
        '/tables' => 'Tables',
        '/lists' => 'Lists',
        '/feature-tour' => 'Feature Tour',
        '/reveal' => 'Reveal',
        '/redux' => 'Sample Entities',
        '/reference' => 'Visibility Classes'

    ]
];

$this->setVar('OUTPUT', $this->getTwig()->render('pantry/lists.twig', $data));
