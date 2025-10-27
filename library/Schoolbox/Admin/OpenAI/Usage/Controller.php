<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Usage;

use Alaress\Paginator;
use Alaress\Web\Controller\Base;
use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Editor\OpenAI\Log\Model as AILog;
use Schoolbox\Admin\OpenAI\Config as AIConfig;
use Schoolbox\Editor\OpenAI\Log\ModelToArray;
use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;
use Schoolbox\User;

class Controller extends Base
{
    /**
     * Maximum number of logs per page
     */
    private const int MAX_PAGE_SIZE = 100;


    /**
     * Controller constructor.
     *
     * @param AILogMapper $aiLogMapper
     * @param ConfigMapper $configMapper
     * @param UserMapper $userMapper
     */
    public function __construct(
        private AILogMapper $aiLogMapper,
        private ConfigMapper $configMapper,
        private UserMapper $userMapper,
    ) {
    }


    /**
     * Show users a short form that lets them configure the filters for AI Sessions that we logged.
     * This lets them interrogate our openai_session table for log instances.
     *
     * @return void
     * @throws \Aura\Router\Exception\RouteNotFound
     * @throws \JsonException
     * @throws \Twig\Error\Error
     */
    public function getIndex(): void
    {
        $features = new AIConfig()->fetchFeatures($this->configMapper);

        // add on image as a feature
        $features['image'] = [
            'title' => 'Image Generation',
        ];

        // only include the title as that's all that the frontend needs here
        $features = array_map(fn($item) => [
            'title' => $item['title']
        ], $features);

        $this->response = $this->responseFromTemplate('@openAIUsage/index.twig', [
            'features' => $features,
            'pageCurrent' => $this->routeGenerator->generate('openAIUsage.getIndex'),
            'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($this->routeGenerator->generate('openAIUsage.getIndex')),
        ]);
    }

    /**
     * Returns the next chunk of logs based on the cursor provided
     *
     * @return void
     * @throws RouteNotFound
     * @throws Exception
     */
    public function postSearch(): void
    {
        $keyword = $this->getRequestPost()->get('keyword');
        $user = $this->getRequestPost()->get('user');
        $feature = $this->getRequestPost()->get('feature');
        $after = $this->getRequestPost()->get('after');
        $before = $this->getRequestPost()->get('before');

        $page = $this->getRequestPost()->getInt('page', 1);
        $limit = $this->getRequestPost()->getInt('limit', self::MAX_PAGE_SIZE);

        //ensure feature is one of the actual features...
        if ($feature) {
            if (!array_key_exists($feature, array_merge(AIConfig::ALL_MODULES, ['image']))) {
                $message = sprintf(
                    '%s is an unknown AI feature.',
                    $feature
                );

                $problem = (new ApiProblem($message))
                    ->setStatus(404)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(404);
                return;
            }
        }

        // ensue the user exists (allow deleted)
        if ($user) {
            $userObj = $this->userMapper->findByIdWithFallback($user);

            if (null === $userObj->getId()) {
                $problem = (new ApiProblem('Unknown User'))
                    ->setStatus(404)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(404);
                return;
            }
        }

        /* Note that date sequence is enforced only by frontend javascript:
         * having start date after end date will simply return an empty set if you somehow construct that
         * scenario in the frontend.
         */

        /* Start Date
         * $after from the POST request is in RFC 3339 format: 2025-10-01T00:00:00+10:00
         */
        $afterADF = null;

        if ($after) {
            try {
                $afterADF = new \Alaress_Date_Format($after, \Alaress_Date_Format::FORMAT_RFC3339_W3C);
            } catch (\InvalidArgumentException $e) {
                $message = sprintf(
                    '%s is an invalid start date or in the incorrect format.',
                    $after
                );

                $problem = (new ApiProblem($message))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }
        }

        /* End Date
         * $before from the POST request is in RFC 3339 format: 2025-10-01T00:00:00+10:00
         */
        $beforeADF = null;

        if ($before) {
            try {
                $beforeADF = new \Alaress_Date_Format($before, \Alaress_Date_Format::FORMAT_RFC3339_W3C);
            } catch (\InvalidArgumentException $e) {
                $message = sprintf(
                    '%s is an invalid end date or in the incorrect format.',
                    $before
                );

                $problem = (new ApiProblem($message))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }
        }

        // new db query space...
        $logGroups = $this->aiLogMapper->getGroupedLogs(
            $keyword,
            $feature,
            $userObj ?? null,
            $afterADF,
            $beforeADF,
            $limit,
            ($page - 1) * $limit,
        );

        // Find total counts
        $count = $this->aiLogMapper->countGroupedLogs(
            $keyword,
            $feature,
            $userObj ?? null,
            $afterADF,
            $beforeADF,
        )[0];

        $pageSize = min($limit, self::MAX_PAGE_SIZE);

        $paginator = (new Paginator\Paginator())
            ->setTotal($count)
            ->setPageSize($pageSize);

        $userTranslator = new User\Translator\Author\ModelToArray();

        $sessions = [];

        //translate each group of session ID's
        foreach ($logGroups as $sessionId => $log) {
            $sessions[$sessionId] = [
                'id' => $sessionId,
                'feature' => $log['feature'],
                'createdAt' => $log['created_at'],
                'createdBy' => $userTranslator->__invoke($log['user']),
                'returnCode' => $log['return_code'],
                '_routes' => [
                    'getLog' => $this->getRouteIfAuthorized(
                        'openAIUsage.getLog',
                        [ 'sessionId' => $sessionId ]
                    ),
                ],
            ];
        }

        $this->response = $this->responseAsJson([
            'data' => array_values($sessions),
            'metadata' => [
                'count' => $count,
                'pagination' => [
                    'limit' => $paginator->getPageSize(),
                    'pages' => $paginator->getPageCount(),
                    'current' => $page,
                ]
            ],
        ]);
    }


    /**
     * @param AILog $log // first in the tree
     * @return void
     */
    public function getLog(AILog $log): void
    {
        $logEntries = $this->aiLogMapper->findByColumn('session_id', $log->getSessionId(), ['order' => 'created_at ASC']);

        $translator = new ModelToArray(
            new User\Translator\Author\ModelToArray()
        );
        $logEntriesArray = $logEntries->map($translator);
        $this->response = $this->responseAsJson($logEntriesArray);
    }
}
