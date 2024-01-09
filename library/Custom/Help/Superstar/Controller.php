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
use DebugBar\DebugBarException;
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
            '90' => [
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
     * Show a list of people who could potentially be awarded Star or Superstar
     * badges for accomplishing 99% and 100% respectively, in their Units
     *
     * @throws ContainerExceptionInterface
     * @throws Error
     * @throws NotFoundExceptionInterface
     * @throws RouteNotFound
     * @throws DebugBarException
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

        /* A list of previously-obtained badges obtained by guys
         */
        $previousAwards = $this->previousAwards($this->badgeMapping);

        /* Get all the data ready; you might want to filter this to a particular
         * unit spec or something like that, for performance reasons...one day
         */
        $stack = (new Payload($this->getDi()->get('databaseInternal')))->generate();

        new \Alaress_Probe($stack, $this->badgeMapping, $previousAwards);

        $this->response = $this->responseFromTemplate('@superstar/index.twig', [
            'report' => [
                'title' => self::REPORT_TITLE,
            ],
            'badgeMapping' => $this->badgeMapping,
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
     * Find all the previous badges awarded to people as per $badgeMapping set
     * We don't want to give the same achievement twice and the nuanced logic
     * makes this a bit harder in native sql megaqueries
     *
     * Instead we construct a list of previously awarded badges and do the
     * subtraction at runtime, which also allows us to say when someone actually
     * got the achievement.
     *
     * @param array $badgeMapping
     * @return array
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function previousAwards(array $badgeMapping): array
    {
        $ids = [];

        foreach ($badgeMapping as $unitSpecId => $mapping) {
            $ids[] = (int)$mapping[Candidacy::SUPERSTAR]['id'];
            $ids[] = (int)$mapping[Candidacy::STAR]['id'];
        }

        /* We're going to inject this straight in to the jugular
         *   1. It's hardcoded by us, manually, so the usual risk of sql injection avoided
         *   2. It's always cast to int, not that we'd ever sql inject ourselves unsafely anyway
         *   3. It's only surfaced in an admin-only report
         *   4. If you hate it enough you can predicate-builder this, but it goes slower
         *
         * If you have an omega ton of ids you might reach a query limit...but I doubt this will
         * be a practical concern. The OLC wouldn't be manageable under such circumstances.
         */
        $badgeIds = implode(',', $ids);

        $query = "
            SELECT 
                badge_id, 
                recipient_id,
                awarded_at
            FROM badge_user
            WHERE badge_id IN ({$badgeIds})
            AND status = :status
        ";

        $statement = $this->getDi()->get('databaseInternal')->prepare($query);
        $statement->execute([
            'status' => \Schoolbox\Learning\Badge\Award\Model::AWARD_STATUS_ACHIEVEMENT
        ]);

        /* Remember a list of (user id, badge id) tuples
         * for a direct lookup via twig ugh
         */
        $stack = [];

        while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
            $stack[$row['recipient_id']][$row['badge_id']] = $row['awarded_at'];
        }

        return $stack;
    }


    /**
     * Award a badge to a particular person
     */
    public function postAward(): void
    {
    }
}
