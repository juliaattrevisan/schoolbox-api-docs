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
     * Tracks emergency contact count per student external ID
     * Used to fill slots 1-3 in order
     *
     * @var array<string, int>
     */
    private array $emergencyContactCount = [];

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
        $iep = !empty($demographics['iep']);
        $disability = trim((string)($demographics['disability'] ?? ''));
        $specialAccommodations = !empty($demographics['special_accommodations']);
        $ell = !empty($demographics['english_language_learner']);

        /* Grade levels is an array — take the first meaningful value
         */
        $yearLevel = '';
        if (isset($person['grade_levels']) && \count($person['grade_levels'])) {
            $yearLevel = trim((string)$person['grade_levels'][0]);
        }

        /* State ID from identifiers array for studies code
         */
        $stateId = $this->resolveIdentifierByType($person, 'state_id');

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
            ->setEmail($email)
            ->setMobile($phone)
            ->setGender($gender)
            ->setYearLevel($yearLevel)
            ->setIndividualLearningPlans($iep)
            ->setSpecialNeeds($specialAccommodations)
            ->setEsl($ell);

        /* Medical alert from disability field
         */
        if ('' !== $disability) {
            $studentProfile->setAlertMedical($disability);
        }

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

        /* Studies code from state_id identifier if available
         */
        if ('' !== $stateId) {
            $studentProfile->setStudiesCode($stateId);
        }

        $this->log->info(sprintf(
            'Adding student profile for %s (preferred: %s) (%s) year=%s',
            $fullname,
            $firstnamePreferred,
            $externalId,
            $yearLevel
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
                $flags = $agent['flags'] ?? [];

                $observerPerson = $agent['observer'] ?? null;
                $studentPerson = $agent['target'] ?? null;

                if (!$observerPerson || !$studentPerson) {
                    continue;
                }

                $observerEdlinkId = trim((string)($observerPerson['id'] ?? ''));
                $studentEdlinkId = trim((string)($studentPerson['id'] ?? ''));

                if ('' === $observerEdlinkId || '' === $studentEdlinkId) {
                    continue;
                }

                /* Resolve external IDs: use cached mapping from importPeople() if available,
                 * otherwise resolve from the expanded person data
                 */
                $observerExternalId = $this->edlinkIdToExternalId[$observerEdlinkId]
                    ?? $this->resolveExternalId($observerPerson);
                $studentExternalId = $this->edlinkIdToExternalId[$studentEdlinkId]
                    ?? $this->resolveExternalId($studentPerson);

                if ('' === $observerExternalId || '' === $studentExternalId) {
                    continue;
                }

                $isPrimaryContact = \in_array('primary_contact', $flags, true);
                $isAlternateContact = \in_array('alternate_contact', $flags, true);
                $isEmergencyContact = \in_array('emergency_contact', $flags, true);

                /* Additional flags available but not yet used:
                 * - has_custody: observer has legal custody of the student
                 * - lives_with: student lives with the observer
                 * - pickup_rights: observer is authorised to pick up the student
                 */

                /* Import as parent if flagged as primary or alternate contact
                 */
                if ($isPrimaryContact || $isAlternateContact) {
                    if (!isset($this->importedParents[$observerExternalId])) {
                        $this->importParent($observerPerson, $observerExternalId);
                        $this->importedParents[$observerExternalId] = true;
                    }

                    $this->createRelationship($observerExternalId, $studentExternalId, $relationship);
                }

                /* Import as emergency contact if flagged
                 */
                if ($isEmergencyContact) {
                    $this->importEmergencyContact($observerPerson, $studentExternalId);
                }
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
     * Import an emergency contact onto a student profile
     *
     * Fills slots 1-3 in order. Contacts beyond 3 are skipped.
     *
     * @param array  $person            The emergency contact's person data
     * @param string $studentExternalId The student's external ID
     */
    private function importEmergencyContact(array $person, string $studentExternalId): void
    {
        $slot = ($this->emergencyContactCount[$studentExternalId] ?? 0) + 1;

        if ($slot > 3) {
            return;
        }

        $this->emergencyContactCount[$studentExternalId] = $slot;

        $name = trim(
            trim((string)($person['first_name'] ?? ''))
            . ' '
            . trim((string)($person['last_name'] ?? ''))
        );
        $phone = trim((string)($person['phone'] ?? ''));

        if ('' === $name && '' === $phone) {
            return;
        }

        try {
            $studentProfile = $this->studentProfileMapper->findByExternalId($studentExternalId);
        } catch (Exception $e) {
            $this->log->warning(sprintf(
                'Could not find student profile for %s; skipping emergency contact',
                $studentExternalId
            ));
            return;
        }

        $nameSetter = "setEmergencyContactName{$slot}";
        $phoneSetter = "setEmergencyContactPhone{$slot}";

        $studentProfile->$nameSetter($name);
        $studentProfile->$phoneSetter($phone);

        $this->log->info(sprintf(
            'Adding emergency contact %d for %s: %s (%s)',
            $slot,
            $studentExternalId,
            $name,
            $phone
        ));

        if ($this->perform) {
            $this->studentProfileMapper->save($studentProfile);
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

    /**
     * Resolve a single identifier value by type from the identifiers[] array
     *
     * @param array  $person
     * @param string $type
     * @return string
     */
    private function resolveIdentifierByType(array $person, string $type): string
    {
        $identifiers = $person['identifiers'] ?? [];

        if (!\is_array($identifiers)) {
            return '';
        }

        foreach ($identifiers as $identifier) {
            if (!\is_array($identifier)) {
                continue;
            }

            $idType = trim((string)($identifier['type'] ?? ''));
            $value = trim((string)($identifier['value'] ?? ''));

            if ($idType === $type && '' !== $value) {
                return $value;
            }
        }

        return '';
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
