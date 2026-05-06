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

    public function __construct(
        ConfigMapper $configMapper,
        private IBGroupMapper $groupMapper,
        private IBGroupSubjectMapper $groupSubjectMapper,
        private GroupSubjectLanguageMapper $groupSubjectLanguageMapper,
        private LanguageMapper $languageMapper,
        private FolderCodeMapper $classCodeMapper,
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
     * Search class codes from folder_code.
     *
     * @api GET /adminv2/international-baccalaureate/configuration/api/class-codes
     */
    public function searchClassCodes(): void
    {
        $search = trim((string) $this->requestQuery->get('query', ''));
        $limit = $this->requestQuery->getInt('limit', ClassCodeCursor::LIMIT_DEFAULT);
        $encodedCursor = $this->requestQuery->get('cursor');

        if (null === $limit) {
            $problem = (new ApiProblem('Limit must be a positive integer'))
                ->setStatus(400)
                ->asArray();
            $this->response = $this->responseAsJson($problem)->withStatus(400);
            return;
        }

        try {
            $cursor = new ClassCodeCursor(
                $this->classCodeMapper,
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
        $codeToSubjectIds = [];

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

            foreach ($codes as $code) {
                $codeToSubjectIds[$code][$gsId] = true;
            }
        }

        $allUniqueCodes = array_keys($codeToSubjectIds);

        if (empty($allUniqueCodes)) {
            $results = array_map(
                fn(int $gsId) => [
                    'groupSubjectId' => $gsId,
                    'total' => 0,
                    'byLanguage' => array_values(array_map(
                        fn(array $lang) => ['languageId' => $lang['languageId'], 'count' => 0],
                        $subjectLanguageData[$gsId]
                    )),
                ],
                $groupSubjectIds
            );
            $this->response = $this->responseAsJson($results);
            return;
        }

        $db = $this->groupSubjectLanguageMapper->getDatabase();

        // Query 1: per-subject distinct student totals via code-to-subject mapping
        $totalBySubjectId = array_fill_keys($groupSubjectIds, 0);
        $unionParts = [];
        $unionParams = [];
        foreach ($codeToSubjectIds as $code => $subjectIdMap) {
            foreach (array_keys($subjectIdMap) as $gsId) {
                $unionParts[] = 'SELECT ? AS code, ? AS gid';
                $unionParams[] = $code;
                $unionParams[] = $gsId;
            }
        }

        $unionSql = implode(' UNION ALL ', $unionParts);
        $stmt = $db->prepare(
            "SELECT cg.gid, COUNT(DISTINCT scu.external_id) AS cnt
             FROM sis_class_user scu
             INNER JOIN sis_class sc ON scu.class_id = sc.id
             INNER JOIN sis_user_profile up ON scu.external_id = up.external_id
             INNER JOIN sis_user_profile_student sups ON up.id = sups.sis_user_profile_id
             INNER JOIN ($unionSql) cg ON sc.code = cg.code
             GROUP BY cg.gid"
        );
        $stmt->execute($unionParams);
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $totalBySubjectId[(int) $row['gid']] = (int) $row['cnt'];
        }

        // Query 2: per-(subject, language) distinct student counts to avoid over-counting
        // students enrolled in multiple class codes for the same language.
        $languageCountBySubjectId = [];
        $languageUnionParts = [];
        $languageUnionParams = [];
        $seenLanguageCodeMappings = [];
        foreach ($subjectLanguageData as $gsId => $languages) {
            foreach ($languages as $language) {
                foreach ($language['codes'] as $code) {
                    $mappingKey = $gsId . ':' . $language['languageId'] . ':' . $code;
                    if (isset($seenLanguageCodeMappings[$mappingKey])) {
                        continue;
                    }
                    $seenLanguageCodeMappings[$mappingKey] = true;
                    $languageUnionParts[] = 'SELECT ? AS gid, ? AS language_id, ? AS code';
                    $languageUnionParams[] = $gsId;
                    $languageUnionParams[] = $language['languageId'];
                    $languageUnionParams[] = $code;
                }
            }
        }

        if ($languageUnionParts !== []) {
            $languageUnionSql = implode(' UNION ALL ', $languageUnionParts);
            $stmt = $db->prepare(
                "SELECT cl.gid, cl.language_id, COUNT(DISTINCT scu.external_id) AS cnt
                 FROM sis_class_user scu
                 INNER JOIN sis_class sc ON scu.class_id = sc.id
                 INNER JOIN sis_user_profile up ON scu.external_id = up.external_id
                 INNER JOIN sis_user_profile_student sups ON up.id = sups.sis_user_profile_id
                 INNER JOIN ($languageUnionSql) cl ON sc.code = cl.code
                 GROUP BY cl.gid, cl.language_id"
            );
            $stmt->execute($languageUnionParams);
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $languageCountBySubjectId[(int) $row['gid']][(int) $row['language_id']] = (int) $row['cnt'];
            }
        }

        // Build response
        $results = [];
        foreach ($groupSubjectIds as $gsId) {
            $byLanguage = [];
            foreach ($subjectLanguageData[$gsId] as $language) {
                $byLanguage[] = [
                    'languageId' => $language['languageId'],
                    'count' => $languageCountBySubjectId[$gsId][$language['languageId']] ?? 0,
                ];
            }
            $results[] = [
                'groupSubjectId' => $gsId,
                'total' => $totalBySubjectId[$gsId],
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

                    $languages[$languageId]['languageId'] = $languageId;
                    $languages[$languageId]['languageName'] = $language->getLanguage()?->getName();
                    $languages[$languageId]['classCodes'] = [
                        ...($languages[$languageId]['classCodes'] ?? []),
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
                        ],
                        $languages
                    )),
                ];
            }

            $this->response = $this->responseAsJson($subjects);
        } catch (Exception $e) {
            $this->response = $this->responseAsJson(['error' => $e->getMessage()])->withStatus(500);
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

                $modelsToSave[] = $this->groupSubjectLanguageMapper
                    ->createModel()
                    ->setGroupSubject($this->groupSubjectMapper->createModel()->setId($groupSubjectId))
                    ->setLanguage($languagesById[$languageId])
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
                        'create' => $this->getRouteIfAuthorized('internationalBaccalaureate.createRegistrationSession'),
                        'delete' => $this->getRouteIfAuthorized('internationalBaccalaureate.deleteRegistrationSession', [
                            'sessionId' => 0,
                        ]),
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
            $this->response = $this->responseAsJson(
                [
                    'success' => false,
                    'message' => $e->getMessage()
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

    /**
     * Delete a registration session
     *
     * @api DELETE /adminv2/international-baccalaureate/registrations/sessions/{sessionId}
     */
    public function deleteRegistrationSession(RegistrationSessionModel $registrationSession): void
    {
        //TODO: might need to add conditions to prevent deletion of a session if it's already been completed (ie registered with IB SA-21597/SA-21596)
        try {
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
}
