<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Resource\Folder;

class Controller extends Base
{
    /**
     * Displays a list of rubric marks for students in the specified class
     *
     * @api   GET /learning/rubricMarkbook/{folderId}
     * @level 190
     * @param Folder\Model $folder                    Folder for this class
     * @throws ServiceNotFound                       If unable to locate HTML web Output
     * @throws \UnexpectedValueException             If folder not provided or could not be found
     */
    public function getMarkbook(Folder\Model $folder): void
    {
        $this->getResponse()->content->set(
            $this->buildHtmlOutput(
                '@rubricMarkbook/markbookOuter.twig',
                [
                    'students' => $this->generateMockMarksPerStudent(),
                    'assessments' => $this->generateMockAssessments(),
                    'folder' => $folder
                ]
            )->setTab('submission')->setTitle('Rubric Markbook for ' . $folder->getName())
        );
    }

    private function generateMockAssessments() : array
    {
        return [
            0 => [
                'name' => 'Assessment 1 - marking capabilities only',
                'markScheme' => 1,
                'capabilities' => [
                    0 => [
                        'name' => 'Capability A',
                        'maxMark' => 15,
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                                'maxMark' => null,
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                                'maxMark' => null,
                            ],
                        ]
                    ],
                    1 => [
                        'name' => 'Capability B',
                        'maxMark' => 15,
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                                'maxMark' => null,
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                                'maxMark' => null,
                            ],
                        ]
                    ]
                ]
            ],
            1 => [
                'name' => 'Assessment 2 - marking indicators only',
                'markScheme' => 2,
                'capabilities' => [
                    0 => [
                        'name' => 'Capability A',
                        'maxMark' => null,
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                                'maxMark' => 5,
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                                'maxMark' => 10,
                            ],
                        ]
                    ],
                    1 => [
                        'name' => 'Capability B',
                        'maxMark' => null,
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                                'maxMark' => 5,
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                                'maxMark' => 10,
                            ],
                        ]
                    ]
                ]
            ],
            2 => [
                'name' => 'Assessment 3 - marking both',
                'markScheme' => 3,
                'capabilities' => [
                    0 => [
                        'name' => 'Capability A',
                        'maxMark' => 15,
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                                'maxMark' => 5,
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                                'maxMark' => 10,
                            ],
                        ]
                    ],
                    1 => [
                        'name' => 'Capability B',
                        'maxMark' => 15,
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                                'maxMark' => 5,
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                                'maxMark' => 10,
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }

    private function generateMockMarksPerStudent() : array
    {
        // TODO sort me alphabetically
        $studentMarks = [
            230 => [
                'name' => 'David Rhodes',
                0 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                1 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                2 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ]
            ],
            229 => [
                'name' => 'Rachel Rhodes',
                0 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                1 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                2 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ]
            ],
            206 => [
                'name' => 'Maddison Rhodes',
                0 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                1 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                2 => [
                    'capabilities' => [
                        0 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => 10,
                            'indicators' => [
                                0 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'normalisedMark' => 5,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ]
            ]
        ];

        return $studentMarks;
    }

}
