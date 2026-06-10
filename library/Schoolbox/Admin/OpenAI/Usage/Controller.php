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
use Schoolbox\Editor\OpenAI\Log;
use Schoolbox\Admin\OpenAI\Config as AIConfig;
use Schoolbox\Mapper\Exception;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
use Schoolbox\OpenAi\Session\Log\Mapper as SessionLogMapper;
use Schoolbox\OpenAi\Session\Log\Model as SessionLogModel;
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
     * @param SessionMapper $sessionMapper
     * @param SessionLogMapper $sessionLogMapper
     * @param ConfigMapper $configMapper
     * @param UserMapper $userMapper
     */
    public function __construct(
        private SessionMapper $sessionMapper,
        private SessionLogMapper $sessionLogMapper,
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
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiUsageLogApiResponse
     */
    public function postSearch(): void
    {
        $keyword = $this->getRequestPost()->get('keyword');
        $userId = $this->getRequestPost()->get('user');
        $feature = $this->getRequestPost()->get('feature');
        $startDate = $this->getRequestPost()->get('startDate');
        $endDate = $this->getRequestPost()->get('endDate');

        $page = $this->getRequestPost()->getInt('page', 1);
        $limit = $this->getRequestPost()->getInt('limit', self::MAX_PAGE_SIZE);

        /* Ensure that in all cases:
         *   - limit is greater than zero
         *   - less than MAX_PAGE_SIZE
         */
        $limit = min(max(0, $limit), self::MAX_PAGE_SIZE);

        //ensure feature is one of the actual features...
        if ($feature) {
            if (!(new AIConfig())->hasFeature($feature)) {
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

        // ensure the user exists (allow deleted)
        $user = null;

        if ($userId) {
            $user = $this->userMapper->findByIdWithFallback($userId);

            if (null === $user->getId()) {
                $message = sprintf(
                    '%s is an unknown user identifier.',
                    $userId
                );

                $problem = (new ApiProblem($message))
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
         * $startAt from the POST request is in RFC 3339 format: 2025-10-01T00:00:00+10:00
         */
        $startAt = null;

        if ($startDate) {
            try {
                /* Go from the start of day (this lets you get things that happened on $startDate)
                 */
                $startAt = (new \Alaress_Date_Format($startDate, \Alaress_Date_Format::FORMAT_RFC3339_W3C))->startOfDay();
            } catch (\InvalidArgumentException $e) {
                $message = sprintf(
                    '%s is an invalid start date or in the incorrect format.',
                    $startDate
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
        $endAt = null;

        if ($endDate) {
            try {
                /* Go to the end of day (this lets you get things that happened on $endDate)
                 */
                $endAt = (new \Alaress_Date_Format($endDate, \Alaress_Date_Format::FORMAT_RFC3339_W3C))->endOfDay();
            } catch (\InvalidArgumentException $e) {
                $message = sprintf(
                    '%s is an invalid end date or in the incorrect format.',
                    $endDate
                );

                $problem = (new ApiProblem($message))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }
        }

        /**
         * Each entry of $logGroups is an `ai_session` row representing a whole session,
         * not a single `ai_session_log` interaction. The per-log fields (e.g. prompt,
         * response, errorMessage) therefore aren't present here; the logFormat/returnCode
         * shown for a session are derived from its first log by
         * SessionMapper::getFilteredSessions().
         */
        $logGroups = $this->sessionMapper->getFilteredSessions(
            $keyword,
            $feature,
            $user,
            $startAt,
            $endAt,
            $limit,
            ($page - 1) * $limit,
        );

        // Find total counts
        $count = $this->sessionMapper->countFilteredSessions(
            $keyword,
            $feature,
            $user,
            $startAt,
            $endAt,
        );

        $paginator = (new Paginator\Paginator())
            ->setTotal($count)
            ->setPageSize($limit);

        /**
         * Compile a stack of unique sessions rather than each individual log instance because you'll
         * have a lot of records -per session id- for those who are resuming chats etc.
         *
         * The frontend treats an AI Session as a distinct representation compared to an AI Session Log entry
         * for future flexibility if we were to change the way sessions are represented, this distinction is
         * handled by the two translators.
         */
        $userTranslator = new User\Translator\Author\ModelToArray();
        $sessionLinksTranslator = new Log\Translator\Session\Links($this->routeGenerator, $this->getAuthorizer(), $this->getUser());
        $sessionTranslator = new Log\Translator\Session\ModelToArray(
            $userTranslator,
            $sessionLinksTranslator
        );

        $sessions = $logGroups->map($sessionTranslator);

        $this->response = $this->responseAsJson([
            'data' => $sessions,
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
     * @param SessionLogModel $log - first log in the session
     * @return void
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiSessionLogApiResponse
     */
    public function getLog(SessionLogModel $log): void
    {
        $logEntries = $this->sessionLogMapper->findByColumn('ai_session_id', $log->getAiSessionId(), [
            'order' => [
                'created_at' => 'ASC'
            ]
        ]);

        $userTranslator = new User\Translator\Author\ModelToArray();
        $strategyFactory = new Log\Translator\Format\StrategyFactory();
        $logTranslator = new Log\Translator\ModelToArray($userTranslator, $strategyFactory);

        $logEntriesArray = $logEntries->map($logTranslator);

        $this->response = $this->responseAsJson($logEntriesArray);
    }
}
