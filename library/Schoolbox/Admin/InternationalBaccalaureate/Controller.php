<?php

/**
 * International Baccalaureate admin controller
 *
 * @author      Haris Ali <haris.ali@schoolbox.education>
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Admin\InternationalBaccalaureate;

use Alaress\Database\Predicate;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Schoolbox\Admin\Navigation\Subnav;
use Schoolbox\Admin\Settings\FieldStrategyFactory;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IB\Group\Subject\Language\Mapper as GroupSubjectLanguageMapper;
use Schoolbox\IB\Group\Subject\Language\Model as GroupSubjectLanguageModel;
use Schoolbox\IB\Group\Subject\Mapper as IBGroupSubjectMapper;
use Schoolbox\IB\Group\Subject\Model as IBGroupSubjectModel;
use Schoolbox\IB\Group\Mapper as IBGroupMapper;
use Schoolbox\IB\Group\Model as IBGroupModel;
use Schoolbox\IB\Registrations\Sessions\Mapper as RegistrationSessionMapper;
use Schoolbox\IB\Registrations\Sessions\Model as RegistrationSessionModel;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper\Exception\NotFound as MapperNotFound;

class Controller extends ConfigController
{
    /* General Configuration ID
     */
    private const int GENERAL_CATEGORY_ID = 0;

    public function __construct(
        ConfigMapper $configMapper,
        private IBGroupMapper $groupMapper,
        private IBGroupSubjectMapper $groupSubjectMapper,
        private GroupSubjectLanguageMapper $groupSubjectLanguageMapper,
        private LanguageMapper $languageMapper,
        private RegistrationSessionMapper $registrationSessionMapper,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    /**
     * DP Subject Configuration page
     *
     * @throws RouteNotFound
     * @api GET /adminv2/international-baccalaureate/configuration
     */
    public function getConfiguration(): void
    {
        $pageCurrent = '/adminv2/international-baccalaureate/configuration';

        // Add General Configuration tab manually
        $categories = [
            [
                'id' => self::GENERAL_CATEGORY_ID,
                'name' => 'general',
                'title' => 'General Configuration',
            ],
        ];

        // Fetch IB groups from database
        $groupModels = $this->groupMapper->findAll();

        /** @var IBGroupModel $groupModel */
        foreach ($groupModels as $groupModel) {
            $categories[] = [
                'id' => $groupModel->getId(),
                'name' => strtolower(str_replace(' ', '-', $groupModel->getName())),
                'title' => $groupModel->getName(),
            ];
        }

        $this->response = $this->responseFromTemplate(
            '@internationalBaccalaureate/configuration.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
                'categories' => $categories,
                '_routes' => [
                    'searchLanguages' => $this->getRouteIfAuthorized('internationalBaccalaureate.searchLanguages'),
                ],
            ]
        );
    }

    /**
     * Search the IB language list using a cursor.
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/languages
     */
    public function searchLanguages(): void
    {
        $search = trim((string) $this->requestQuery->get('query', ''));
        $limit = $this->requestQuery->getInt('limit', Cursor::LIMIT_DEFAULT);
        $encodedCursor = $this->requestQuery->get('cursor');

        if (null === $limit) {
            $problem = (new ApiProblem('Limit must be a positive integer'))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        try {
            $cursor = new Cursor(
                $this->languageMapper,
                $search,
                $limit
            );
            $results = $cursor->retrieveResults($encodedCursor);
        } catch (Exception | \ValueError $e) {
            $problem = (new ApiProblem($e->getMessage()))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        $data = $results->map(
            fn(LanguageModel $language) => [
                'id' => $language->getId(),
                'code' => $language->getCode(),
                'name' => $language->getName(),
            ]
        );

        $this->response = $this->responseAsJson([
            'data' => $data,
            'metadata' => [
                'count' => $cursor->retrieveResultCount(),
                'cursor' => [
                    'current' => $encodedCursor ?? $cursor->current($results),
                    'next' => ($results->count() < $limit) ? null : $cursor->next($results),
                ],
            ],
        ]);
    }

    /**
     * Get configuration data for a specific category
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/{categoryId}
     */
    public function getConfigurationData(int $categoryId): void
    {
        $configurations = [];

        if ($categoryId === self::GENERAL_CATEGORY_ID) { // General tab - load from preloaded config
            $configLoader = $this->getConfigLoader();

            $configurations = [
                [
                    'categoryId' => self::GENERAL_CATEGORY_ID,
                    'key' => ConfigMapper::NAME_IB_SCHOOL_NAME,
                    'value' => $configLoader->get(ConfigMapper::NAME_IB_SCHOOL_NAME) ?? '',
                    'description' => 'Enter the school\'s name as registered with the IB.',
                    'type' => 'text',
                    'label' => 'School Name',
                ],
                [
                    'categoryId' => self::GENERAL_CATEGORY_ID,
                    'key' => ConfigMapper::NAME_IB_SCHOOL_CODE,
                    'value' => $configLoader->get(ConfigMapper::NAME_IB_SCHOOL_CODE) ?? '',
                    'description' => 'Enter the school\'s code as provided by the IB.',
                    'type' => 'text',
                    'label' => 'School Code',
                ],
            ];
        }

        $this->response = $this->responseAsJson($configurations);
    }

    /**
     * Get subjects for a specific category
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/subjects/{groupId}
     */
    public function getCategorySubjects(int $groupId): void
    {
        try {
            $groupSubjects = $this->groupSubjectMapper->findByColumn(
                'group_id',
                $groupId,
                [
                    'order' => ['s.name' => 'ASC', 'level' => 'ASC'],
                ]
            );

            $subjects = [];
            /** @var IBGroupSubjectModel $groupSubject */
            foreach ($groupSubjects as $groupSubject) {
                $subjects[] = [
                    'id' => $groupSubject->getId(),
                    'subject' => $groupSubject->getSubject()->getName(),
                    'level' => $groupSubject->getLevel() ?: '',
                    'languages' => $groupSubject->getLanguages()
                        ->map(fn(GroupSubjectLanguageModel $language) => [
                            'languageId' => $language->getLanguage()?->getId(),
                            'classCode' => $language->getClassCode() ?? '',
                        ])
                        ->reindex()
                        ->getAll(),
                ];
            }

            $this->response = $this->responseAsJson($subjects);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson(['error' => $e->getMessage()])->withStatus(500);
        }
    }

    /**
     * Save configuration data
     *
     * @api POST /adminv2/international-baccalaureate/configuration/api/save
     */
    public function saveConfiguration(): void
    {
        $data = (array) $this->requestPost;

        if (isset($data['subjectLanguages'])) {
            $this->saveSubjectLanguageConfiguration($data);
            return;
        }

        if (!isset($data['key']) || !isset($data['value'])) {
            $this->response = $this->responseAsJson(['success' => false, 'message' => 'Invalid request data'])->withStatus(400);
            return;
        }

        $this->updateConfig(
            [$data['key'] => $data['value']],
            $this->buildIbConfigPredicate()
        );

        $this->response = $this->responseAsJson(['success' => true, 'message' => 'Configuration saved successfully']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function saveSubjectLanguageConfiguration(array $data): void
    {
        $categoryId = isset($data['categoryId']) ? (int) $data['categoryId'] : 0;
        $subjectLanguages = is_array($data['subjectLanguages'] ?? null) ? $data['subjectLanguages'] : null;

        if ($categoryId <= 0 || $subjectLanguages === null) {
            $this->response = $this->responseAsJson(['success' => false, 'message' => 'Invalid subject language request data'])->withStatus(400);
            return;
        }

        try {
            $groupSubjects = $this->groupSubjectMapper->findByColumn('group_id', $categoryId);
            $groupSubjectIds = array_map(
                static fn($groupSubjectId) => (int) $groupSubjectId,
                $groupSubjects->map(fn($groupSubject) => $groupSubject->getId())->getAll()
            );

            if (empty($groupSubjectIds)) {
                $this->response = $this->responseAsJson(['success' => false, 'message' => 'No group subjects found for category'])->withStatus(400);
                return;
            }

            $allowedGroupSubjectIds = array_fill_keys($groupSubjectIds, true);
            $modelsToSave = [];

            foreach ($subjectLanguages as $subjectLanguage) {
                if (!is_array($subjectLanguage)) {
                    continue;
                }

                $groupSubjectId = (int) ($subjectLanguage['groupSubjectId'] ?? 0);
                $languageId = (int) ($subjectLanguage['languageId'] ?? 0);

                if (
                    $groupSubjectId <= 0 ||
                    $languageId <= 0 ||
                    !isset($allowedGroupSubjectIds[$groupSubjectId])
                ) {
                    continue;
                }

                $modelsToSave[] = $this->groupSubjectLanguageMapper
                    ->createModel()
                    ->setGroupSubject($this->groupSubjectMapper->createModel()->setId($groupSubjectId))
                    ->setLanguage($this->languageMapper->createModel()->setId($languageId))
                    ->setClassCode(trim((string) ($subjectLanguage['classCode'] ?? '')));
            }

            $this->groupSubjectLanguageMapper->deleteByColumn('group_subject_id', $groupSubjectIds);

            if (!empty($modelsToSave)) {
                $this->groupSubjectLanguageMapper->save($modelsToSave);
            }

            $this->response = $this->responseAsJson(['success' => true, 'message' => 'Configuration saved successfully']);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson(['success' => false, 'message' => $e->getMessage()])->withStatus(500);
        }
    }

    private function buildIbConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_IB);
    }

    /**
     * Registration Reports page
     *
     * @throws RouteNotFound|\Twig\Error\Error
     * @api GET /adminv2/international-baccalaureate/registrations
     */
    public function getRegistrations(): void
    {
        $pageCurrent = '/adminv2/international-baccalaureate/registrations';
        $this->response = $this->responseDependentOnRequest(
            '@internationalBaccalaureate/registrations.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
                '_routes' => [
                    'sessions' => [
                        'list' => $this->getRouteIfAuthorized('internationalBaccalaureate.getRegistrationSessions'),
                        'create' => $this->getRouteIfAuthorized('internationalBaccalaureate.createRegistrationSession')
                    ]
                ]
            ]
        );
    }

    /**
     * Get the list of all the registration sessions
     *
     * @return void
     * @api GET /adminv2/international-baccalaureate/registrations/sessions
     *
     */
    public function getRegistrationSessions(): void
    {
        try {
            $sessions = $this->registrationSessionMapper->findAll();
            $sessionsBySystemPurpose = [];

            /** @var RegistrationSessionModel $session */
            foreach ($sessions as $session) {
                $systemPurpose = $session->getSystemPurpose();

                if (!$systemPurpose) {
                    continue;
                }

                $sessionsBySystemPurpose[$systemPurpose->getId()][] = $session->jsonSerialize();
            }

            $this->response = $this->responseAsJson($sessionsBySystemPurpose);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson(['error' => $e->getMessage()])->withStatus(500);
        }
    }

    /**
     * Create registration sessions
     *
     * @return void
     * @api POST /adminv2/international-baccalaureate/registrations/sessions
     *
     */
    public function createRegistrationSession(): void
    {
        $post = $this->requestPost;

        $systemPurposeId = $post->getInt('systemPurposeId');
        $academicYear = trim((string)$post->get('academicYear'));
        $examSession = trim((string)$post->get('examSession'));

        if (
            null === $systemPurposeId ||
            '' === $academicYear ||
            !in_array(
                $examSession,
                RegistrationSessionMapper::EXAM_SESSIONS,
                true
            )
        ) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Invalid registration session request data',
            ])->withStatus(400);
            return;
        }

        /* Validate system purpose ID and load the system purpose model, as it's needed for creating the registration session.
         * If the ID is invalid, return an error.
         */
        try {
            $systemPurpose = $this->registrationSessionMapper
                ->getJoinMapper('systemPurpose')
                ->findOneByColumn('id', $systemPurposeId);
        } catch (MapperNotFound) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Invalid registration session request data: systemPurposeId is incorrect',
            ])->withStatus(400);
            return;
        } catch (Exception $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => $e->getMessage(),
            ])->withStatus(500);
            return;
        }

        try {
            $session = $this->registrationSessionMapper
                ->createModel()
                ->setSystemPurpose($systemPurpose)
                ->setAcademicYear($academicYear)
                ->setExamSession($examSession)
                ->setCreatedBy($this->getUser())
                ->setUpdatedBy($this->getUser());

            $this->registrationSessionMapper->save($session);

            /** @var RegistrationSessionModel|null $createdSession */
            $createdSession = $this->registrationSessionMapper->findById($session->getId());

            if (!$createdSession || !$createdSession->getSystemPurpose()) {
                $this->response = $this->responseAsJson([
                    'success' => false,
                    'message' => 'Registration session was created but could not be loaded',
                ])->withStatus(500);
                return;
            }

            $this->response = $this
                ->responseAsJson($createdSession->jsonSerialize())
                ->withStatus(201);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }
    }
}
