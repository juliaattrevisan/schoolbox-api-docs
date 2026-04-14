<?php

/**
 * @copyright   Copyright 2026, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\External\Connector\Edlink\Base\Endpoint\Profile;

use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\IApi;
use Schoolbox\External\Connector\Edlink\Base\Endpoint\Lookup\PeopleIds;
use Schoolbox\External\Connector\FirstMeaningfulValue;
use Schoolbox\External\Connector\ValidStrats;
use Schoolbox\External\Connector\Watchlist;
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
     * How long to wait when downloading photos from Edlink-signed URLs
     */
    private const PHOTO_TIMEOUT = 15;

    /**
     * Tracks parent persons we've already imported, keyed by resolved external ID
     * Used to avoid duplicate parent profiles when multiple students share a parent
     *
     * @var array<string, bool>
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
     * Dedicated guzzle client for downloading photos from Edlink-signed URLs.
     * Separate from the API client because photo URLs aren't on the API host
     * and shouldn't receive our Bearer token.
     */
    private Client $photoClient;

    /**
     * Profile constructor.
     */
    public function __construct(
        private ValidStrats $validStrats,
        private LoggerInterface $log,
        private bool $perform,
        private IApi $api,
        private Watchlist $watcher,
        private array $watchlist,
        private bool $includePhoto,
        private PeopleIds $peopleIds,
        private UserProfile\Mapper $baseProfileMapper,
        private UserProfile\Student\Mapper $studentProfileMapper,
        private UserProfile\Parent\Mapper $parentProfileMapper,
        private UserProfile\Staff\Mapper $staffProfileMapper,
        private UserProfile\Relationship\Mapper $relationshipMapper,
        private StorageMapper $storageMapper
    ) {
        $this->photoClient = new Client([
            'timeout' => self::PHOTO_TIMEOUT,
            'http_errors' => true,
        ]);
    }

    /**
     * Import user profile data from Edlink
     */
    public function import(): void
    {
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

            if (!isset($response['$data']) || !\count($response['$data'])) {
                break;
            }

            foreach ($response['$data'] as $person) {
                $edlinkId = trim((string)($person['id'] ?? ''));

                if ('' === $edlinkId) {
                    continue;
                }

                $externalId = PeopleIds::resolveExternalId($person);

                if ('' === $externalId) {
                    $externalId = $edlinkId;
                }

                /* Share the mapping with the Classes strategy
                 */
                $this->peopleIds->set($edlinkId, $externalId);

                /* Apply watchlist filter if set
                 */
                if (!$this->watcher->isOnWatchlist($externalId, $this->watchlist)) {
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
     */
    private function importStudent(array $person, string $externalId): void
    {
        $firstname = trim((string)($person['first_name'] ?? ''));
        $firstnamePreferred = (new FirstMeaningfulValue())->find([
            trim((string)($person['display_name'] ?? '')),
            $firstname,
        ]);
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
        $stateId = PeopleIds::resolveIdentifierByType($person, 'state_id');

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
        if ($this->includePhoto && !empty($person['picture_url'])) {
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
     */
    private function importStaff(array $person, string $externalId, array $roles): void
    {
        $firstname = trim((string)($person['first_name'] ?? ''));
        $firstnamePreferred = (new FirstMeaningfulValue())->find([
            trim((string)($person['display_name'] ?? '')),
            $firstname,
        ]);
        $lastname = trim((string)($person['last_name'] ?? ''));
        $email = trim((string)($person['email'] ?? ''));
        $phone = trim((string)($person['phone'] ?? ''));

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
            ->setFirstname($firstname)
            ->setFirstnamePreferred($firstnamePreferred)
            ->setLastname($lastname);

        /* Download photo if available and photo import is enabled
         */
        if ($this->includePhoto && !empty($person['picture_url'])) {
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
     * Agents describe observer->target relationships.
     * observer_id = the parent/guardian (Person ID)
     * target_id = the student (Person ID)
     * relationship = "parent" or "guardian"
     */
    private function importAgents(): void
    {
        $nextUrl = sprintf('agents?$first=%s', self::NUM_PER_PAGE);

        for ($i = 0; ($nextUrl && $i < self::MAX_CALLS); $i++) {
            /* Use $expand=observer,target to get Person data inline,
             * avoiding a separate API call per agent.
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

                /* Resolve external IDs via the shared lookup, with fallback
                 * to inline resolution from the expanded person data.
                 */
                $observerExternalId = $this->peopleIds->resolve($observerEdlinkId);
                if ($observerExternalId === $observerEdlinkId) {
                    $observerExternalId = PeopleIds::resolveExternalId($observerPerson);
                }
                $studentExternalId = $this->peopleIds->resolve($studentEdlinkId);
                if ($studentExternalId === $studentEdlinkId) {
                    $studentExternalId = PeopleIds::resolveExternalId($studentPerson);
                }

                if ('' === $observerExternalId || '' === $studentExternalId) {
                    continue;
                }

                $isPrimaryContact = \in_array('primary_contact', $flags, true);
                $isAlternateContact = \in_array('alternate_contact', $flags, true);
                $isEmergencyContact = \in_array('emergency_contact', $flags, true);

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
     */
    private function importParent(array $person, string $externalId): void
    {
        $firstname = trim((string)($person['first_name'] ?? ''));
        $lastname = trim((string)($person['last_name'] ?? ''));
        $email = trim((string)($person['email'] ?? ''));
        $phone = trim((string)($person['phone'] ?? ''));
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
     * Import an emergency contact onto a student profile.
     *
     * Fills slots 1-3 in order. Contacts beyond 3 are skipped.
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
     * Download a photo from an Edlink-signed URL and attach it to the base profile.
     */
    private function downloadPhoto(UserProfile\Model $baseProfile, string $url): void
    {
        try {
            $response = $this->photoClient->request('GET', $url);
            $imageContent = $response->getBody()->getContents();

            if ('' === $imageContent) {
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
     * Parse an Edlink ISO 8601 date string into an Alaress_Date_Format
     *
     * Edlink sends dates like "2010-05-15T00:00:00.000Z" or "2010-05-15"
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
}
