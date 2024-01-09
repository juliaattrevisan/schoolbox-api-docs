<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Twig\Error\Error;

class Controller extends Base
{
    /**
     * The name of this report which appears when you bookmark it
     * in yer browser
     */
    private const REPORT_TITLE = 'OLC Superstar Report';


    /**
     * An index of (unit spec id, badge id) tuples for awarding stuff
     * obtained via Justin
     */
    private array $badgeMapping = [
        // OLC 23
        '20240122' => [
            // 1. Fundamentals
            '139' => [
                Candidacy::SUPERSTAR => [
                    'id' => 28,
                    'route' => null
                ],
                Candidacy::STAR => [
                    'id' => 46,
                    'route' => null
                ]
            ],
            // 2. Teaching & Learning
            '138' => [
                Candidacy::SUPERSTAR => [
                    'id' => 27,
                    'route' => null
                ],
                Candidacy::STAR => [
                    'id' => 48,
                    'route' => null
                ]
            ],
            // 3. Marcomms
            '142' => [
                Candidacy::SUPERSTAR => [
                    'id' => 32,
                    'route' => null
                ],
                Candidacy::STAR => [
                    'id' => 50,
                    'route' => null
                ]
            ],
            // 4. Pastoral Care
            '141' => [
                Candidacy::SUPERSTAR => [
                    'id' => 29,
                    'route' => null
                ],
                Candidacy::STAR => [
                    'id' => 47,
                    'route' => null
                ]
            ],
            // 5. Extra-Curricular
            '140' => [
                Candidacy::SUPERSTAR => [
                    'id' => 30,
                    'route' => null
                ],
                Candidacy::STAR => [
                    'id' => 45,
                    'route' => null
                ]
            ],
            // 6. Superuser
            '137' => [
                Candidacy::SUPERSTAR => [
                    'id' => 31,
                    'route' => null
                ],
                Candidacy::STAR => [
                    'id' => 49,
                    'route' => null
                ]
            ],
        ],
        // OLC 24
        '20241231' => [
            // 1. Fundamentals
            '139' => [
                Candidacy::SUPERSTAR => [
                    'id' => 34,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 43,
                    'route' => null,
                ],
            ],
            // 2. Teaching & Learning
            '138' => [
                Candidacy::SUPERSTAR => [
                    'id' => 35,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 44,
                    'route' => null,
                ],
            ],
            // 3. Marcomms
            '142' => [
                Candidacy::SUPERSTAR => [
                    'id' => 36,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 41,
                    'route' => null,
                ],
            ],
            // 4. Pastoral Care
            '141' => [
                Candidacy::SUPERSTAR => [
                    'id' => 37,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 42,
                    'route' => null,
                ],
            ],
            // 5. Extra-Curricular
            '140' => [
                Candidacy::SUPERSTAR => [
                    'id' => 33,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 39,
                    'route' => null,
                ],
            ],
            // 6. Superuser
            '137' => [
                Candidacy::SUPERSTAR => [
                    'id' => 38,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 40,
                    'route' => null,
                ],
            ],
        ]
    ];


    /**
     * Controller constructor.
     */
    public function __construct()
    {
    }


    /**
     * Perform an in-place update of badge mapping routes
     * so we don't need to recalculate the same route repeatedly
     *
     * (each route takes a json payload anyway)
     *
     * @return void
     * @throws RouteNotFound
     */
    public function prepareRoutes(): void
    {
        /* Do this sharpish by avoiding adf/carbon instantiation
         * TODO Possibly parameterise this somehow
         */
        $now = date('Ymd');

        foreach ($this->badgeMapping as $date => &$unitSpecs) {
            if ($now <= (int)$date) {
                foreach ($unitSpecs as $unitSpecId => &$actionables) {
                    foreach ($actionables as $type => &$actionable) {
                        $actionable['route'] = $this->routeGenerator->generate('learningBadgeAward.awardAsAchievement', [
                            'badgeId' => $actionable['id'],
                        ]);
                    }

                    unset($actionable);
                }

                unset($actionables);

                /* We only care about this set
                 */
                $this->badgeMapping = $unitSpecs;

                return;
            }
        }
    }


    /**
     * Show a list of people who could potentially be awarded Star or Superstar
     * badges for accomplishing 99% and 100% respectively, in their Units
     *
     * @throws Error
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws RouteNotFound
     * @throws \PDOException
     */
    public function getCandidates(): void
    {
        /* Sequential dependency
         *
         * Pre-generate urls for each badge because we need to post to them
         * (Each unit spec has a particular badge to award...)
         */
        $this->prepareRoutes();

        /* Get all the data ready; you might want to filter this to a particular
         * unit spec or something like that, for performance reasons...one day
         */
        $stack = (new Payload($this->getDi()->get('databaseInternal')))->generate();

        $this->response = $this->responseFromTemplate('@superstar/index.twig', [
            'report' => [
                'title' => self::REPORT_TITLE,
            ],
            'badgeMapping' => $this->badgeMapping,
            'stack' => $stack
        ], self::REPORT_TITLE);
    }


    /**
     * Award a badge to a particular person
     */
    public function postAward(): void
    {
    }
}
