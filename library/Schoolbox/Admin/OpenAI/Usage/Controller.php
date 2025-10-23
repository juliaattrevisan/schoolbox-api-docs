<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Usage;

use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Editor\OpenAI\Log\Model as AILog;
use Schoolbox\Admin\OpenAI\Config as AIConfig;
use Schoolbox\Editor\OpenAI\Log\ModelToArray;
use Schoolbox\UserMapper;

class Controller extends Base
{
    /**
     * Controller constructor.
     *
     * @param AILogMapper $aiLogMapper
     */
    public function __construct(
        private AILogMapper $aiLogMapper,
        private ConfigMapper $configMapper,
    ) {
    }

    public function getIndex(): void
    {
        // get the first 100 logs to display in the table
        $logGroups = $this->aiLogMapper->findAllGrouped();

        // count of all
        $count = $this->aiLogMapper->countAll();

        $features = new AIConfig()->fetchFeatures($this->configMapper);

        // add on image as a feature
        $features['image'] = [
            'title' => 'Image Generation',
        ];

        $this->response = $this->responseFromTemplate('@openAIUsage/index.twig', [
            'features' => $features,
            'count' => $count,
            'logGroups' => $logGroups,
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
        $cursor = $this->getRequestPost()->get('cursor', null);

        // Build predicate using system predicate builder
        $predicate = $this->aiLogMapper->getPredicateBuilder();

        //ensure feature is one of the actual features...
        if ($feature) {
            if (!array_key_exists($feature, array_merge(AIConfig::ALL_MODULES, ['image']))) {
                $problem = (new ApiProblem('Unknown Feature'))
                    ->setStatus(404)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(404);
                return;
            }
            $predicate->equals('feature', $feature);
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
            $predicate->equals('user_id', $user);
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
            $predicate->greaterThanOrEqualTo('created_at', $after);
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
            $predicate->lessThanOrEqualTo('created_at', $before);
        }

        if ($keyword) {
            $predicate->any(
                ($this->aiLogMapper->getPredicateBuilder())
                    ->like('prompt', "%$keyword%")
                    ->like('response', "%$keyword%")
            );
        }

        $offset = $cursor ?? 0;

        // Find logs by predicate using the system builder
        $logGroups = $this->aiLogMapper->findPredicateGrouped($predicate, offset: $offset);
        $count = $this->aiLogMapper->countByPredicates($predicate);

        $translator = new ModelToArray();
        $logs = [];

        //translate each group of session ID's
        foreach ($logGroups as $sessionId => $group) {
            $logs[$sessionId] = $group->map($translator);
        }


        $this->response = $this->responseAsJson([
            'data' => $logs,
            'metadata' => [
                'total' => $count,
                'cursor' => $offset + count($logs),
                ]
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

        $translator = new ModelToArray();
        $logEntriesArray = $logEntries->map($translator);
        $this->response = $this->responseAsJson($logEntriesArray);
    }
}
