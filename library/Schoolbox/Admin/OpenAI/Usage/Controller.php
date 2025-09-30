<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Usage;

use Alaress\Web\Controller\Base;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Error\Mapper as ErrorMapper;
use Schoolbox\Editor\OpenAI\Log\Mapper as AILogMapper;
use Schoolbox\Editor\OpenAI\Log\Model as AILog;
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
        $logs = $this->aiLogMapper->findAll(['limit' => 100, 'order' => 'created_at DESC']);

        $this->response = $this->responseFromTemplate('@openAIUsage/index.twig', [
            'logs' => $logs,
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

        $cursor = $this->getRequestPost()->get('cursor', null);

        $logs = $this->aiLogMapper->findAll([
            'limit' => 100,
            'offset' => $cursor,
            'order' => 'created_at DESC'
        ]);


        $this->response = $this->responseAsJson($logs);
    }

    /**
     * @param AILog $log // first in the tree
     * @return void
     * @throws \Twig\Error\Error
     */
    public function getLog(AILog $log): void
    {
        // find the rest of the tree and add into a chain
        $logEntries = [];
        foreach ($this->aiLogMapper->findByColumn('session_id', $log->getSessionId(), ['order' => 'created_at ASC']) as $entry) {
            $logEntries[] = $entry;
        }

        $this->response = $this->responseAsJson($logEntries);
    }
}
