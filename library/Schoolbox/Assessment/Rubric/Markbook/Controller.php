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
                'capabilities' => [
                    0 => [
                        'name' => 'Capability A',
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                            ],
                        ]
                    ],
                    1 => [
                        'name' => 'Capability B',
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                            ],
                        ]
                    ]
                ]
            ],
            1 => [
                'name' => 'Assessment 2 - marking indicators only',
                'capabilities' => [
                    0 => [
                        'name' => 'Capability A',
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                            ],
                        ]
                    ],
                    1 => [
                        'name' => 'Capability B',
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                            ],
                        ]
                    ]
                ]
            ],
            2 => [
                'name' => 'Assessment 3 - marking both',
                'capabilities' => [
                    0 => [
                        'name' => 'Capability A',
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                            ],
                            1 => [
                                'name' => 'Indicator 2',
                            ],
                        ]
                    ],
                    1 => [
                        'name' => 'Capability B',
                        'indicators' => [
                            0 => [
                                'name' => 'Indicator 1',
                            ],
                            1 => [
                                'name' => 'Indicator 2',
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
                            'mark' => '10/10',
                            'indicators' => [
                                0 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => '10/15',
                            'indicators' => [
                                0 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                1 => [
                    'capabilities' => [
                        0 => [
                            'mark' => null,
                            'indicators' => [
                                0 => [
                                    'mark' => 'A+',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => 'B+',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'normalisedMark' => null,
                            'indicators' => [
                                0 => [
                                    'mark' => 'D',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => 'C-',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                2 => [
                    'capabilities' => [
                        0 => [
                            'mark' => '95%',
                            'indicators' => [
                                0 => [
                                    'mark' => '100%',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '100%',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => '55%',
                            'indicators' => [
                                0 => [
                                    'mark' => '50%',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '60%',
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
                            'mark' => '7/10',
                            'indicators' => [
                                0 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => '5/15',
                            'indicators' => [
                                0 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                1 => [
                    'capabilities' => [
                        0 => [
                            'mark' => null,
                            'indicators' => [
                                0 => [
                                    'mark' => '5/15',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '5/5',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => null,
                            'indicators' => [
                                0 => [
                                    'mark' => '8/10',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '4/10',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                2 => [
                    'capabilities' => [
                        0 => [
                            'mark' => '50%',
                            'indicators' => [
                                0 => [
                                    'mark' => '4/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '4/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => '100%',
                            'indicators' => [
                                0 => [
                                    'mark' => '8/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '8/8',
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
                            'mark' => '5/10',
                            'indicators' => [
                                0 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => '10/15',
                            'indicators' => [
                                0 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => null,
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                1 => [
                    'capabilities' => [
                        0 => [
                            'mark' => null,
                            'indicators' => [
                                0 => [
                                    'mark' => '8/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '5/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => null,
                            'indicators' => [
                                0 => [
                                    'mark' => '1/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => '2/8',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ]
                    ]
                ],
                2 => [
                    'capabilities' => [
                        0 => [
                            'mark' => 'A+',
                            'indicators' => [
                                0 => [
                                    'mark' => 'A+',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => 'A+',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 2'
                                ],
                            ]
                        ],
                        1 => [
                            'mark' => 'C',
                            'indicators' => [
                                0 => [
                                    'mark' => 'B',
                                    'selectedDescriptorText' => 'I am some descriptor text for Indicator 1'
                                ],
                                1 => [
                                    'mark' => 'D',
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
