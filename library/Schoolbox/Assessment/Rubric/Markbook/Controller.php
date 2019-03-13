<?php
/**
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Assessment\Rubric\Markbook;

use Alaress\Web\Controller\Base;
use Aura\Di\Exception\ServiceNotFound;
use Schoolbox\Assessment\Component\SubmissionBox\MarkType;
use Alaress\Database\Connector\IConnector;
use Schoolbox\Learning\MarkScheme;
use Schoolbox\Resource\Folder;

class Controller extends Base
{

    /**
     * @var IConnector
     */
    private $database;

    /**
     * Constructor
     *
     * @param IConnector $database
     */
    public function __construct(IConnector $database)
    {
        $this->database = $database;
    }

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
        $this->getRawMarkbookData($folder->getId());

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

    /**
     * @param int $folderId
     * @return array
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \RangeException
     */
    private function getRawMarkbookData(int $folderId) : array
    {
        $data = [];
        $getAllAssessmentsInFolderQuery = $this->database->prepareQuery("SELECT var.assessment_id,
                    a.title AS assessment_name,
                    vrm.assessment_mark_type,
                    vrm.assessment_max_mark,
                    vrm.student_id,
                    vrm.capability_id,
                    vrm.capability_name,
                    vrm.capability_mark,
                    vrm.capability_max_value,
                    vrm.indicator_id,
                    vrm.indicator_name,
                    vrm.indicator_mark,
                    vrm.indicator_max_value,
                    vrm.descriptor_id,
                    vrm.descriptor_name,
                    vrm.descriptor_max_value
                    FROM       v_rubric_mark vrm
                    INNER JOIN v_assessment_relation var ON vrm.assessment_id = var.assessment_id
                    INNER JOIN v_most_recent_feedback vmrf ON var.assessment_id = vmrf.assessment_id
                    INNER JOIN assessment a ON a.id = vmrf.assessment_id
                    WHERE      var.folder_id=:folderId and vrm.feedback_type='instructor'");

        $getAllAssessmentsInFolderQuery->execute(['folderId' => $folderId]);

        $assessments = [];
        $studentMarks = [];

        foreach ($getAllAssessmentsInFolderQuery->getIterator() as $i => $row) {

            // Stuff everything about the assessment into an array
            $assessments[$row['assessment_id']] = [
                'name' => $row['assessment_name'],
                'maxMark' => $row['assessment_max_mark'],
                'capabilities' => [
                    $row['capability_id'] => [
                        'name' => $row['capability_name'],
                        'maxMark' => $row['capability_max_value'],
                        'indicators' => [
                            $row['indicator_id'] => [
                                'name' => $row['indicator_name'],
                                'maxMark' => $row['indicator_max_value']
                            ]
                        ]
                    ]
                ]
            ];

            $marktype = null;
            switch ($row['assessment_mark_type']) {
                // Yes, I know these are deprecated, but we have to use them
                case MarkType::TYPE_PERCENT:
                    $marktype = new MarkScheme\Percentage();
                    break;
                case MarkType::TYPE_NONE:
                    $marktype = new MarkScheme\None();
                    break;
                case MarkType::TYPE_TOTAL:
                    $maxMark = $row['capability_max_value'] ?? $row['assessment_max_mark'];
                    $marktype = new MarkScheme\TotalMarkFractional($maxMark);
                    break;
                case MarkType::TYPE_LETTER:
                    // TODO how can we get the grade group?
                    $marktype = new MarkScheme\LetterGrade($dueWork->getGradeGroup());
                    break;
                default:
                    throw new \DomainException('Invalid MarkType for DueWork: ' . $row['assessment_name']);
            }

            $markScheme = new MarkScheme\Percentage();
            $humanReadableCapabilityMark = $markScheme->getHumanReadableMark($capabilityMark);
            $humanReadableIndicatorMark = $markScheme->getHumanReadableMark($indicatorMark);

            // Stuff everything about the students marks into an array
            $studentMarks[$row['student_id']] = [
                'name' => $row['student_name'],
                $row['assessment_id'] => [
                    'capabilities' => [
                        $row['capability_id'] => [
                            'mark' => $humanReadableCapabilityMark,
                            'indicators' => [
                                $row['indicator_id'] => [
                                    'mark' => $humanReadableIndicatorMark,
                                    'selectedDescriptorText' => $row['descriptor_name']
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        }

        return $data;
    }

}
