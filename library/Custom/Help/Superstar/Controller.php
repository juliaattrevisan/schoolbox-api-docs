<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2024, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Custom\Help\Superstar;

use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Schoolbox\Mapper\DefinitionException;
use Schoolbox\Mapper\Exception\Deleted;
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
     *
     * You can curate this list over time, removing old entries once you no longer
     * have a use for them - otherwise this will get hilariously long and hard to manage.
     * They're all hardcoded assignations anyway and you can restore an old set from
     * github if you ever needed that
     */
    private array $badgeMapping = [
        /* OLC 23
         * Note that this has a special case implementation for the "6. Superstar" unit,
         * where there's an assessment outside of a project
         */
        2023 => [
            // 1. Fundamentals
            // 90 in vagrant; 139 in actual
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
        2024 => [
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
        ],
        // OLC 25
        2025 => [
            // 1. Fundamentals
            '139' => [
                Candidacy::SUPERSTAR => [
                    'id' => 78,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 77,
                    'route' => null,
                ],
            ],
            // 2. Teaching & Learning
            '138' => [
                Candidacy::SUPERSTAR => [
                    'id' => 80,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 79,
                    'route' => null,
                ],
            ],
            // 3. Marcomms
            '142' => [
                Candidacy::SUPERSTAR => [
                    'id' => 81,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 85,
                    'route' => null,
                ],
            ],
            // 4. Pastoral Care
            '141' => [
                Candidacy::SUPERSTAR => [
                    'id' => 82,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 86,
                    'route' => null,
                ],
            ],
            // 5. Extra-Curricular
            '140' => [
                Candidacy::SUPERSTAR => [
                    'id' => 83,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 88,
                    'route' => null,
                ],
            ],
            // 6. Superuser
            '137' => [
                Candidacy::SUPERSTAR => [
                    'id' => 84,
                    'route' => null,
                ],
                Candidacy::STAR => [
                    'id' => 87,
                    'route' => null,
                ],
            ],
        ],
    ];


    /**
     * Controller constructor.
     */
    public function __construct(private \Schoolbox\RoleMapper $roleMapper)
    {
    }


    /**
     * Redirect you to the first available set of badges you can award.
     *
     * @return void
     * @throws RouteNotFound
     * @throws \DomainException
     */
    public function getRedirect(): void
    {
        $year = array_keys($this->badgeMapping)[0] ?? null;

        if (null === $year) {
            throw new \DomainException('Hardcode (unit spec id, badge id) tuples.');
        }

        $this->response = $this->redirectFound($this->routeGenerator->generate('superstar.getCandidates', ['year' => $year]));
    }


    /**
     * Show a list of people who could potentially be awarded Star or Superstar
     * badges for accomplishing 99% and 100% respectively, in their Units
     *
     * @param int $year
     * @throws ContainerExceptionInterface
     * @throws Error
     * @throws NotFoundExceptionInterface
     * @throws RouteNotFound
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \LogicException
     * @throws \PDOException
     * @throws DefinitionException
     * @throws Deleted
     * @throws \UnexpectedValueException
     */
    public function getCandidates(int $year): void
    {
        /* Sequential dependency
         *
         * Pre-generate urls for each badge because we need to post to them
         * (Each unit spec has a particular badge to award...)
         */
        $this->prepareRoutes($year);

        /* An injectable
         */
        $database = $this->getDi()->get('databaseInternal');

        /* A list of previously-obtained badges obtained by guys
         */
        $previousAwards = (new PreviousAwards($database))->generate($this->badgeMapping[$year]);

        /* Get all the data ready; you might want to filter this to a particular
         * unit spec or something like that, for performance reasons...one day
         */
        $stack = (new Payload($database, $this->getDi()->get('assessmentDataSource'), $this->roleMapper))->generate($year);

        $this->response = $this->responseFromTemplate('@superstar/index.twig', [
            'report' => [
                'title' => self::REPORT_TITLE,
                'year' => $year
            ],
            'badgeYears' => array_keys($this->badgeMapping),
            'badgeMapping' => $this->badgeMapping[$year],
            'previousAwards' => $previousAwards,
            'stack' => $stack
        ], self::REPORT_TITLE);
    }


    /**
     * Perform an in-place update of badge mapping routes
     * so we don't need to recalculate the same route repeatedly
     *
     * (each route takes a json payload anyway)
     *
     * @param int $year
     * @return void
     * @throws RouteNotFound
     * @throws \DomainException
     */
    private function prepareRoutes(int $year): void
    {
        if (!isset($this->badgeMapping[$year])) {
            throw new \DomainException('Badge mapping for this year is missing.');
        }

        foreach ($this->badgeMapping[$year] as $unitSpecId => &$actionables) {
            foreach ($actionables as $type => &$actionable) {
                $actionable['route'] = $this->routeGenerator->generate('learningBadgeAward.awardAsAchievement', [
                    'badgeId' => $actionable['id'],
                ]);
            }

            unset($actionable);
        }
    }
}
