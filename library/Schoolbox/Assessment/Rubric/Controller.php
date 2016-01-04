<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2016, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Assessment\Rubric;

use Alaress\Web\Controller\Base as BaseController;

class Controller extends BaseController
{

    /** @var Factory */
    protected $factory;

    /** @var Mapper */
    protected $mapper;


    /**
     * @return Factory
     */
    public function getFactory()
    {
        return $this->factory;
    }


    /**
     * @param Factory $factory
     * @return self
     */
    public function setFactory($factory)
    {
        $this->factory = $factory;
        return $this;
    }


    /**
     * @return Mapper
     */
    public function getMapper()
    {
        return $this->mapper;
    }


    /**
     * @param Mapper $mapper
     * @return self
     */
    public function setMapper($mapper)
    {
        $this->mapper = $mapper;
        return $this;
    }


    /**
     * AJAX method for creating a new group or criterion
     *
     * @api POST /rubric/create
     * @level 156
     */
    public function create()
    {
        $post = $this->getRequest()->post;

        $formField = $post->get('formField', 'rb');

        $context = [
            'formField' => $formField,
            'rubric' => [
                'id' => $post->get('rubricId'),
                'levels' => $post->get('levelCount') ? array_fill(0, $post->get('levelCount'), ':)') : [],
            ],
        ];


        if ($post->get('type') == 'criterion') {

            $context['rowspan'] = null;
            $context['group'] = [
                'id' => $post->get('groupId')
            ];
            $context['crit'] = [
                'name' => $post->get('name'),
                'id'   => rand(1, PHP_INT_MAX),
                'descriptors' => [],
                'used' => false,
            ];

            $template = 'group-row-editable';

        }

        elseif ($post->get('type') == 'group') {

            $context['rowspan'] = 1;
            $context['group'] = [
                'id'   => rand(1, PHP_INT_MAX),
                'name' => $post->get('name'),
                'used' => false,
            ];

            $template = 'group-editable';

        } else {
            return;
        }


        $this->getResponse()->content->set([
            'data'     => $context,
            'format'   => 'nochrome',
            'template' => "@rubric/$template.twig"
        ]);

    }

    public function testGeneric()
    {
        $rid = rand(10000, 100000);

        $postdata = [
            $rid => [
                'level' => [
                    [ 'name' => 'Wow!' ],
                    [ 'name' => 'Expected' ],
                    [ 'name' => 'Building' ],
                    [ 'name' => 'Emerging' ],
                    [ 'name' => 'Not Shown' ],
                    [ 'name' => 'foo' ],
                    [ 'name' => 'bar' ],
                    [ 'name' => 'baz' ],
                    [ 'name' => 'bing' ],
                    [ 'name' => 'boop' ],
                    [ 'name' => 'bang' ],
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
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
                [   31 => [ [ 'text' => 'Student relies heavily on the teacher to form responses to questions' ] ]   ],
            ]
        ];

        // level cutoff
        if (isset($_GET['levels']) && $_GET['levels']) {
            $postdata[$rid]['level'] = array_slice($postdata[$rid]['level'], 0, $_GET['levels']);
            $postdata[$rid]['descriptor'] = array_slice($postdata[$rid]['descriptor'], 0, $_GET['levels']);

        }

        $this->outputTest($this->getFactory()->buildFromPostData($postdata, true));
    }
    public function testEmpty()
    {
        $this->outputTest($this->getFactory()->buildEmpty());
    }
    public function testBox($boxId)
    {
        $this->outputTest($this->getMapper()->findBySubmissionBoxId($boxId));
    }
    public function testFolder($folderId)
    {
        $this->outputTest($this->getMapper()->findByFolderId($folderId));
    }

    protected function outputTest(Rubric $grid)
    {
        $this->getResponse()->content->set([
            'data' => [
                'formField' => 'rubberick',
                'rubric' => $grid->toContext()
            ],
            'template' => '@rubric/grid-editable.twig'
        ]);
    }

}
