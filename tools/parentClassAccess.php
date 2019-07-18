#!/usr/bin/php
<?php
/**
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use \Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Realtime\Routing\Cache\WritableCache;
use \Schoolbox\Role;
use \Schoolbox\UserMapper;
use \Schoolbox\Permission\Access\UserAccessMapper;
use \Schoolbox\Permission\Access\UserAccess as UserAccessModel;
use \Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Application\CliTool;

require_once __DIR__ . '/../conf/core/require.php';

$kernel = new CliTool\Kernel(


    new class extends CliTool\App
    {

        // the name of the CSV to import
        private const OPT_SHORT_FILE = 'f:';
        // to load students as well as parents into the group
        private const OPT_LONG_STUDENTS = 'students';

        private const OPT_LONG_DRY_RUN = 'dry-run';


        /** @inheritdoc */
        public function getShortOpts(): array
        {
            return [self::OPT_SHORT_FILE];
        }


        /** @inheritdoc */
        public function getLongOpts(): array
        {
            return [
                self::OPT_LONG_STUDENTS,
                self::OPT_LONG_DRY_RUN,
            ];
        }


        /** @inheritdoc */
        public function run(): void
        {
            $log = $this->getLogger();
            $userMapper = UserMapper::getInstance();
            $userAccessMapper = UserAccessMapper::getInstance();
            $folderMapper = FolderMapper::getInstance();
            $classHandler = new \External_Class();

            // suppress the route cache mutations
            $userAccessMapper->unregisterCallbacks();

            if (!$this->getOpt(self::OPT_SHORT_FILE)) {
                $log->error("No import filename specified");
                exit;
            }

            if (!file_exists($this->getOpt(self::OPT_SHORT_FILE))) {
                $log->error("Specified import file not found");
                exit;
            }

            $loadStudents = $this->getOpt(self::OPT_LONG_STUDENTS);
            $dryRun = $this->getOpt(self::OPT_LONG_DRY_RUN);

            $folders = [];
            $classes = [];

            $handle = fopen($this->getOpt(self::OPT_SHORT_FILE), 'rb');

            if ($handle) {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) > 1) {
                        if (!isset($classes[$row[0]])) {
                            $classes[$row[0]] = [];
                        }

                        for ($i = 1; $i < count($row); $i++) {
                            $code = $row[$i];
                            if (strlen(trim($code)) > 0) {
                                $classes[$row[0]][] = $row[$i];
                            }
                        }
                    }
                }
                fclose($handle);
            } else {
                // Couldn't read file
                $log->error('Could not locate file to import.');
                exit;
            }

            if ($dryRun) {
                $this->getLogger()->warning('Performing a dry run. Provide --perform to update DB.');
            } else if (!$this->perform()) {
                // If not explicitly specifying --perform option
                $this->getLogger()->warning('Updating DB. The default behaviour without --perform is subject to change.');
            }

            foreach ($classes as $folderId => $codes) {
                try {
                    $currentFolder = $folderMapper->findById($folderId);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    $codeString = implode(',', $codes);
                    $log->warning("Failed to find folder with id: {$folderId}. Skipping adding users from '{$codeString}' to folder id {$folderId}");
                    continue;
                }
                $folders[$folderId] = $currentFolder;

                if ($loadStudents) {
                    // Remove all students from specified classes
                    $students = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::STUDENT);
                    $log->notice("Removing student access from '{$folders[$folderId]->getTitle()}'");

                    if (!$dryRun) {
                        foreach ($students as $student) {
                            $userAccessMapper->deleteByColumnList([
                                'uid' => $student->getId(),
                                'sid' => $folderId
                            ]);
                        }
                    }
                }

                // Remove all parents from specified classes
                $parents = $userAccessMapper->findUsersInCategory($folders[$folderId], Role::PARENT);
                $log->notice("Removing parent access from '{$folders[$folderId]->getTitle()}'");

                if (!$dryRun) {
                    foreach ($parents as $parent) {
                        $userAccessMapper->deleteByColumnList([
                            'uid' => $parent->getId(),
                            'sid' => $folderId
                        ]);
                    }
                }

                // Add parents from class codes to the specified classes
                foreach ($codes as $code) {
                    if (empty($code)) {
                        continue;
                    }
                    $log->notice("Adding parents of '{$code}' students to '{$folders[$folderId]->getTitle()}'");

                    $parents = $this->getParents($code);

                    if ($loadStudents) {
                        $students = $classHandler->getStudents($code);

                        if (is_array($students)) {
                            foreach ($students as $student) {
                                $this->loadUserAccess($userMapper, $userAccessMapper, $student['synergy_id'], Role::STUDENT, $folderId, $dryRun);
                            }
                        }
                    }

                    if (is_array($parents)) {
                        foreach ($parents as $parent) {
                            $this->loadUserAccess($userMapper, $userAccessMapper, $parent['StudentContactID'], Role::PARENT, $folderId, $dryRun);
                            $this->loadUserAccess($userMapper, $userAccessMapper, $parent['StudentContactSpouseID'], Role::PARENT, $folderId, $dryRun);
                        }
                    }
                }
            }

            // Reload cache (if not dry run)
            if (!$dryRun) {
                /** @var WritableCache $cache */
                $this->getDi()->values['logger'] = $log;
                $cache = $this->getDi()->get('subscriptionCache');

                try {
                    $cache->cacheAllUsers(INSTANCE_ID);
                } catch (UnderflowException $e) {
                    $log->notice($e->getMessage(), ['exception' => $e]);
                }
            }

        }

        /**
         * Retrieve all parents of students in the specified subject
         * @param $subCode
         * @return array|bool
         */
        function getParents($subCode)
        {
            $class = new \External_Class();
            $externalUser = new \External_User();

            $students = $class->getStudents($subCode);

            $contacts = [];

            if (is_array($students)) {
                foreach ($students as $student) {
                    $contactsCurrent = $externalUser->getStudentContactList($student['synergy_id']);
                    foreach ($contactsCurrent as $contactCurrent) {
                        $contacts[$contactCurrent['StudentContactID']] = $contactCurrent;
                    }
                }
            }

            return $contacts;
        }

        /**
         * @param UserMapper $userMapper
         * @param UserAccessMapper $userAccessMapper
         * @param int $externalId
         * @param string $role
         * @param int $folderId
         * @param bool $dryRun
         * @throws PDOException
         */
        function loadUserAccess($userMapper, $userAccessMapper, $externalId, $role, $folderId, $dryRun)
        {
            $log = $this->getLogger();
            // This would typically happen for a non-existent spouse, just do nothing in this case

            if (empty($externalId)) {
                return;
            }

            try {
                // Get primary contact

                $user = $userMapper->findByExternalId($externalId, $role);

                if (!isset($usersAdded[$user->getId()])) {
                    // Give student access to the class

                    if (!$dryRun) {
                        $userAccess = $userAccessMapper->createModel();
                        $userAccess->setUser($user);
                        $userAccess->setServiceIdentifier($folderId);
                        $userAccess->setService(UserAccessModel::SERVICE_HOMEPAGE);
                        $userAccess->setAccessLevel(UserAccessModel::ACCESS_READ);
                        $userAccessMapper->save($userAccess);
                    }

                    $log->notice(" Added:    {$role} '{$user->getFirstname()} {$user->getLastname()}'");
                    $usersAdded[$user->getId()] = true;
                } else {
                    $log->notice(" Skipping: {$role} '{$user->getFirstname()} {$user->getLastname()}' (Already Added To Folder)");
                }
            } catch (MapperException $e) {
                $log->error(" Error:    External {$role} user ID not found {$externalId}");
            } catch (UnexpectedValueException $e) {
                $log->error(" Error:    {$e->getMessage()}");
            }
        }

    }
);
$kernel->runApp();
