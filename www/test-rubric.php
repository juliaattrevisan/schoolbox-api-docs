<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2015, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

use Schoolbox\Assessment\Rubric\Factory;

/** @var SitePage $this */

if (empty($this)) {
    die("No sitepage context");
}

$this->setFile(TEMPLATE_content, 'output.html');

$formField = 'rubberick';

$twig = $this->getTwig();

$factory = new Factory();

// we got postdata
if (!empty($_POST)) {

    $context = [
        'formField' => $formField,
        'rubric' => [
            'id' => $_POST['rubricId'],
            'levels' => $_POST['levelCount'] ? array_fill(0, $_POST['levelCount'], ':)') : [],
        ],
    ];


    if ($_POST['type'] == 'criterion') {

        $context['rowspan'] = null;
        $context['group'] = [
            'id' => $_POST['groupId']
        ];
        $context['crit'] = [
            'name' => $_POST['name'],
            'id'   => rand(1, PHP_INT_MAX),
            'descriptors' => [],
            'used' => false,
        ];

        die($twig->render('rubric-group-row-editable.twig', $context));
    }

    elseif ($_POST['type'] == 'group') {

        $context['rowspan'] = 1;
        $context['group'] = [
            'id'   => rand(1, PHP_INT_MAX),
            'name' => $_POST['name'],
            'used' => false,
        ];

        die($twig->render('rubric-group-editable.twig', $context));

    }

    else {

        $rubric = $factory->buildFromPostData($_POST[$formField], false);

        $tbl = $twig->render('rubric-markable.twig', [ 'formField' => $formField, 'rubric' => $rubric->toContext() ]);

        $this->setVar('OUTPUT', $tbl);

    }

} else {

    $postdata = [
        '4144909' => [
            'level' => [
                [ 'name' => 'Wow!' ],
                [ 'name' => 'Expected' ],
                [ 'name' => 'Building' ],
                [ 'name' => 'Emerging' ],
                [ 'name' => 'Not Shown' ],
            ],
            'group' => [
                21 => [ 'name' => 'Language' ],
                22 => [ 'name' => 'Content' ],
                23 => [ 'name' => 'Conversation Skills' ],
            ],
            'criterion' => [
                31 => [
                    'groupId' => 21,
                    'on'      => 1,
                    'name'    => 'Grammar'
                ],
                32 => [
                    'groupId' => 21,
                    'on'      => 1,
                    'name'    => 'Language Used'
                ],
                33 => [
                    'groupId' => 22,
                    'on'      => 1,
                    'name'    => 'Card Information'
                ],
                34 => [
                    'groupId' => 23,
                    'on'      => 1,
                    'name'    => 'Understanding'
                ],
                35 => [
                    'groupId' => 23,
                    'on'      => 1,
                    'name'    => 'Responses to Questions'
                ],
                36 => [
                    'groupId' => 23,
                    'on'      => 1,
                    'name'    => 'Pronunciation'
                ],
            ],
            'descriptor' => [
                31 => [
                    [
                        'text' => 'Grammar is correct',
                        'points' => 4
                    ],
                    [
                        'text' => 'Grammatical errors are made (eg. agreements, verb conjugation, gender)',
                        'points' => 3
                    ],
                    [
                        'text' => 'Grammatical errors hinder understanding in some responses',
                        'points' => 2
                    ],
                    [
                        'text' => '',
                        'points' => ''
                    ],
                    [
                        'text' => 'Grammatical errors hinder understanding',
                        'points' => 0
                    ],
                ],
                32 => [
                    [
                        'text' => '',
                        'points' => ''
                    ],
                    [
                        'text' => 'Student speaks in French',
                        'points' => 3
                    ],
                    [
                        'text' => '',
                        'points' => ''
                    ],
                    [
                        'text' => 'Student attempts to speak in French but reverts to English for some words',
                        'points' => 1
                    ],
                    [
                        'text' => 'Student speaks in English',
                        'points' => 0
                    ],
                ],
                33 => [
                    [
                        'text' => 'Student completes all required components of the card and adds own phrases',
                        'points' => 4
                    ],
                    [
                        'text' => 'Student completes all required components of the card',
                        'points' => 3
                    ],
                    [
                        'text' => 'Student attempts to cover all required information on the card',
                        'points' => 2
                    ],
                    [
                        'text' => '',
                        'points' => ''
                    ],
                    [
                        'text' => 'No content covered',
                        'points' => 0
                    ],
                ],
                34 => [
                    [
                        'text' => 'Student understands all questions asked and information given',
                        'points' => 4
                    ],
                    [
                        'text' => 'Student understands all questions and information with help from the teacher',
                        'points' => 3
                    ],
                    [
                        'text' => 'Student understands most questions and information with help from the teacher',
                        'points' => 2
                    ],
                    [
                        'text' => 'Student relies heavily on the teacher for comprehension',
                        'points' => 1
                    ],
                    [
                        'text' => 'No understanding of questions asked or information given',
                        'points' => 0
                    ],
                ],
                35 => [
                    [
                        'text' => 'Student responds to question in full sentences',
                        'points' => 4
                    ],
                    [
                        'text' => 'Student responds to all questions',
                        'points' => 3
                    ],
                    [
                        'text' => 'Student responds to some questions with help from the teacher',
                        'points' => 2
                    ],
                    [
                        'text' => 'Student relies heavily on the teacher to form responses to questions',
                        'points' => 1
                    ],
                    [
                        'text' => '',
                        'points' => ''
                    ],
                ]
            ],
        ]
    ];


    $rubric = $factory->buildFromPostData($postdata, true);
    //$rubric = $factory->buildEmpty();


    $tbl = $twig->render('rubric-editable.twig', ['formField' => $formField, 'rubric' => $rubric->toContext()]);

    $this->setVar('OUTPUT', $tbl);
}
