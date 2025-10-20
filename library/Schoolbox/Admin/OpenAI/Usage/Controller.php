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
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Editor\OpenAI\Log\Model as AILog;
use Schoolbox\Admin\OpenAI\Config as AIConfig;
use Schoolbox\Editor\OpenAI\Log\ModelToArray;
use Schoolbox\UserMapper;
use Schoolbox\User;

class Controller extends Base
{
    private const MAX_PAGE_SIZE = 100;

    /**
     * Controller constructor.
     *
     * @param AILogMapper $aiLogMapper
     * @param ConfigMapper $configMapper
     */
    public function __construct(
        private AILogMapper $aiLogMapper,
        private ConfigMapper $configMapper,
    ) {
    }

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
     * returns the next chunk of logs based on the cursor provided
     * @return void
     */
    public function postSearch(): void
    {
        $keyword = $this->getRequestPost()->get('keyword', null);
        $user = $this->getRequestPost()->get('user', null);
        $feature = $this->getRequestPost()->get('feature', null);
        $after = $this->getRequestPost()->get('after', null);
        $before = $this->getRequestPost()->get('before', null);

        $page = $this->getRequestPost()->getInt('page', 1);
        $limit = $this->getRequestPost()->getInt('limit', self::MAX_PAGE_SIZE);

        //ensure feature is one of the actual features...
        if ($feature) {
            if (!array_key_exists($feature, array_merge(AIConfig::ALL_MODULES, ['image']))) {
                $problem = (new ApiProblem('Unknown Feature'))
                    ->setStatus(404)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(404);
                return;
            }
        }

        // ensue the user exists (allow deleted)
        if ($user) {
            $userMapper = UserMapper::getInstance();
            $userObj = $userMapper->findByIdWithFallback($user);
            if (null === $userObj->getId()) {
                $problem = (new ApiProblem('Unknown User'))
                    ->setStatus(404)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(404);
                return;
            }
        }

        // dates (before/after)
        if ($after) {
            try {
                $after  = new \Alaress_Date_Format($after, \Alaress_Date_Format::FORMAT_DATE_ALT);
            } catch (\InvalidArgumentException $e) {
                $problem = (new ApiProblem('Invalid After Date'))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }
        }
        if ($before) {
            try {
                $before  = new \Alaress_Date_Format($before, \Alaress_Date_Format::FORMAT_DATE_ALT);
            } catch (\InvalidArgumentException $e) {
                $problem = (new ApiProblem('Invalid Before Date'))
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
            $after,
            $before,
            $limit,
            ($page - 1) * $limit,
        );

        // Find total counts
        $count = $this->aiLogMapper->countGroupedLogs(
            $keyword,
            $feature,
            $userObj ?? null,
            $after,
            $before,
        )[0];

        $pageSize = min($limit, self::MAX_PAGE_SIZE);

        $paginator = (new Paginator\Paginator())
            ->setTotal($count)
            ->setPageSize($pageSize);

        $userTranslator = new User\Translator\Author\ModelToArray();

        $sessions = [];

        //translate each group of session ID's
        foreach ($logGroups as $sessionId => $log) {
            // $sessionId could be some fake "no-session-{id}" that we created in the mapper
            // so figure out the real session ID which we need to generate the route for
            $actualSessionId = $sessionId ?? null;

            $sessions[$sessionId] = [
                'id' => $sessionId,
                'feature' => $log['feature'],
                'createdAt' => $log['created_at'],
                'createdBy' => $userTranslator->__invoke($log['user']),
                '_routes' => [
                    'getLog' => $actualSessionId !== null ? $this->getRouteIfAuthorized(
                        'openAIUsage.getLog',
                        [ 'sessionId' => $actualSessionId ]
                    ) : null,
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
     * @throws \Twig\Error\Error
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
