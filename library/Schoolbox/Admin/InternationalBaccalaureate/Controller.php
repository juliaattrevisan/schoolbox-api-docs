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

use Alaress_Date_Format;
use Alaress\Database\Predicate;
use Alaress\Module\Cache\Rebuilder;
use Alaress\Web\Controller\ConfigController;
use Alaress\Web\Request\Values;
use Aura\Router\Exception\RouteNotFound;
use Crell\ApiProblem\ApiProblem;
use Exception;
use External_ClassInterface;
use Psr\SimpleCache\CacheInterface;
use Report_Exporter;
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
use Schoolbox\IB\Registrations\Sessions\Student\Helper as RegistrationSessionStudentHelper;
use Schoolbox\IB\Registrations\Sessions\Student\Export\Csv as RegistrationSessionStudentCsvExport;
use Schoolbox\IB\Registrations\Sessions\Student\Mapper as RegistrationSessionStudentMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Model as RegistrationSessionStudentModel;
use Schoolbox\IB\Registrations\Sessions\Student\Subject\Language\Mapper as RegistrationSessionStudentSubjectLanguageMapper;
use Schoolbox\IB\Registrations\Sessions\Student\Translator\ModelToArray as RegistrationSessionStudentModelToArray;
use Schoolbox\Language\Mapper as LanguageMapper;
use Schoolbox\Language\Model as LanguageModel;
use Schoolbox\Mapper as BaseMapper;
use Schoolbox\Mapper\Exception\NotFound as MapperNotFound;
use Schoolbox\Resource\Folder\Code\Mapper as FolderCodeMapper;
use Schoolbox\Resource\Folder\Code\Model as FolderCodeModel;

class Controller extends ConfigController
{
    /* General Configuration ID
     */
    private const int GENERAL_CATEGORY_ID = 0;
    /* Maximum academic year length
     */
    private const int REGISTRATION_SESSION_ACADEMIC_YEAR_MAX_LENGTH = 4;
    private const string CONFIRMATION_SECTION_CANDIDATE = 'candidate';
    private const string CONFIRMATION_SECTION_SUBJECT = 'subject';
    private const array CONFIRMATION_SECTIONS = [
        self::CONFIRMATION_SECTION_CANDIDATE,
        self::CONFIRMATION_SECTION_SUBJECT,
    ];

    public function __construct(
        ConfigMapper $configMapper,
        private IBGroupMapper $groupMapper,
        private IBGroupSubjectMapper $groupSubjectMapper,
        private GroupSubjectLanguageMapper $groupSubjectLanguageMapper,
        private LanguageMapper $languageMapper,
        private FolderCodeMapper $classCodeMapper,
        private RegistrationSessionMapper $registrationSessionMapper,
        private External_ClassInterface $externalClass,
        private RegistrationSessionStudentMapper $registrationSessionStudentMapper,
        private RegistrationSessionStudentSubjectLanguageMapper $registrationSessionStudentSubjectLanguageMapper,
        private RegistrationSessionStudentModelToArray $registrationSessionStudentModelToArray,
        private RegistrationSessionStudentHelper $registrationSessionStudentHelper,
        private \External_UserInterface $externalUser,
        FieldStrategyFactory $fieldStrategyFactory,
        Rebuilder $rebuilder,
        CacheInterface $oidcCache
    ) {
        parent::__construct($configMapper, $fieldStrategyFactory, $rebuilder, $oidcCache);
    }

    /**
     * DP Subject Configuration page
     *
     * @throws RouteNotFound|\Twig\Error\Error
     * @api GET /adminv2/international-baccalaureate/configuration
     */
    public function getConfiguration(): void
    {
        $pageCurrent = '/adminv2/international-baccalaureate/configuration';
        $defaultLanguage = $this->getDefaultLanguage();

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
                'defaultLanguage' => $defaultLanguage ? [
                    'id' => $defaultLanguage->getId(),
                    'code' => $defaultLanguage->getCode(),
                    'name' => $defaultLanguage->getName(),
                ] : null,
                '_routes' => [
                    'searchLanguages' => $this->getRouteIfAuthorized('internationalBaccalaureate.searchLanguages'),
                    'searchClassCodes' => $this->getRouteIfAuthorized('internationalBaccalaureate.searchClassCodes'),
                    'studentCount' => $this->getRouteIfAuthorized('internationalBaccalaureate.getStudentCount'),
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
            $this->sendJsonError('Limit must be a positive integer', 400);
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
            $this->sendJsonError($e->getMessage(), 400);
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
     * Search class codes from folder_code.
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/class-codes
     */
    public function searchClassCodes(): void
    {
        $search = trim((string) $this->requestQuery->get('query', ''));
        $limit = $this->requestQuery->getInt('limit', ClassCodeCursor::LIMIT_DEFAULT);
        $encodedCursor = $this->requestQuery->get('cursor');

        try {
            $cursor = new ClassCodeCursor(
                $this->classCodeMapper,
                $search,
                $limit
            );
            $results = $cursor->retrieveResults($encodedCursor);
        } catch (Exception | \ValueError $e) {
            $this->sendJsonError($e->getMessage(), 400);
            return;
        }

        $data = $results
            ->filter(fn(FolderCodeModel $classCode) => !empty($classCode->getCode()))
            ->map(
                fn(FolderCodeModel $classCode) => [
                    'id' => $classCode->getCode(),
                    'code' => $classCode->getCode(),
                    'name' => $classCode->getCode(),
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
     * Get student counts for IB group subjects based on their stored class codes.
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/student-count
     */
    public function getStudentCount(): void
    {
        $groupSubjectIds = array_map(
            'intval',
            (array) $this->requestQuery->get('groupSubjectId', [])
        );
        $groupSubjectIds = array_values(array_filter($groupSubjectIds, fn(int $id) => $id > 0));

        if (empty($groupSubjectIds)) {
            $this->response = $this->responseAsJson([]);
            return;
        }

        // Single ORM query for all language rows across all requested subjects
        $allLanguageRows = $this->groupSubjectLanguageMapper->findByColumn(
            'group_subject_id',
            $groupSubjectIds
        );

        $subjectLanguageData = array_fill_keys($groupSubjectIds, []);

        foreach ($allLanguageRows as $languageRow) {
            $gsId = $languageRow->getGroupSubject()?->getId();
            $languageId = $languageRow->getLanguage()?->getId();

            if ($gsId === null || !array_key_exists($gsId, $subjectLanguageData) || $languageId === null) {
                continue;
            }

            $codes = $this->getClassCodeValues($languageRow);
            $subjectLanguageData[$gsId][$languageId]['languageId'] = $languageId;
            $subjectLanguageData[$gsId][$languageId]['codes'] = array_values(array_unique([
                ...($subjectLanguageData[$gsId][$languageId]['codes'] ?? []),
                ...$codes,
            ]));
        }

        // Build response
        $results = [];
        foreach ($groupSubjectIds as $gsId) {
            $byLanguage = [];
            $subjectClassCodes = [];
            foreach ($subjectLanguageData[$gsId] as $language) {
                $subjectClassCodes = [
                    ...$subjectClassCodes,
                    ...$language['codes'],
                ];
                $byLanguage[] = [
                    'languageId' => $language['languageId'],
                    'count' => $this->countStudentsInClassCodes($language['codes']),
                ];
            }
            $results[] = [
                'groupSubjectId' => $gsId,
                'total' => $this->countStudentsInClassCodes($subjectClassCodes),
                'byLanguage' => $byLanguage,
            ];
        }

        $this->response = $this->responseAsJson($results);
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
                $languages = [];

                foreach ($groupSubject->getLanguages() as $language) {
                    $languageId = $language->getLanguage()?->getId();

                    if ($languageId === null) {
                        continue;
                    }

                    $systemPurposeId = $language->getSystemPurposeId();
                    $key = $languageId . '_' . ($systemPurposeId ?? 'null');
                    $languages[$key]['languageId'] = $languageId;
                    /** @phpstan-ignore-next-line - language is nullable despite what PHPStan says */
                    $languages[$key]['languageName'] = $language->getLanguage()?->getName();
                    $languages[$key]['systemPurposeId'] = $systemPurposeId;
                    $languages[$key]['classCodes'] = [
                        ...($languages[$key]['classCodes'] ?? []),
                        ...$this->getClassCodeValues($language),
                    ];
                }

                $subjects[] = [
                    'id' => $groupSubject->getId(),
                    'subject' => $groupSubject->getSubject()->getName(),
                    'level' => $groupSubject->getLevel() ?: '',
                    'languages' => array_values(array_map(
                        static fn(array $language) => [
                            'languageId' => $language['languageId'],
                            'languageName' => $language['languageName'],
                            'classCode' => implode(', ', array_unique($language['classCodes'])),
                            'systemPurposeId' => $language['systemPurposeId'],
                        ],
                        $languages
                    )),
                ];
            }

            $this->response = $this->responseAsJson($subjects);
        } catch (Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    /**
     * Get default language
     * @return LanguageModel|null
     */
    private function getDefaultLanguage(): ?LanguageModel
    {
        $englishLanguage = $this->languageMapper->findByColumn('code', 'ENG')->first();

        if ($englishLanguage instanceof LanguageModel) {
            return $englishLanguage;
        }

        $firstLanguage = $this->languageMapper->findAll(['limit' => 1])->first();

        return $firstLanguage instanceof LanguageModel ? $firstLanguage : null;
    }

    /**
     * Save configuration data
     *
     * @api POST /adminv2/international-baccalaureate/configuration/api/save
     */
    public function saveConfiguration(): void
    {
        $data = $this->requestPost->getArrayCopy();

        if (isset($data['subjectLanguages'])) {
            $this->saveSubjectLanguageConfiguration($data);
            return;
        }

        if (!isset($data['key']) || !isset($data['value'])) {
            $this->sendJsonError('Invalid request data', 400);
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
            $this->sendJsonError('Invalid subject language request data', 400);
            return;
        }

        try {
            $groupSubjects = $this->groupSubjectMapper->findByColumn('group_id', $categoryId);
            $groupSubjectIds = array_map(
                static fn($groupSubjectId) => (int) $groupSubjectId,
                $groupSubjects->map(fn($groupSubject) => $groupSubject->getId())->getAll()
            );

            if (empty($groupSubjectIds)) {
                $this->sendJsonError('No group subjects found for category', 400);
                return;
            }

            $allowedGroupSubjectIds = array_fill_keys($groupSubjectIds, true);

            $requestedLanguageIds = array_filter(array_column($subjectLanguages, 'languageId'));
            $languagesById = $this->languageMapper->findByColumn('id', $requestedLanguageIds)->indexBy('id')->getAll();

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
                    !isset($allowedGroupSubjectIds[$groupSubjectId]) ||
                    !isset($languagesById[$languageId])
                ) {
                    continue;
                }

                $systemPurposeId = isset($subjectLanguage['systemPurposeId'])
                    ? ((int) $subjectLanguage['systemPurposeId'] ?: null)
                    : null;

                $modelsToSave[] = $this->groupSubjectLanguageMapper
                    ->createModel()
                    ->setGroupSubject($this->groupSubjectMapper->createModel()->setId($groupSubjectId))
                    ->setLanguage($languagesById[$languageId])
                    ->setClassCode(trim((string) ($subjectLanguage['classCode'] ?? '')))
                    ->setSystemPurposeId($systemPurposeId);
            }

            $this->groupSubjectLanguageMapper->deleteByColumn('group_subject_id', $groupSubjectIds);

            if (!empty($modelsToSave)) {
                $this->groupSubjectLanguageMapper->save($modelsToSave);
            }

            $this->response = $this->responseAsJson(['success' => true, 'message' => 'Configuration saved successfully']);
        } catch (Exception $e) {
            $this->sendJsonError($e->getMessage(), 500);
        }
    }

    private function buildIbConfigPredicate(): Predicate\Builder
    {
        return $this->getConfigMapper()
                    ->getPredicateBuilder()
                    ->equals(ConfigMapper::COL_MODULE, ConfigMapper::MODULE_IB);
    }

    /**
     * @return string[]
     */
    private function getClassCodeValues(GroupSubjectLanguageModel $language): array
    {
        return array_values(array_filter(
            $language->getClassCode()->map(
                static fn(FolderCodeModel $classCode) => trim((string) $classCode->getCode())
            )->getAll(),
            static fn(string $classCode): bool => $classCode !== ''
        ));
    }

    /**
     * @param string[] $classCodes
     */
    private function countStudentsInClassCodes(array $classCodes): int
    {
        $classCodes = array_values(array_unique(array_filter(
            array_map('trim', $classCodes),
            static fn(string $classCode): bool => $classCode !== ''
        )));

        if ($classCodes === []) {
            return 0;
        }

        $studentIds = [];
        foreach ($this->externalClass->getStudents(implode(',', $classCodes)) as $student) {
            if (!isset($student['synergy_id'])) {
                continue;
            }

            $studentIds[(string) $student['synergy_id']] = true;
        }

        return count($studentIds);
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
        $reportRoute = $this->getRouteIfAuthorized('internationalBaccalaureate.getReport', ['sessionId' => 0]);
        $this->response = $this->responseDependentOnRequest(
            '@internationalBaccalaureate/registrations.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
                '_routes' => [
                    'sessions' => [
                        'list' => $this->getRouteIfAuthorized('internationalBaccalaureate.getRegistrationSessions'),
                        'create' => $this->getRouteIfAuthorized('internationalBaccalaureate.createRegistrationSession'),
                        'delete' => $this->getRouteIfAuthorized('internationalBaccalaureate.deleteRegistrationSession', [
                            'sessionId' => 0,
                        ]),
                        'reportBase' => $reportRoute !== null ? \dirname($reportRoute) : null,
                    ],
                ],
            ]
        );
    }

    /**
     * Diploma Programme Year 2 Registration Report page
     *
     * @api GET /adminv2/international-baccalaureate/registrations/sessions/{sessionId}
     */
    public function getReport(int $sessionId): void
    {
        try {
            $session = $this->registrationSessionMapper->findById($sessionId);
        } catch (MapperNotFound) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Registration session not found.',
            ])->withStatus(404);
            return;
        }

        $pageCurrent = '/adminv2/international-baccalaureate/registrations';
        $this->response = $this->responseDependentOnRequest(
            '@internationalBaccalaureate/registrations/report.twig',
            [
                'pageCurrent' => $pageCurrent,
                'allBreadcrumbs' => (new Subnav($this->getConfigLoader(), $this->getUser()))->generateBreadcrumbs($pageCurrent),
                '_routes' => [
                    'candidatesEndpoint' => $this->getRouteIfAuthorized('internationalBaccalaureate.getRegistrationSessionStudents', ['sessionId' => $sessionId]),
                    'candidateExportEndpoint' => $this->getRouteIfAuthorized('internationalBaccalaureate.exportRegistrationSessionStudentsCsv', ['sessionId' => $sessionId]),
                    'searchLanguages' => $this->getRouteIfAuthorized('internationalBaccalaureate.searchLanguages'),
                    'completePreparation' => $this->getRouteIfAuthorized('internationalBaccalaureate.sessionCompletePreparation', ['sessionId' => $sessionId]),
                    'renotifyUnconfirmed' => $this->getRouteIfAuthorized('internationalBaccalaureate.renotifyUnconfirmed', ['sessionId' => $sessionId]),
                ],
                'sessionTitle' => $session->jsonSerialize()['title'],
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

                $deleteRoute = $this->getRouteIfAuthorized('internationalBaccalaureate.deleteRegistrationSession', [
                    'sessionId' => $session->getId(),
                ]) ?: null;
                $hasRegistered = $this->registrationSessionStudentMapper->hasRegisteredStudents($session->getId());
                $deleteEndpoint = ($deleteRoute !== null && !$hasRegistered) ? $deleteRoute : null;

                $sessionsBySystemPurpose[$systemPurpose->getId()][] = array_merge(
                    $session->jsonSerialize(),
                    ['deleteEndpoint' => $deleteEndpoint]
                );
            }

            $this->response = $this->responseAsJson($sessionsBySystemPurpose);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson(
                [
                    'success' => false,
                    'message' => $e->getMessage(),
                ]
            )->withStatus(500);
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
        $academicYear = trim((string) $post->get('academicYear'));
        $examSession = trim((string) $post->get('examSession'));

        if (
            null === $systemPurposeId ||
            $systemPurposeId <= 0 ||
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

        /* Validation for academic year length - to prevent excessively long values being entered which could cause issues with display and storage.
         */
        if (mb_strlen($academicYear) > self::REGISTRATION_SESSION_ACADEMIC_YEAR_MAX_LENGTH) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => sprintf(
                    'Academic year must be %d characters or fewer, for example 2026.',
                    self::REGISTRATION_SESSION_ACADEMIC_YEAR_MAX_LENGTH
                ),
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

            $deleteRoute = $this->getRouteIfAuthorized('internationalBaccalaureate.deleteRegistrationSession', [
                'sessionId' => $createdSession->getId(),
            ]) ?: null;

            $this->response = $this
                ->responseAsJson(array_merge(
                    $createdSession->jsonSerialize(),
                    ['deleteEndpoint' => $deleteRoute]
                ))
                ->withStatus(201);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }
    }

    /**
     * Delete a registration session
     *
     * @api DELETE /adminv2/international-baccalaureate/registrations/sessions/{sessionId}
     */
    public function deleteRegistrationSession(RegistrationSessionModel $registrationSession): void
    {
        try {
            if ($this->registrationSessionStudentMapper->hasRegisteredStudents($registrationSession->getId())) {
                $this->response = $this->responseAsJson([
                    'success' => false,
                    'message' => 'Cannot delete a registration session that has registered students.',
                ])->withStatus(409);
                return;
            }

            $registrationSession->setDeletedBy($this->getUser());

            $this->registrationSessionMapper->save($registrationSession);
            $this->registrationSessionMapper->delete($registrationSession);

            $this->response = $this->responseEmpty()->withStatus(204);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => $e->getMessage(),
            ])->withStatus(500);
        }
    }

    /**
     * Export students for a registration session as a CSV.
     *
     * @api GET /adminv2/international-baccalaureate/registrations/sessions/{sessionId}/candidates/export/csv
     */
    public function exportRegistrationSessionStudentsCsv(RegistrationSessionModel $registrationSession): void
    {
        try {
            $sessionId = (int) $registrationSession->getId();
            $csvExport = new RegistrationSessionStudentCsvExport();
            $students = $this->registrationSessionStudentMapper->findStudentsForSession($sessionId);
            $rows = $students
                ->map(
                    fn(RegistrationSessionStudentModel $student): array => $csvExport->buildRow(
                        $student,
                        $this->findExternalStudent($student)
                    )
                )
                ->reindex()
                ->getAll();

            $exporter = new Report_Exporter(sprintf('ib-registration-candidate-details-%d', $sessionId));
            $exporter->setHeadings($csvExport->headings());
            $exporter->setData($rows);
            $exporter->exportCSV();
        } catch (Exception $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Something has gone wrong. Please try again later.',
            ])->withStatus(500);
        }
    }

    /**
     * Get students for a registration session.
     *
     * @api GET /adminv2/international-baccalaureate/registrations/sessions/{sessionId}/candidates
     */
    public function getRegistrationSessionStudents(int $sessionId): void
    {
        try {
            $schoolName = (string) ($this->getConfigLoader()->get(ConfigMapper::NAME_IB_SCHOOL_NAME) ?? '');
            $students = $this->registrationSessionStudentMapper->findStudentsForSession($sessionId);
            $studentPayloads = $students
                ->map(
                    fn(RegistrationSessionStudentModel $student): array => ($this->registrationSessionStudentModelToArray)(
                        $student,
                        $this->findExternalStudent($student),
                        $schoolName
                    )
                )
                ->reindex()
                ->getAll();

            $this->response = $this->responseAsJson([
                'success' => true,
                'students' => $this->registrationSessionStudentHelper->groupByStatus($studentPayloads),
                'statuses' => $this->registrationSessionStudentHelper->getStatuses(),
            ]);
        } catch (MapperNotFound) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Registration session not found.',
            ])->withStatus(404);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Something has gone wrong. Please try again later.',
            ])->withStatus(500);
        }
    }

    /**
     * Manually override the language selected for a student's enrolled IB subjects.
     *
     * @api PUT /adminv2/international-baccalaureate/registrations/sessions/{sessionId}/candidates/{studentId}/languages
     */
    public function updateRegistrationSessionStudentSubjectLanguages(
        int $sessionId,
        int $studentId
    ): void {
        $subjects = new Values($this->request->getParsedBody() ?? [])->get('subjects');

        if (!is_array($subjects)) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Invalid subject language request data.',
            ])->withStatus(400);
            return;
        }

        $subjectLanguagePayloads = [];

        foreach ($subjects as $subject) {
            if (!is_array($subject)) {
                $this->response = $this->responseAsJson([
                    'success' => false,
                    'message' => 'Invalid subject language request data.',
                ])->withStatus(400);
                return;
            }

            $groupSubjectId = (int) ($subject['groupSubjectId'] ?? 0);
            $languageId = (int) ($subject['languageId'] ?? 0);

            if ($groupSubjectId <= 0 || $languageId <= 0) {
                $this->response = $this->responseAsJson([
                    'success' => false,
                    'message' => 'Invalid subject language request data.',
                ])->withStatus(400);
                return;
            }

            $subjectLanguagePayloads[$groupSubjectId] = [
                'groupSubjectId' => $groupSubjectId,
                'languageId' => $languageId,
            ];
        }

        $groupSubjectIds = array_keys($subjectLanguagePayloads);
        $languageIds = array_values(array_unique(array_column($subjectLanguagePayloads, 'languageId')));

        $database = $this->registrationSessionStudentSubjectLanguageMapper->getDatabase();
        $transactionStarted = false;

        try {
            $registrationSessionStudent = $this->persistRegistrationSessionStudent($sessionId, $studentId);
            $groupSubjectsById = $groupSubjectIds === []
                ? []
                : $this->groupSubjectMapper
                    ->findByColumn('id', $groupSubjectIds)
                    ->indexBy('id')
                    ->getAll();
            $languagesById = $languageIds === []
                ? []
                : $this->languageMapper
                    ->findByColumn('id', $languageIds)
                    ->indexBy('id')
                    ->getAll();

            /* Making sure that all the given data are valid before updating
             */
            if (count($groupSubjectsById) !== count($groupSubjectIds) || count($languagesById) !== count($languageIds)) {
                $this->response = $this->responseAsJson([
                    'success' => false,
                    'message' => 'Invalid subject language request data.',
                ])->withStatus(400);
                return;
            }

            $modelsToSave = [];

            foreach ($subjectLanguagePayloads as $subjectLanguage) {
                $groupSubjectId = $subjectLanguage['groupSubjectId'];
                $languageId = $subjectLanguage['languageId'];
                $modelsToSave[] = $this->registrationSessionStudentSubjectLanguageMapper
                    ->createModel()
                    ->setRegistrationSessionStudent($registrationSessionStudent)
                    ->setGroupSubject($groupSubjectsById[$groupSubjectId])
                    ->setLanguage($languagesById[$languageId]);
            }

            $transactionStarted = $database->beginTransaction();

            $this->registrationSessionStudentSubjectLanguageMapper->deleteByColumn(
                'registration_session_student_id',
                $registrationSessionStudent->getId()
            );

            if ($modelsToSave !== []) {
                $this->registrationSessionStudentSubjectLanguageMapper->save($modelsToSave);
            }

            $database->commit();
            $transactionStarted = false;

            $this->response = $this->responseAsJson([
                'success' => true,
                'subjects' => array_values($subjectLanguagePayloads),
            ]);
        } catch (MapperNotFound) {
            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Candidate not found for this user in the registration session.',
            ])->withStatus(404);
        } catch (Exception $e) {
            if ($transactionStarted) {
                $database->rollBack();
            }

            $this->response = $this->responseAsJson([
                'success' => false,
                'message' => 'Something has gone wrong. Please try again later.',
            ])->withStatus(500);
        }
    }

    /**
     * Move selected candidates out of preparation.
     *
     * Dynamic preparation rows are persisted before being transitioned.
     *
     * @api POST /adminv2/international-baccalaureate/registrations/sessions/{sessionId}/complete-preparation
     */
    public function sessionCompletePreparation(RegistrationSessionModel $registrationSession): void
    {
        $post       = $this->requestPost;
        $studentIds = $post->get('studentIds');

        try {
            $studentIds = $this->normalisePreparationStudentIds($studentIds);
        } catch (\InvalidArgumentException $e) {
            $this->sendJsonError($e->getMessage(), 400);
            return;
        }

        $database           = $this->registrationSessionStudentMapper->getDatabase();
        $transactionStarted = false;

        try {
            $students             = $this->registrationSessionStudentMapper->findStudentsForSession((int)$registrationSession->getId());
            $studentsToTransition = $this->resolvePreparationStudentsForTransition($students, $studentIds);

            $transactionStarted = $database->beginTransaction();

            foreach ($studentsToTransition as $student) {
                $student->setStatus(RegistrationSessionStudentModel::STATUS_UNCONFIRMED);
                $this->registrationSessionStudentMapper->save($student);
            }

            $database->commit();
            $transactionStarted = false;

            $this->response = $this->responseAsJson([
                                                        'success'           => true,
                                                        'transitionedCount' => count($studentsToTransition),
                                                    ]);
        } catch (\InvalidArgumentException $e) {
            if ($transactionStarted) {
                $database->rollBack();
            }
            $this->sendJsonError($e->getMessage(), 400);
        } catch (Exception) {
            if ($transactionStarted) {
                $database->rollBack();
            }
            $this->sendJsonError('Something has gone wrong. Please try again later.', 500);
        }
    }

    /**
     *  Update a candidate or subject details confirmation checkbox for a registration session student.
     *
     * @api PUT /adminv2/international-baccalaureate/registrations/sessions/{sessionId}/candidates/{studentId}/confirmation
     */
    public function updateRegistrationSessionStudentConfirmation(
        int $sessionId,
        int $studentId
    ): void {
        $payload = new Values($this->request->getParsedBody() ?? []);
        $section = $payload->get('section');
        $confirmed = $payload->get('confirmed');

        if (!in_array($section, self::CONFIRMATION_SECTIONS, true) || !is_bool($confirmed)) {
            $this->sendJsonError('Invalid confirmation request data.', 400);
            return;
        }

        try {
            $registrationSessionStudent = $this->persistRegistrationSessionStudent($sessionId, $studentId);
            $updatedAt = Alaress_Date_Format::now();
            $updatedBy = $this->getUser();

            if ($section === self::CONFIRMATION_SECTION_CANDIDATE) {
                $registrationSessionStudent
                    ->setCandidateConfirmed($confirmed)
                    ->setCandidateConfirmedAt($updatedAt)
                    ->setCandidateConfirmedBy($updatedBy);
            } else {
                $registrationSessionStudent
                    ->setSubjectConfirmed($confirmed)
                    ->setSubjectConfirmedAt($updatedAt)
                    ->setSubjectConfirmedBy($updatedBy);
            }

            $this->registrationSessionStudentMapper->save($registrationSessionStudent);

            $this->response = $this->responseAsJson([
                'success' => true,
                'section' => $section,
                'confirmed' => $confirmed,
                'updatedAt' => $updatedAt,
                'updatedBy' => [
                    'id' => $updatedBy->getId(),
                    'name' => $updatedBy->getFullName(),
                ],
            ]);
        } catch (MapperNotFound) {
            $this->sendJsonError('Candidate not found for this user in the registration session.', 404);
        } catch (Exception $e) {
            $this->sendJsonError('Something has gone wrong. Please try again later.', 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findExternalStudent(RegistrationSessionStudentModel $student): array
    {
        $externalId = trim((string) ($student->getUser()?->getExternalId() ?? ''));

        if ($externalId === '') {
            return [];
        }

        return $this->externalUser->getStudent($externalId);
    }

    /**
     * Persist a registration session student, creating the record if it doesn't exist. This is needed to ensure that
     * there is a registration session student record to link the subject languages to,
     * even if the student hasn't been fully registered yet.
     *
     * @throws MapperNotFound
     */
    private function persistRegistrationSessionStudent(
        int $sessionId,
        int $studentId
    ): RegistrationSessionStudentModel {
        try {
            return $this->registrationSessionStudentMapper->findOneByColumnList([
                'user_id' => $studentId,
                'registration_session_id' => $sessionId,
            ]);
        } catch (MapperNotFound) {
            $registrationSessionStudent = null;

            foreach ($this->registrationSessionStudentMapper->findStudentsForSession($sessionId) as $student) {
                if (
                    $student instanceof RegistrationSessionStudentModel
                    && $student->getUser()?->getId() === $studentId
                ) {
                    $registrationSessionStudent = $student;
                    break;
                }
            }

            if (!$registrationSessionStudent instanceof RegistrationSessionStudentModel) {
                throw new MapperNotFound();
            }

            if ($registrationSessionStudent->getId() !== null) {
                return $registrationSessionStudent;
            }

            try {
                $this->registrationSessionStudentMapper->save($registrationSessionStudent);
            } catch (Exception $e) {
                try {
                    return $this->registrationSessionStudentMapper->findOneByColumnList([
                        'user_id' => $studentId,
                        'registration_session_id' => $sessionId,
                    ]);
                } catch (MapperNotFound) {
                    throw $e;
                }
            }

            return $this->registrationSessionStudentMapper->findOneByColumnList([
                'user_id' => $studentId,
                'registration_session_id' => $sessionId,
            ]);
        }
    }

    /**
     * @param mixed $studentIds
     * @return int[]
     */
    private function normalisePreparationStudentIds(mixed $studentIds): array
    {
        if (!is_array($studentIds) || $studentIds === []) {
            throw new \InvalidArgumentException('Student IDs are required.');
        }

        $normalisedStudentIds = [];

        foreach ($studentIds as $studentId) {
            $studentId = (int) $studentId;

            if ($studentId <= 0) {
                throw new \InvalidArgumentException('Student IDs are required.');
            }

            $normalisedStudentIds[$studentId] = $studentId;
        }

        return array_values($normalisedStudentIds);
    }

    /**
     * @param iterable<mixed> $students
     * @param int[] $studentIds
     * @return RegistrationSessionStudentModel[]
     */
    private function resolvePreparationStudentsForTransition(iterable $students, array $studentIds): array
    {
        $studentsByRequestedId = [];

        foreach ($students as $student) {
            if (!$student instanceof RegistrationSessionStudentModel) {
                continue;
            }

            $candidateIds = [];

            if ($student->getId() !== null) {
                $candidateIds[] = $student->getId();
            }

            if ($student->getUser()?->getId() !== null) {
                $candidateIds[] = $student->getUser()->getId();
            }

            foreach (array_unique($candidateIds) as $candidateId) {
                $studentsByRequestedId[$candidateId][] = $student;
            }
        }

        $studentsToTransition = [];

        foreach ($studentIds as $studentId) {
            $matches = $studentsByRequestedId[$studentId] ?? [];

            if ($matches === []) {
                throw new \InvalidArgumentException('One or more selected students could not be found in this registration session.');
            }

            if (count($matches) > 1) {
                foreach ($matches as $match) {
                    if ($match->getStatus() !== RegistrationSessionStudentModel::STATUS_PREPARATION) {
                        throw new \InvalidArgumentException(
                            'One or more selected student IDs belong to candidates who are already further down the process.'
                        );
                    }
                }

                throw new \InvalidArgumentException('One or more selected student IDs are ambiguous in this registration session.');
            }

            $student = $matches[0];

            if ($student->getStatus() !== RegistrationSessionStudentModel::STATUS_PREPARATION) {
                throw new \InvalidArgumentException(
                    'One or more selected student IDs belong to candidates who are already further down the process.'
                );
            }

            $studentKey                        = $student->getUser()?->getId() ?? $student->getId() ?? $studentId;
            $studentsToTransition[$studentKey] = $student;
        }

        return array_values($studentsToTransition);
    }

    /**
     * Re-send the unconfirmed registration details notification to a student.
     *
     * @api POST /adminv2/international-baccalaureate/registrations/sessions/{sessionId}/candidates/renotify
     */
    public function renotifyUnconfirmed(int $sessionId): void
    {
        $post = $this->requestPost;
        $studentIds = $post->get('studentIds');

        if (!is_array($studentIds)) {
            $this->sendJsonError('Invalid request data', 400);
            return;
        }

        $studentIds = array_values(array_unique(array_filter(
            array_map('intval', $studentIds),
            static fn(int $studentId): bool => $studentId > 0
        )));

        if ($studentIds === []) {
            $this->sendJsonError('Invalid request data', 400);
            return;
        }

        try {
            $registrationSessionStudents = $this->registrationSessionStudentMapper->findByColumnList([
                'user_id' => $studentIds,
                'registration_session_id' => $sessionId,
            ]);
            $registrationSessionStudentsByUserId = [];

            foreach ($registrationSessionStudents as $registrationSessionStudent) {
                if (!$registrationSessionStudent instanceof RegistrationSessionStudentModel) {
                    continue;
                }

                $userId = $registrationSessionStudent->getUser()?->getId();

                if ($userId !== null) {
                    $registrationSessionStudentsByUserId[$userId] = $registrationSessionStudent;
                }
            }

            $renotified = [];
            $failed = [];
            $registrationSessionStudentsToRenotify = [];

            foreach ($studentIds as $studentId) {
                $registrationSessionStudent = $registrationSessionStudentsByUserId[$studentId] ?? null;

                if (!$registrationSessionStudent instanceof RegistrationSessionStudentModel) {
                    $failed[] = [
                        'studentId' => $studentId,
                        'message' => 'Candidate not found for this user in the registration session.',
                    ];
                    continue;
                }

                if ($registrationSessionStudent->getStatus() !== RegistrationSessionStudentModel::STATUS_UNCONFIRMED) {
                    $failed[] = [
                        'studentId' => $studentId,
                        'message' => 'Candidate is not awaiting confirmation.',
                    ];
                    continue;
                }

                $renotified[] = $studentId;
                $registrationSessionStudentsToRenotify[] = $registrationSessionStudent;
            }

            if ($registrationSessionStudentsToRenotify !== []) {
                foreach ($registrationSessionStudentsToRenotify as $registrationSessionStudent) {
                    $this->registrationSessionStudentMapper->propagate(
                        $this->getUser(),
                        BaseMapper::METHOD_UPDATE,
                        $registrationSessionStudent,
                        [
                            'status' => [
                                RegistrationSessionStudentModel::STATUS_UNCONFIRMED,
                                RegistrationSessionStudentModel::STATUS_PREPARATION,
                            ],
                        ]
                    );
                }
            }

            $partial = $failed !== [] && $renotified !== [];
            $success = $renotified !== [];

            $message = match (true) {
                $partial => 'Some students could not be renotified.',
                $failed !== [] => 'No students were renotified.',
                default => 'Student(s) renotified.',
            };

            $this->response = $this->responseAsJson([
                'success' => $success,
                'partial' => $partial,
                'message' => $message,
                'renotified' => $renotified,
                'failed' => $failed,
            ]);
        } catch (MapperNotFound) {
            $this->sendJsonError('Candidate not found for this user in the registration session.', 404);
        } catch (Exception) {
            $this->sendJsonError('Something has gone wrong. Please try again later.', 500);
        }
    }
}
