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

$twig = $this->getTwig();

// we got postdata
if (!empty($_POST)) {

    $context = [
        'rubric' => [
            'id' => $_POST['rubricId'],
            'levels' => array_fill(0, $_POST['levelCount'], ':)'),
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

    echo "<tr><td>{$_POST['name']}</td></tr>";

    exit;
}



$map = [
    'Format'       => [
        'Presentation' => []
    ],
    'Language'     => [
        'Argumentative Language'           => [],
        'Comparisons between Characters'   => [],
        'Descriptions of Characters'       => [],
        'Explanation of the Problem'       => [],
        'Grammar'                          => [
            [ 'Grammar is correct', 4 ],
            [ 'Grammatical errors are made (eg. agreements, verb conjugation, gender)', 3 ],
            [ 'Grammatical errors hinder understanding in some sentences', 2 ],
        ],
        'Language Used'                    => [],
        'Negative Phrases'                 => [],
        'Relationships between Characters' => [],
        'Spelling'                         => [
            [ 'Accents are missing from some words', 3 ],
            [ 'Some words are misspelt', 2 ],
            [ 'Spelling errors hinder understanding of some words', 1 ],
            [ 'Spelling errors hinder understanding', 0 ],
        ],
    ],
    'Presentation' => [
        'Participation' => [],
        'Performance'   => [],
        'Pronunciation' => [],
        'Use of Script' => [],
    ],
    'Task'         => [
        'Content'      => [
            [ 'Student creates a cd cover which includes a title, a list of 10-15 songs, and an insert which gives a brief promotion of each song explaining the genre of the song, and whether they like it or not and why', 3 ],
            [ 'Student includes most of the information described at the expected level', 2 ],
            [ 'Student includes some of the information described at the expected level', 1 ],
            [ 'No content included', 0 ],
        ],
        'Presentation' => [
            [ 'Student presents their work in a cd cover format including front cover with title, track list and original album artwork with cd insert inside the lid', 4 ],
            [ 'Student presents their work in a cd cover format including front cover with title, track list and album artwork', 3 ],
            [ 'Student presents their work in a cd cover format including front cover with title and track list', 2 ],
        ],
    ]
];


$map = [

    'Geospacial Skills' => [
        'Data Collection' => [
            null,
            [ 'Can collect data by observing chosen locations in a neat and clear manner. Data has been represented on the map including a summary of the findings for the ‘Environment’ section.', 4 ],
            [ 'Can collect data by observing chosen locations in a neat and clear manner. Data has been transferred to a map completely, clearly and concisely.', 3 ],
            [ 'Can collect data by observing chosen locations. Data has been added to a map is unclear or incomplete.', 2 ],
            [ 'Can collect data by observing chosen locations. Data not transferred to the map.', 1 ],
        ],
        'Mapping' => [
            [ 'The map contains all of the required information and is neat, clear and well presented.', 5 ],
            [ 'The map includes all required information, however it lacks the neatness and clarity required.', 4 ],
            [ 'The map is complete with BOLTSS and includes the data collected from the fieldwork observations, however it is missing one of the following: sources of pollution or water direction,', 3 ],
            [ 'The map includes all data from the fieldwork observations, however it is missing two of the following: sources of pollution, water direction, or BOLTSS.', 2 ],
            [ 'An attempt has been made to create a map, however the map is very incomplete.', 1 ],
        ],
    ],
    'Knowledge' => [
        'Describing water quality' => [
            null,
            [ 'Has explained in detail the impacts of human activities and physical processes on the water quality and made links to each of the sites visited.', 4 ],
            [ 'Has explained how human activities and physical processes impact on water quality of the region.', 3 ],
            [ 'Has identified some basic impacts of land use and the surrounding environment on water quality, but needs to expand on these ideas.', 2 ],
            [ 'Has not identified the impacts of the surrounding environment and land uses on water quality.', 1 ],
        ],
        'Improving water quality' => [
            null,
            [ 'Has come up with an effective solution to improve water quality in the area including detailed strategies of how this could be done.', 4 ],
            [ 'Has described a workable solution to improve water quality in the area with some detail of strategies.', 3 ],
            [ 'Has made a basic attempt to describe what could be done to improve water quality in the area.', 2 ],
            [ 'Has made a basic attempt to describe what could be done to improve water quality in the area, but the idea is unrealistic.', 1 ],
        ],
    ],
    '' => [
        'Participation' => [
            null,
            null,
            [ 'Collected all required data and actively participated in the class discussions at each site visited. Very focused in class and completed the work without distraction.', 3 ],
            [ 'Collected all required data and made some attempts to participate in class discussions at each site. Mostly focused on completing the report and map in class.', 2 ],
            [ 'Collected all required data but did not contribute to the class discussion at each site. Was a bit distracted in class when completing the report and map.', 1 ],
        ]
    ]

];

$map = [
    'Language' => [
        'Grammar' => [
            [ 'Grammar is correct', 4 ],
            [ 'Grammatical errors are made (eg. agreements, verb conjugation, gender)', 3 ],
            [ 'Grammatical errors hinder understanding in some responses', 2 ],
            null,
            [ 'Grammatical errors hinder understanding', 0 ],
        ],
        'Language Used' => [
            null,
            [ 'Student speaks in French', 3 ],
            null,
            [ 'Student attempts to speak in French but reverts to English for some words', 1 ],
            [ 'Student speaks in English', 0 ],
        ],
    ],
    'Content' => [
        'Card Information' => [
            [ 'Student completes all required components of the card and adds own phrases', 4 ],
            [ 'Student completes all required components of the card', 3 ],
            [ 'Student attempts to cover all required information on the card', 2 ],
            null,
            [ 'No content covered', 0 ],
        ],
    ],
    'Conversation Skills' => [
        'Understanding' => [
            [ 'Student understands all questions asked and information given', 4 ],
            [ 'Student understands all questions and information with help from the teacher', 3 ],
            [ 'Student understands most questions and information with help from the teacher', 2 ],
            [ 'Student relies heavily on the teacher for comprehension', 1 ],
            [ 'No understanding of questions asked or information given', 0 ],
        ],
        'Responses to Questions' => [
            [ 'Student responds to question in full sentences', 4 ],
            [ 'Student responds to all questions', 3 ],
            [ 'Student responds to some questions with help from the teacher', 2 ],
            [ 'Student relies heavily on the teacher to form responses to questions', 1 ],
            [ 'No responses to questions', 0 ],
        ],
        'Pronunciation' => [
            [ 'Student demonstrates knowledge of the French pronunciation (eg. vowel sounds, silent letters, "r" sound)', 4 ],
            [ 'Pronunciation is clear', 3 ],
            [ 'Pronunciation can be understood most of the time', 2 ],
            null,
            [ 'Pronunciation is unclear and hinders understanding', 0 ],
        ],
    ],
    '' => [],
];


$factory = new Factory();
$rubric  = $factory->buildFromArrayMap(
    [ 'Wow! (4)', 'Expected (3)', 'Building (2)', 'Emerging (1)', 'Not Shown (0)' ],
    $map
);
//$rubric = $factory->buildEmpty();

$tbl = $twig->render('rubric-editable.twig', [ 'rubric' => $rubric->toContext() ]);

$this->setVar('OUTPUT', $tbl);
