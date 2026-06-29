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
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception;
use Schoolbox\OpenAi\Moderation\Event\Status as ModerationEventStatus;
use Schoolbox\OpenAi\Session\Mapper as SessionMapper;
use Schoolbox\OpenAi\Session\Log\Mapper as SessionLogMapper;
use Schoolbox\OpenAi\Session\Model as SessionModel;
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
        $moderationStatusKeys = $this->getRequestPost()->get('moderationStatus');
        $page = $this->getRequestPost()->getInt('page', 1) ?? 1;

        if ($page <= 0) {
            $page = 1;
        }

        $limit = $this->getRequestPost()->getInt('limit', self::MAX_PAGE_SIZE) ?? self::MAX_PAGE_SIZE;

        /* Ensure that in all cases:
         *   - limit is greater than zero
         *   - less than or equal to MAX_PAGE_SIZE
         */
        if ($limit <= 0) {
            $limit = 1;
        }
        if ($limit > self::MAX_PAGE_SIZE) {
            $limit = self::MAX_PAGE_SIZE;
        }

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

        /* Moderation Statuses (recieved as an array of ModerationEventStatus keys, or null for all)
         */
        $moderationStatuses = new GenericCollection(ModerationEventStatus::class);

        if (null !== $moderationStatusKeys) {
            if (!\is_array($moderationStatusKeys)) {
                $problem = (new ApiProblem('Moderation status must be an array.'))
                    ->setStatus(400)
                    ->asArray();
                $this->response = $this->responseAsJson($problem)->withStatus(400);
                return;
            }

            foreach ($moderationStatusKeys as $statusKey) {
                try {
                    $moderationStatuses->add(ModerationEventStatus::from($statusKey));
                } catch (\ValueError $e) {
                    $message = \sprintf('%s is an invalid moderation status.', $statusKey);

                    $problem = (new ApiProblem($message))
                        ->setStatus(400)
                        ->asArray();
                    $this->response = $this->responseAsJson($problem)->withStatus(400);
                    return;
                }
            }
        }

        /**
         * Each entry of $logSessions represents a whole session of logs.
         * The per-log fields (e.g. prompt, response, errorMessage) therefore aren't present here.
         * The logFormat shown for a session is derived from its first log by SessionMapper::getFilteredSessions().
         */
        $logSessions = $this->sessionMapper->getFilteredSessions(
            $keyword,
            $feature,
            $user,
            $startAt,
            $endAt,
            $moderationStatuses,
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
            $moderationStatuses,
        );

        $paginator = (new Paginator\Paginator())
            ->setTotal($count)
            ->setPageSize($limit);

        /**
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

        $sessions = $logSessions->map($sessionTranslator);

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
     * @param SessionModel $session
     * @return void
     * @see /library/Schoolbox/Admin/OpenAI/Usage/templates/payload.ts AiSessionLogApiResponse
     */
    public function getSessionLogs(SessionModel $session): void
    {
        $logEntries = $this->sessionLogMapper->findByColumn('ai_session_id', $session->getId(), [
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
