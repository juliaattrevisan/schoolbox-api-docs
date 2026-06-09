<?php

/**
 * @author      Justin Clarke <justin.clarke@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\OpenAI\Usage;

use Alaress\Authorizer\Rule;
use Alaress\Module\Module as BaseModule;
use Alaress\Twig\Loader\IChainLoader;
use Alaress\Web\Controller\Base;
use InvalidArgumentException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\OpenAi\Session\Log\Mapper as AILogMapper;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Mapper\Exception\Deleted;
use Schoolbox\UserMapper;

class Module extends BaseModule
{
    /**
     * @inheritdoc
     */
    protected $basename = 'openAIUsage';


    /**
     * Hook method for setting up routes via self::register()
     *
     * @return void
     */
    protected function registerRoutes(): void
    {
        /* Only supers can get here
         */
        $isAdmin = new Rule\All(
            new Rule\IsLoggedIn(),
            new Rule\IsSuperuser(),
        );

        $preload = function (array $params) {
            return $this->preloadLog($params);
        };

        /* Allow users the opportunity to get a set of results using filter criteria
         */
        $this->register('GET', '', 'getIndex', $isAdmin);

        /* Find a set of results by filter criteria
         */
        $this->register('POST', '', 'postSearch', $isAdmin);

        /* Load the details of a particular session_identifier which can consist of multiple rows in the
         * openai_log table due to people resuming their ai session etc.
         */
        $this->register('GET', '/log/{sessionId}', 'getLog', $isAdmin, $preload);
    }

    /**
     * @inheritdoc
     */
    public function buildController(): Base
    {
        return new Controller(
            $this->getMapperFactory()->get(SessionMapper::class),
            $this->getAILogMapper(),
            $this->getMapperFactory()->get(ConfigMapper::class),
            $this->getMapperFactory()->get(UserMapper::class),
        );
    }


    /**
     * Preload the first log entry on the {sessionId} param
     *
     * @param array $params
     * @return array
     * @throws MapperException
     * @throws Deleted
     */
    private function preloadLog(array $params): array
    {
        // if preloaded, then return
        if ($params['log'] ?? null) {
            return ['log' => $params['log']];
        }

        // fetch the first log entry for this sessionId
        if (!isset($params['sessionId'])) {
            throw new InvalidArgumentException('AI session identifier parameter is missing.');
        }

        $log = $this->getAILogMapper()->findOneByColumn('ai_session_id', $params['sessionId']);

        return ['log' => $log];
    }


    /**
     * {@inheritdoc}
     */
    public function initTemplatePaths(IChainLoader $templateLoader): void
    {
        $this->addTemplatePath($templateLoader, __DIR__ . '/templates');
    }


    /**
     * @return AILogMapper
     * @throws \InvalidArgumentException If cannot find mapper class
     */
    protected function getAILogMapper(): AILogMapper
    {
        return $this->getMapperFactory()->get(AILogMapper::class);
    }
}
