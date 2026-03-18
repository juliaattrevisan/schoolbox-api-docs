<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile;

use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\IApi;
use Schoolbox\External\Importer\Endpoint\IStrategy;
use Schoolbox\External\Profile as UserProfile;
use Schoolbox\Mapper\Exception;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;

/**
 * Class Profile
 *
 * Imports sis_user_profile* data from the Edlink Graph API
 *
 * @package Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile
 */
class Profile implements IStrategy
{
    /**
     * Prevent infinite loop of api calls
     */
    private const MAX_CALLS = 500;

    /**
     * Number of records per page
     * Edlink recommends 10000 for bulk sync
     */
    private const NUM_PER_PAGE = 10000;

    /**
     * Edlink role constants
     */
    private const ROLE_STUDENT = 'student';
    private const ROLE_TEACHER = 'teacher';
    private const ROLE_ADMINISTRATOR = 'administrator';
    private const ROLE_OBSERVER = 'observer';

    /**
     * Staff category constants matching Schoolbox convention
     */
    private const CATEGORY_TEACHER = 'teacher';
    private const CATEGORY_EMPLOYEE = 'employee';

    /**
     * Preferred identifier types from the Edlink identifiers array, in priority order.
     * The first match wins. Falls back to Edlink UUID if none found.
     */
    private const IDENTIFIER_PRIORITY = ['sis_id', 'student_id', 'staff_id'];

    /**
     * Tracks parent persons we've already imported, keyed by resolved external ID
     * Used to avoid duplicate parent profiles when multiple students share a parent
     *
     * @var array
     */
    private array $importedParents = [];

    /**
     * Maps Edlink UUID → resolved external ID (SIS ID)
     * Used by importAgents() to look up the SIS ID for students/parents
     * that were already imported by importPeople()
     *
     * @var array<string, string>
     */
    private array $edlinkIdToExternalId = [];

    /**
     * School lookup: Edlink school ID → school name
     * Used to resolve campus names from school_ids
     *
     * @var array<string, string>
     */
    private array $schools = [];

    /**
     * @param ValidStrats                     $validStrats
     * @param LoggerInterface                 $log
     * @param bool                            $perform
     * @param IApi                            $api
     * @param array                           $watchlist
     * @param bool                            $importPhotos
     * @param UserProfile\Mapper              $baseProfileMapper
     * @param UserProfile\Student\Mapper      $studentProfileMapper
     * @param UserProfile\Parent\Mapper       $parentProfileMapper
     * @param UserProfile\Staff\Mapper        $staffProfileMapper
     * @param UserProfile\Relationship\Mapper $relationshipMapper
     * @param StorageMapper                   $storageMapper
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private array $watchlist,
        private bool $importPhotos,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper,
        private StorageMapper $storageMapper
    ) {
    }

    /**
     * Import user profile data from Edlink
     */
    public function import(): void
    {
        /* Preload schools for campus name resolution
         */
        $this->log->info('Preloading schools from Edlink.');
        $this->preloadSchools();

        /* Import people (students and staff)
         */
        if ($this->validStrats->isStudentStratEnabled()
            || $this->validStrats->isFamiliesStratEnabled()
            || $this->validStrats->isStaffStratEnabled()
        ) {
            $this->log->info('Processing people from Edlink.');
            $this->importPeople();
        }

        /* Import parent/guardian relationships via agents endpoint
         */
        if ($this->validStrats->isFamiliesStratEnabled()) {
            $this->log->info('Processing agents (parent/guardian relationships) from Edlink.');
            $this->importAgents();
        }
    }

    /**
     * Import all people from the /people endpoint
     * Students and staff are identified by their roles[] array
     */
    private function importPeople(): void
    {
        $nextUrl = sprintf('people?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            /* Diagnostic: log the response keys so we can see what Edlink actually returns
             */
            if ($i === 0) {
                $this->log->info(sprintf(
                    'DIAGNOSTIC: Response keys=%s, record count=%s, raw first 200 chars=%s',
                    json_encode(array_keys($response)),
                    isset($response['$data']) ? \count($response['$data']) : 'KEY_NOT_FOUND',
                    substr(json_encode($response), 0, 200)
                ));
            }

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $person) {
                $edlinkId = trim((string)($person['id'] ?? ''));

                if ('' === $edlinkId) {
                    continue;
                }

                /* Resolve the external ID: prefer SIS ID from identifiers[], fall back to Edlink UUID
                 */
                $externalId = $this->resolveExternalId($person);

                if ('' === $externalId) {
                    $externalId = $edlinkId;
                }

                /* Temporary diagnostic: log the first few people's identifier resolution
                 */
                if ($i === 0) {
                    $this->log->info(sprintf(
                        'DIAGNOSTIC: Edlink ID=%s, resolved externalId=%s, roles=%s, identifiers=%s',
                        $edlinkId,
                        $externalId,
                        json_encode($person['roles'] ?? 'NOT_SET'),
                        json_encode($person['identifiers'] ?? 'NOT_SET')
                    ));
                }

                /* Cache the mapping so importAgents() can resolve IDs later
                 */
                $this->edlinkIdToExternalId[$edlinkId] = $externalId;

                /* Apply watchlist filter if set
                 */
                if (\count($this->watchlist) && !Watchlist::isOnWatchlist($externalId, $this->watchlist)) {
                    continue;
                }

                $roles = $person['roles'] ?? [];

                /* A person can have multiple roles; process each applicable one
                 */
                if (\in_array(self::ROLE_STUDENT, $roles, true)
                    && ($this->validStrats->isStudentStratEnabled() || $this->validStrats->isFamiliesStratEnabled())
                ) {
                    $this->importStudent($person, $externalId);
                }

                if ((\in_array(self::ROLE_TEACHER, $roles, true) || \in_array(self::ROLE_ADMINISTRATOR, $roles, true))
                    && $this->validStrats->isStaffStratEnabled()
                ) {
                    $this->importStaff($person, $externalId, $roles);
                }
            }

            $nextUrl = $response['$next'] ?? false;
        }
    }

    /**
     * Import a single student profile
     *
     * @param array  $person
     * @param string $externalId
     */
    private function importStudent(array $person, string $externalId): void
    {
        $firstname = trim((string)($person['first_name'] ?? ''));
        $middleName = trim((string)($person['middle_name'] ?? ''));
        $firstnamePreferred = trim((string)($person['first_name'] ?? ''));
        $lastname = trim((string)($person['last_name'] ?? ''));
        $email = trim((string)($person['email'] ?? ''));
        $phone = trim((string)($person['phone'] ?? ''));

        /* Demographics
         */
        $demographics = $person['demographics'] ?? [];
        $gender = trim((string)($demographics['gender'] ?? ''));
        $birthday = $demographics['birthday'] ?? null;

        /* Grade levels is an array — take the first meaningful value
         */
        $yearLevel = '';
        if (isset($person['grade_levels']) && \count($person['grade_levels'])) {
            $yearLevel = trim((string)$person['grade_levels'][0]);
        }

        /* Graduation year
         */
        $graduationYear = $person['graduation_year'] ?? null;

        /* Campus — resolve from school_ids if available
         * Use the first school's name from our preloaded schools
         */
        $campusName = '';
        $schoolIds = $person['school_ids'] ?? [];
        if (\count($schoolIds) && \count($this->schools)) {
            $firstSchoolId = $schoolIds[0];
            $campusName = $this->schools[$firstSchoolId] ?? '';
        }

        /* Username — Edlink doesn't provide a dedicated username field
         * Use email as the most reliable identifier, fall back to external ID
         */
        $username = (new FirstMeaningfulValue())->find([
            $email,
            $externalId,
        ]);

        $fullname = trim("{$firstname} {$lastname}");

        /* Find or create the profile
         */
        try {
            $studentProfile = $this->studentProfileMapper->findByExternalId($externalId);
            $studentBaseProfile = $studentProfile->getBaseProfile();
        } catch (Exception $e) {
            $studentProfile = $this->studentProfileMapper->createModel();

            try {
                $studentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
            } catch (Exception $e) {
                $studentBaseProfile = $this->baseProfileMapper->createModel();
            }

            $studentProfile->setBaseProfile($studentBaseProfile);
        }

        $studentBaseProfile
            ->setExternalId($externalId)
            ->setFirstname($firstname)
            ->setFirstnamePreferred($firstnamePreferred)
            ->setLastname($lastname);

        /* Download photo if available and photo import is enabled
         */
        if ($this->importPhotos && !empty($person['picture_url'])) {
            $this->downloadPhoto($studentBaseProfile, $person['picture_url']);
        }

        if ($this->perform) {
            $this->baseProfileMapper->save($studentBaseProfile);
        }

        $studentProfile
            ->setUsername($username)
            ->setEmail($email)
            ->setMobile($phone)
            ->setGender($gender)
            ->setYearLevel($yearLevel)
            ->setCampusName($campusName);

        /* Date of birth from demographics.birthday
         */
        if (!empty($birthday)) {
            try {
                $dob = $this->parseEdlinkDate($birthday);
                if (null !== $dob) {
                    $studentProfile->setDateOfBirth($dob);
                }
            } catch (\Exception $e) {
                $this->log->debug(sprintf('Could not parse birthday for %s: %s', $externalId, $birthday));
            }
        }

        /* Studies code from graduation year if available
         */
        if (null !== $graduationYear) {
            $studentProfile->setStudiesCode((string)$graduationYear);
        }

        $this->log->info(sprintf(
            'Adding student profile for %s (preferred: %s) (%s) year=%s campus=%s',
            $fullname,
            $firstnamePreferred,
            $externalId,
            $yearLevel,
            $campusName
        ));

        if ($this->perform) {
            $this->studentProfileMapper->save($studentProfile);
        }
    }

    /**
     * Import a single staff profile
     *
     * @param array  $person
     * @param string $externalId
     * @param array  $roles
     */
    private function importStaff(array $person, string $externalId, array $roles): void
    {
        $firstname = trim((string)($person['first_name'] ?? ''));
        $firstnamePreferred = trim((string)($person['first_name'] ?? ''));
        $lastname = trim((string)($person['last_name'] ?? ''));
        $email = trim((string)($person['email'] ?? ''));
        $phone = trim((string)($person['phone'] ?? ''));
        $title = '';

        $username = (new FirstMeaningfulValue())->find([
            $email,
            $externalId,
        ]);

        /* Determine category based on roles
         */
        $category = self::CATEGORY_EMPLOYEE;
        if (\in_array(self::ROLE_TEACHER, $roles, true)) {
            $category = self::CATEGORY_TEACHER;
        }

        $fullname = trim("{$firstname} {$lastname}");

        /* Find or create the profile
         */
        try {
            $staffProfile = $this->staffProfileMapper->findByExternalId($externalId);
            $staffBaseProfile = $staffProfile->getBaseProfile();
        } catch (Exception $e) {
            $staffProfile = $this->staffProfileMapper->createModel();

            try {
                $staffBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
            } catch (Exception $e) {
                $staffBaseProfile = $this->baseProfileMapper->createModel();
            }

            $staffProfile->setBaseProfile($staffBaseProfile);
        }

        $staffBaseProfile
            ->setExternalId($externalId)
            ->setTitle($title)
            ->setFirstname($firstname)
            ->setFirstnamePreferred($firstnamePreferred)
            ->setLastname($lastname);

        /* Download photo if available and photo import is enabled
         */
        if ($this->importPhotos && !empty($person['picture_url'])) {
            $this->downloadPhoto($staffBaseProfile, $person['picture_url']);
        }

        if ($this->perform) {
            $this->baseProfileMapper->save($staffBaseProfile);
        }

        $staffProfile
            ->setCategory($category)
            ->setEmail($email)
            ->setPhone($phone)
            ->setUsername($username);

        $this->log->info(sprintf(
            'Adding staff profile for %s (preferred: %s) (%s)',
            $fullname,
            $firstnamePreferred,
            $externalId
        ));

        if ($this->perform) {
            $this->staffProfileMapper->save($staffProfile);
        }
    }

    /**
     * Import parent/guardian relationships from the /agents endpoint
     *
     * Agents describe observer→target relationships.
     * observer_id = the parent/guardian (Person ID)
     * target_id = the student (Person ID)
     * relationship = "parent" or "guardian"
     */
    private function importAgents(): void
    {
        $nextUrl = sprintf('agents?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            /* Use $expand=observer to get the parent's Person data inline
             * This avoids a separate API call per parent
             */
            $response = $this->api->retrieve('GET', $nextUrl . '&$expand=observer,target');

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $agent) {
                $relationship = trim((string)($agent['relationship'] ?? ''));

                /* Only process parent/guardian relationships
                 */
                if ($relationship !== 'parent' && $relationship !== 'guardian') {
                    continue;
                }

                $parentPerson = $agent['observer'] ?? null;
                $studentPerson = $agent['target'] ?? null;

                if (!$parentPerson || !$studentPerson) {
                    continue;
                }

                $parentEdlinkId = trim((string)($parentPerson['id'] ?? ''));
                $studentEdlinkId = trim((string)($studentPerson['id'] ?? ''));

                if ('' === $parentEdlinkId || '' === $studentEdlinkId) {
                    continue;
                }

                /* Resolve external IDs: use cached mapping from importPeople() if available,
                 * otherwise resolve from the expanded person data
                 */
                $parentExternalId = $this->edlinkIdToExternalId[$parentEdlinkId]
                    ?? $this->resolveExternalId($parentPerson);
                $studentExternalId = $this->edlinkIdToExternalId[$studentEdlinkId]
                    ?? $this->resolveExternalId($studentPerson);

                if ('' === $parentExternalId || '' === $studentExternalId) {
                    continue;
                }

                /* Import the parent profile (only once per parent)
                 */
                if (!isset($this->importedParents[$parentExternalId])) {
                    $this->importParent($parentPerson, $parentExternalId);
                    $this->importedParents[$parentExternalId] = true;
                }

                /* Create the relationship between parent and student
                 */
                $this->createRelationship($parentExternalId, $studentExternalId, $relationship);
            }

            $nextUrl = $response['$next'] ?? false;
        }
    }

    /**
     * Import a single parent profile
     *
     * @param array  $person
     * @param string $externalId
     */
    private function importParent(array $person, string $externalId): void
    {
        $firstname = trim((string)($person['first_name'] ?? ''));
        $lastname = trim((string)($person['last_name'] ?? ''));
        $email = trim((string)($person['email'] ?? ''));
        $phone = trim((string)($person['phone'] ?? ''));
        $title = '';
        $username = (new FirstMeaningfulValue())->find([$email, $externalId]);

        try {
            $parentProfile = $this->parentProfileMapper->findByExternalId($externalId);
            $parentBaseProfile = $parentProfile->getBaseProfile();
        } catch (Exception $e) {
            $parentProfile = $this->parentProfileMapper->createModel();

            try {
                $parentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $externalId);
            } catch (Exception $e) {
                $parentBaseProfile = $this->baseProfileMapper->createModel();
            }

            $parentProfile->setBaseProfile($parentBaseProfile);
        }

        $parentBaseProfile
            ->setExternalId($externalId)
            ->setTitle($title)
            ->setFirstname($firstname)
            ->setLastname($lastname);

        if ($this->perform) {
            $this->baseProfileMapper->save($parentBaseProfile);
        }

        $parentProfile
            ->setEmail($email)
            ->setMobile($phone)
            ->setUsername($username);

        $this->log->info(sprintf(
            'Adding parent profile for %s (%s)',
            trim("{$firstname} {$lastname}"),
            $externalId
        ));

        if ($this->perform) {
            $this->parentProfileMapper->save($parentProfile);
        }
    }

    /**
     * Create a parent-student relationship record
     *
     * @param string $parentExternalId
     * @param string $studentExternalId
     * @param string $relationshipType
     */
    private function createRelationship(string $parentExternalId, string $studentExternalId, string $relationshipType): void
    {
        try {
            $parentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $parentExternalId);
        } catch (Exception $e) {
            $this->log->warning(sprintf(
                'Could not find parent base profile for %s; skipping relationship',
                $parentExternalId
            ));
            return;
        }

        try {
            $studentBaseProfile = $this->baseProfileMapper->findOneByColumn('external_id', $studentExternalId);
        } catch (Exception $e) {
            $this->log->warning(sprintf(
                'Could not find student base profile for %s; skipping relationship',
                $studentExternalId
            ));
            return;
        }

        $this->log->info(sprintf(
            'Adding relation: %s is %s of %s',
            trim("{$parentBaseProfile->getFirstname()} {$parentBaseProfile->getLastname()}"),
            $relationshipType,
            trim("{$studentBaseProfile->getFirstname()} {$studentBaseProfile->getLastname()}")
        ));

        if ($this->perform) {
            $relationship = $this->relationshipMapper->createModel()
                ->setUserProfile($parentBaseProfile)
                ->setChildProfile($studentBaseProfile)
                ->setType($relationshipType);

            $this->relationshipMapper->save($relationship);
        }
    }

    /**
     * Download a photo from a URL and attach it to the base profile
     *
     * @param UserProfile\Model $baseProfile
     * @param string            $url
     */
    private function downloadPhoto(UserProfile\Model $baseProfile, string $url): void
    {
        try {
            $imageContent = @file_get_contents($url);

            if (false === $imageContent || '' === $imageContent) {
                return;
            }

            $storage = $this->storageMapper->createModel();
            $storage->setData($imageContent);

            if ($this->perform) {
                $this->storageMapper->save($storage);
                $baseProfile->setPortrait($storage);
            }
        } catch (\Throwable $e) {
            $this->log->warning(sprintf(
                'Could not download photo for %s: %s',
                $baseProfile->getExternalId(),
                $e->getMessage()
            ));
        }
    }

    /**
     * Resolve the best external ID for a person from Edlink data.
     *
     * Checks the identifiers[] array for SIS-provided IDs (sis_id, student_id, staff_id)
     * in priority order. Falls back to the Edlink UUID if no SIS identifier is found.
     *
     * @param array $person
     * @return string
     */
    /**
     * Preload all schools into a lookup array for campus name resolution
     */
    private function preloadSchools(): void
    {
        $nextUrl = sprintf('schools?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            $response = $this->api->retrieve('GET', $nextUrl);

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $school) {
                $id = trim((string)($school['id'] ?? ''));
                $name = trim((string)($school['name'] ?? ''));

                if ('' !== $id && '' !== $name) {
                    $this->schools[$id] = $name;
                }
            }

            $nextUrl = $response['$next'] ?? false;
        }

        $this->log->info(sprintf('Loaded %d schools.', \count($this->schools)));
    }

    /**
     * Parse an Edlink ISO 8601 date string into an Alaress_Date_Format
     *
     * Edlink sends dates like "2010-05-15T00:00:00.000Z" or "2010-05-15"
     *
     * @param string $dateString
     * @return \Alaress_Date_Format|null
     */
    private function parseEdlinkDate(string $dateString): ?\Alaress_Date_Format
    {
        if ('' === $dateString) {
            return null;
        }

        try {
            $dateTime = new \DateTimeImmutable($dateString);

            return \Alaress_Date_Format::newDatabaseDate(
                $dateTime->format('Y-m-d H:i:s'),
                new \DateTimeZone($dateTime->format('e') !== 'Z' ? $dateTime->format('e') : 'UTC')
            );
        } catch (\Exception $e) {
            $this->log->warning(sprintf('Could not parse Edlink date: %s', $dateString));
            return null;
        }
    }

    private function resolveExternalId(array $person): string
    {
        $edlinkId = trim((string)($person['id'] ?? ''));
        $identifiers = $person['identifiers'] ?? [];

        if (\is_array($identifiers) && \count($identifiers)) {
            /* Build a type → value lookup from the identifiers array
             * Edlink identifiers are [{type: "sis_id", value: "12345"}, ...]
             */
            $idLookup = [];

            foreach ($identifiers as $identifier) {
                if (!\is_array($identifier)) {
                    continue;
                }

                $type = trim((string)($identifier['type'] ?? ''));
                $value = trim((string)($identifier['value'] ?? ''));

                if ('' !== $type && '' !== $value) {
                    $idLookup[$type] = $value;
                }
            }

            /* Check priority list for the first available SIS identifier
             */
            foreach (self::IDENTIFIER_PRIORITY as $preferredType) {
                if (isset($idLookup[$preferredType])) {
                    return $idLookup[$preferredType];
                }
            }

            /* If none of the preferred types matched, use the first available identifier
             * (this catches provider-specific types like canvas_id, powerschool_id, etc.)
             */
            if (\count($idLookup)) {
                return $idLookup[array_key_first($idLookup)];
            }
        }

        /* Fall back to Edlink UUID
         */
        return $edlinkId;
    }
}
