<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Usage;

use Alaress\Database\Predicate\Builder as PredicateBuilder;
use Alaress\Web\Controller\Base;
use Crell\ApiProblem\ApiProblem;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Editor\OpenAI\Log\Model as AILog;
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
    ) {
    }

    public function getIndex(): void
    {
        // get the first 100 logs to display in the table
        $logGroups = $this->aiLogMapper->findAllGrouped();

        $this->response = $this->responseFromTemplate('@openAIUsage/index.twig', [
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

        //ensure feature is one of the actual features...
// need SA-20932 to check features, ill use the same logic in that
//        if ($feature && !in_array($feature, AILog::getFeatures(), true)) {
//            $problem = (new ApiProblem('Unknown Feature'))
//                ->setStatus(404)
//                ->asArray();
//            $this->response = $this->responseAsJson($problem)->withStatus(404);
//            return;
//        }

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
        /**
         * if ($date = strtotime($value)) {
         * $date = date('Y-m-d H:i:s', $date);
         * $sqlFilter[] = "`time` >= :after";
         * $params['after'] = $date;
         * }
         */
        if ($after) {
            if ($date = strtotime($after)) {
                $after = date('Y-m-d H:i:s', $date);
            } else {
                $problem = (new ApiProblem('Invalid After Date'))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }
        }
        if ($before) {
            if ($date = strtotime($before)) {
                $before = date('Y-m-d H:i:s', $date);
            } else {
                $problem = (new ApiProblem('Invalid Before Date'))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }
        }

        // Build predicate using system predicate builder
        $predicate = $this->aiLogMapper->getPredicateBuilder();
        if ($user) {
            $predicate->equals('user_id', $user);
        }
        if ($feature) {
            $predicate->equals('feature', $feature);
        }
        if ($keyword) {
            $predicate->any(
                ($this->aiLogMapper->getPredicateBuilder())
                    ->like('prompt', "%$keyword%")
                    ->like('response', "%$keyword%")
            );
        }
        if ($after) {
            $predicate->greaterThanOrEqualTo('created_at', $after);
        }
        if ($before) {
            $predicate->lessThanOrEqualTo('created_at', $before);
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
            ]
        );
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
