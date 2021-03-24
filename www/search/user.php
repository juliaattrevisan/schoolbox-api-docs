<?php

use Alaress\Color\ColorFactory;
use Schoolbox\Collection;
use Alaress\Mail\AbstractMailer;
use Schoolbox\Config\Loader as ConfigLoader;
use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access as Access;
use Schoolbox\Permission\Access\UserAccess;
use Schoolbox\ProfessionalDevelopment\Mapper as ProfessionalDevelopmentMapper;
use Schoolbox\Profile\Button\Mapper as ProfileButtonMapper;
use Schoolbox\Resource\Folder\Code\SubjectCodeMatchFactory;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Resource\Folder\Model as Folder;
use Schoolbox\Resource\Folder\Code\Model as FolderCode;
use Schoolbox\UserMapper;
use Schoolbox\Role;
use Schoolbox\User as SchoolboxUser;
use Schoolbox\Profile\ValidDetailsVisible;
use Schoolbox\Profile\ValidEmailVisible;

/**
 * @param SitePage $page
 * @param array $user
 * @param External_User $synUserHandler
 * @param UserMapper $userMapper
 * @param Eportfolio_Authenticator $authenticator
 * @param IConfigLoader $config
 *
 * @throws \Twig\Error\LoaderError
 * @throws \Twig\Error\RuntimeError
 * @throws \Twig\Error\SyntaxError
 */
function renderParent(
    SitePage $page,
    $user,
    External_User $synUserHandler,
    UserMapper $userMapper,
    Eportfolio_Authenticator $authenticator,
    IConfigLoader $config
) {
    $externalUser = $user['synergy_id'] ? $synUserHandler->getParent($user['synergy_id']) : [];
    $students = new Collection();

    if ($user['synergy_id']) {
        $enrolments = $synUserHandler->getEnrolledStudents($user['synergy_id']);
        foreach ($enrolments as $child) {
            try {
                $students->add($userMapper->findByExternalId($child['RelatedID']));
            } catch (Exception $e) {
                // noop
            }
        }
    }

    /** @var SchoolboxUser $sessionUser */
    $sessionUser = $_SESSION["user"];

    if (isset($externalUser['SpouseID'])) {
        try {
            $spouse = $userMapper->findByExternalId($externalUser['SpouseID'], Role::PARENT);
        } catch (MapperException $e) {
            $spouse = null;
        }
    } else {
        $spouse = null;
    }

    try {
        $user    = $userMapper->findById($user['uid']);
        $customButtons = ProfileButtonMapper::getInstance()->findButtonsForUser($user, $sessionUser);
    } catch (MapperException $e) {
        $customButtons = [];
    }
    $showDetails = (new ValidDetailsVisible($synUserHandler, $config))->isSatisfiedBy($user, $sessionUser);

    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/parent.twig',
            [
                'session'        => $_SESSION,
                'user'           => $user,
                'externalUser'   => $externalUser,
                'spouse'         => $spouse,
                'students'       => $students,
                'customButtons'  => $customButtons,
                'eportfolioAuth' => $authenticator->canView(),
                'now'            => Alaress_Date_Format::now(),
                'showDetails'    => $showDetails,
            ]
        )
    );
}

/**
 * @param SitePage $page
 * @param array $user
 * @param External_User $synUserHandler
 * @param UserMapper $userMapper
 * @param Eportfolio_Authenticator $authenticator
 * @param IConfigLoader $config
 *
 * @throws Twig\Error\LoaderError
 * @throws Twig\Error\RuntimeError
 * @throws Twig\Error\SyntaxError
 */
function renderStaff(
    SitePage $page,
    $user,
    External_User $synUserHandler,
    UserMapper $userMapper,
    Eportfolio_Authenticator $authenticator,
    IConfigLoader $config
) {
    $externalUser = $user['synergy_id'] ? $synUserHandler->getStaff($user['synergy_id']) : [];
    /** @var SchoolboxUser $sessionUser */
    $sessionUser = $_SESSION["user"];

    try {
        $user    = $userMapper->findById($user['uid']);
        $customButtons = ProfileButtonMapper::getInstance()->findButtonsForUser($user, $sessionUser);
    } catch (MapperException $e) {
        $customButtons = [];
    }

    $detailsVisible = new ValidDetailsVisible($synUserHandler, $config);

    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/staff.twig',
            [
                'session'        => $_SESSION,
                'user'           => $user,
                'externalUser'   => $externalUser,
                'eportfolioAuth' => $authenticator->canView(),
                'customButtons'  => $customButtons,
                'now'            => Alaress_Date_Format::now(),
                'showDetails'    => $detailsVisible->isSatisfiedBy($user, $sessionUser),
            ]
        )
    );
}

/**
 * @param SitePage $page
 * @param array $user
 * @param Group $groupHandler
 * @param External_User $synUserHandler
 * @param External_Class $classHandler
 * @param UserMapper $userMapper
 * @param FolderMapper $folderMapper
 * @param Eportfolio_Authenticator $authenticator
 * @param ProfessionalDevelopmentMapper $pdMapper
 * @param IConfigLoader $config
 *
 * @throws MapperException
 * @throws MapperException\Deleted
 * @throws Twig\Error\LoaderError
 * @throws Twig\Error\RuntimeError
 * @throws Twig\Error\SyntaxError
 */
function renderStudent(
    SitePage $page,
    $user,
    Group $groupHandler,
    External_User $synUserHandler,
    External_Class $classHandler,
    UserMapper $userMapper,
    FolderMapper $folderMapper,
    Eportfolio_Authenticator $authenticator,
    ProfessionalDevelopmentMapper $pdMapper,
    IConfigLoader $config
) {
    $externalUser = [];
    if ($user['synergy_id']) {
        $externalUser = $synUserHandler->getStudent($user['synergy_id']);
        if (is_array($externalUser['AlertMessage'])) {
            $externalUser['AlertMessages'] = $externalUser['AlertMessage'];
        } else {
            $externalUser['AlertMessages'] = empty(trim($externalUser['AlertMessage'])) ? [] : [$externalUser['AlertMessage']];
        }
        if (strlen(trim($externalUser["CourtOrderType"])) !== 0) {
            $externalUser['AlertMessages'][] = "Court Order - " . trim($externalUser["CourtOrderType"]);
        }
    }

    /** @var SchoolboxUser $sessionUser */
    $sessionUser = $_SESSION["user"];

    $student = $userMapper->findById($user['uid']);


    // Wrangle default colors
    $colors              = Timetable_ColourMapper::getInstance()->findAll()->reindex();
    $colorCount          = count($colors);
    $colorFactory        = new ColorFactory();
    $subjectColorManager = new Timetable_ColourManager($colors);
    $groupColorManager   = new Timetable_ColourManager($colors);


    // Load the list of subjects, and their teacher.
    // Then group by subject type?
    $subjects      = [];
    $splitSubjects = $config->get('mysubjects_split');
    $teacherRoles  = $groupHandler->getStaffGroupTypeIds();
    $codeMatcher   = (new SubjectCodeMatchFactory())->build($config->get('external_type'));
    $subjectList   = $synUserHandler->getSubjectList($student);
    foreach ($subjectList as $subject) {
        /* Decide whether the currently logged in user can see a link
         * to this subject's folder; link availability is dependent
         * on the folder existing and the current user having >= READ
         * permission on that folder
         *
         * Mindfully deconflict any array keys that might have come out of
         * the external connector's array of data
         */
        $subject['linkedToFolder'] = false;

        $group = $splitSubjects && $subject['classType'] ? $subject['classType'] : 'Classes';
        // Load a bunch of category and teacher info too
        $folder = $folderMapper->findForCampusAndClass($subject['campusCode'] ?? '', $subject['code']);
        if ($folder) {
            $subject['folder'] = $folder;
            $subject['perms']  = $groupHandler->getPermission($folder->getId());

            /* At this point we know the folder exists
             * if the user has >= READ access, then they can view the link to the folder
             * With more elbow grease this becomes unit testable
             */
            $subject['linkedToFolder'] = $subject['perms'] >= UserAccess::ACCESS_READ;

            // If config flag enabled, only fetch teacher(s) for folder's codes which map to an enrolled subject
            $taughtFolderCodes = $folder->getCodeString();
            if ($config->get('student_teacher_class_visibility')) {
                $folderCodesRetained = [];
                foreach (explode(',', $taughtFolderCodes) as $folderCode) {
                    foreach ($subjectList as $enrolledSubject) {
                        if ($codeMatcher->isMatch($folderCode, $enrolledSubject['campusCode'], $enrolledSubject['code'])) {
                            $folderCodesRetained[] = $folderCode;
                            break;
                        }
                    }
                }
                $taughtFolderCodes = implode(',', $folderCodesRetained);
            }
        } elseif ($config->get('show_all_subjects')) {
            $subject['folder'] = (new Folder())
                ->setName($subject['name'])
                ->setCodes(FolderCode::createCollection())
                ->addCode((new FolderCode())->setCode($codeMatcher->generateFolderCode($subject['campusCode'], $subject['code'])));
            $subject['perms']  = UserAccess::ACCESS_DENY;
            $taughtFolderCodes = $subject['folder']->getCodeString();
        } else {
            continue;
        }

        // Apply a color if there is none
        if ($colorCount > 0 && !$subject['folder']->getColour()) {
            $subject['folder']->setColour(
                $colorFactory->build(
                    $subjectColorManager->getColour($subject['folder']->getCodeString())
                )
            );
        }

        $teacherCodes = $classHandler->getTeachers($taughtFolderCodes);
        if (count($teacherCodes) > 0) {
            $subject['teachers'] = $userMapper->findByColumnList([
                'synergy_id' => $teacherCodes,
                'group_id'   => $teacherRoles,
            ]);
        } else {
            $subject['teachers'] = [];
        }

        $subjects[$group][] = $subject;
    }

    // Load the list of groups, and their owner and folder.
    $groups         = $groupHandler->involvedInCategoryList($user['uid'], false, UserAccess::ACCESS_READ, true);
    $groupFolderIds = array_column($groups, 'id');
    $groupFolders   = $folderMapper->findByColumn('id', $groupFolderIds)->indexBy('id');
    foreach ($groups as &$group) {
        $group['perms']  = $groupHandler->getPermission($group['id']);
        $group['folder'] = $groupFolders[$group['id']] ?? null;

        /* Decide whether the currently logged in user can see a link
         * to this group's folder; link availability is dependent
         * the current user having >= READ permission on that folder
         *
         * Mindfully deconflict any array keys that might have come out of
         * the external connector's array of data
         */
        $group['linkedToFolder'] = $group['perms'] >= UserAccess::ACCESS_READ;

        // Apply a color if there is none
        if ($colorCount > 0 && !$group['folder']->getColour()) {
            $group['folder']->setColour(
                $colorFactory->build(
                    $groupColorManager->getColour($group['folder']->getId())
                )
            );
        }

        $owners = $groupHandler->getOwner($group['id']);
        if (is_array($owners)) {
            foreach ($owners as $owner) {
                try {
                    $group['owners'][] = $userMapper->findById($owner['uid']);
                } catch (MapperException $e) {
                    // This user no longer exists. Not populating.
                }
            }
        }
    }
    unset($group);

    // Load users for form teacher, tutor, etc.
    $formTeacher = null;
    if ($externalUser['StudentFormStaffName']) {
        if ($externalUser['TeacherFormID']) {
            try {
                $formTeacher = $userMapper->findByExternalId($externalUser['TeacherFormID'], Role::STAFF);
            } catch (Exception $e) {
                $formTeacher = new SchoolboxUser();
                $formTeacher->setLastname($externalUser['StudentFormStaffName']);
            }
        } else {
            $formTeacher = new SchoolboxUser();
            $formTeacher->setLastname($externalUser['StudentFormStaffName']);
        }
    }

    /* Display their name if you couldn't find them in the db
     * via their id (some sis don't have the id)
     */
    $tutorTeacher = null;
    if ($externalUser['StudentTutorName']) {
        if ($externalUser['TeacherTutorID']) {
            try {
                $tutorTeacher = $userMapper->findByExternalId($externalUser['TeacherTutorID'], Role::STAFF);
            } catch (Exception $e) {
                $tutorTeacher = new SchoolboxUser();
                $tutorTeacher->setLastname($externalUser['StudentTutorName']);
            }
        } else {
            $tutorTeacher = new SchoolboxUser();
            $tutorTeacher->setLastname($externalUser['StudentTutorName']);
        }
    }

    $hohTeacher = null;
    if ($externalUser['StudentHeadOfHouse']) {
        if ($externalUser['HeadOfHouseID']) {
            try {
                $hohTeacher = $userMapper->findByExternalId($externalUser['HeadOfHouseID'], Role::STAFF);
            } catch (Exception $e) {
                $hohTeacher = new SchoolboxUser();
                $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
            }
        } else {
            $hohTeacher = new SchoolboxUser();
            $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
        }
    }

    /* Display their name if you couldn't find them in the db
     * via their id (some sis don't have the id)
     */
    $coordTeacher = null;
    if ($externalUser['StudentYearLevelCoordinator']) {
        if ($externalUser['YearLevelCoordID']) {
            try {
                $coordTeacher = $userMapper->findByExternalId($externalUser['YearLevelCoordID'], Role::STAFF);
            } catch (Exception $e) {
                $coordTeacher = new SchoolboxUser();
                $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
            }
        } else {
            $coordTeacher = new SchoolboxUser();
            $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
        }
    }

    // Get coach
    $studentCoach = null;
    if ($externalUser['StudentCoach']) {
        if ($externalUser['CoachID']) {
            try {
                $studentCoach = $userMapper->findByExternalId($externalUser['CoachID']);
            } catch (Exception $e) {
                $studentCoach = new SchoolboxUser();
                $studentCoach->setLastname($externalUser['StudentCoach']);
            }
        } else {
            $studentCoach = new SchoolboxUser();
            $studentCoach->setLastname($externalUser['StudentCoach']);
        }
    }

    // Load contact's user IDs
    $contacts    = [];
    if ($user['synergy_id']) {
        $contactList = $synUserHandler->getStudentContactList([$user['synergy_id']]);
        foreach ($contactList as $contact) {
            $main = null;
            try {
                $main = $userMapper->findByExternalId($contact['StudentContactID'], Role::PARENT);
            } catch (Exception $e) {
                // noop
            }
            $spouse = null;
            try {
                $spouse = $userMapper->findByExternalId($contact['StudentContactSpouseID'], Role::PARENT);
            } catch (Exception $e) {
                // noop
            }

            // Only show the parent contacts to the parent themselves, hide divorced parents from each other
            if (
                $sessionUser->getRole()->isStaff() || ($sessionUser->getRole()->isParent() && (
                        $contact['StudentContactID'] == $sessionUser->getExternalId() ||
                        $contact['StudentContactSpouseID'] == $sessionUser->getExternalId())
                )
            ) {
                $contact['main']   = $main;
                $contact['spouse'] = $spouse;
                $contacts[]        = $contact;
            }
        }
    }

    // Determine whether current user is student's parent, and if not if they
    // are related in some other way.

    $student->loadParents();
    $isParentOfStudent  = $sessionUser->isParentOf($student);
    $isPrimaryCarer     = false;
    if ($isParentOfStudent && isset($contactList)) {
        // Only the primary parent will see emergency contact information
        if ($contactList[0]['StudentContactID'] == $sessionUser->getExternalId() || $contactList[0]['StudentContactSpouseID'] == $sessionUser->getExternalId()) {
            $isPrimaryCarer = true;
        }
    }

    $page->setFile(TEMPLATE_content, 'output.html');

    $showPastoral = $config->get('pastoral_enabled');

    if ($sessionUser->getRole()->isParent() && !$config->get('pastoral_parent_access')) {
        $showPastoral = false;
    }

    $protectStudentContacts = $config->get('protect_student_contacts');

    $hideContacts = $student->getHideContacts() ? 1 : 0;

    switch ($protectStudentContacts) {
        case Access::VISIBILITY_EVERYONE: // Everyone
            break;
        case Access::VISIBILITY_STAFF: // Staff Only
            if (!$sessionUser->getRole()->isStaff()) {
                $hideContacts = true;
            }
            break;
        case Access::VISIBILITY_STAFF_PARENT: // Staff & Parents Only
            if (!$sessionUser->getRole()->isStaff() && !$sessionUser->getRole()->isParent()) {
                $hideContacts = true;
            }
            break;
    }

    $emails = [];

    foreach ($subjects as $subject) {
        foreach ($subject as $class) {
            /** @var SchoolboxUser $teacher */
            foreach ($class["teachers"] as $teacher) {
                $emails[] = $teacher->getFormattedEmailDisplay();
            }
        }
    }

    $teacherEmails = array_filter([$formTeacher, $tutorTeacher, $hohTeacher, $coordTeacher, $studentCoach]);

    foreach ($teacherEmails as $object) {
        if ($object instanceof Schoolbox\User) {
            $emails[] = $object->getFormattedEmailDisplay();
        }
    }

    $allTeacherEmails = implode(AbstractMailer::implodeGlue(), array_filter(array_unique($emails)));

    $pd = null;
    if ($config->get('site_location') === 'help') {
        /* Load Professional Development details if they exist
         */
        try {
            $pd = $pdMapper->findOneByColumn('user_id', $user['uid']);
        } catch (MapperException $e) {
            // noop
        }
    }

    $user    = $userMapper->findById($user['uid']);
    $customButtons = ProfileButtonMapper::getInstance()->findButtonsForUser($user, $sessionUser);
    $showDetails = (new ValidDetailsVisible($synUserHandler, $config))->isSatisfiedBy($user, $sessionUser);
    $showEmails = (new ValidEmailVisible($synUserHandler, $config))->isSatisfiedBy($user, $sessionUser);

    // Get the Verso school's Travel Log data
    $travelLogRenderer = new Custom\Verso\TravelLog\Renderer(
        $config->get('site_school_code'),
        $config->get('verso_client_id'),
        $config->get('verso_client_secret'),
        $config->get('travelLog_api_caching')
    );

    // Get the data from Verso API.
    $travelLogs = $travelLogRenderer->render($page->getTwig(), $user->getExternalId(), true);

    /* For a student's classes tiles to be visible, they must pass the sis
     * checks determined by $showDetails and their timetable must be visible
     * via the Hide Timetable checkbox in their Modify User screen
     */
    $showClasses = $showDetails && !$student->getHideTimetable();

    /* Decide whether the current user can comment on this student's learning moments
     *
     * Staff and student can always comment
     * Parents can comment if the config allows
     * Everyone else cannot
     */
    $learningMomentCanComment = ($sessionUser->getRole()->isStaff() || $sessionUser->getRole()->isStudent() || ($sessionUser->getRole()->isParent() && $config->get('evidence_parent_comment')));

    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/student.twig',
            [
                'session'                  => $_SESSION,
                'user'                     => $student,
                'externalUser'             => $externalUser,
                'showPastoral'             => $showPastoral,
                'eportfolioAuth'           => $authenticator->canView(),
                'hideContacts'             => $hideContacts,
                'showClasses'              => $showClasses,
                'now'                      => Alaress_Date_Format::now(),
                'boarderType'              => $synUserHandler->determineBoarderType($externalUser['StudentBoarder']),
                'contacts'                 => $contacts,
                'subjects'                 => $subjects,
                'customButtons'            => $customButtons,
                'groups'                   => $groups,
                'formTeacher'              => $formTeacher,
                'tutorTeacher'             => $tutorTeacher,
                'hohTeacher'               => $hohTeacher,
                'coordTeacher'             => $coordTeacher,
                'studentCoach'             => $studentCoach,
                'isParentOfStudent'        => $isParentOfStudent,
                'isPrimaryCarer'           => $isPrimaryCarer,
                'allTeacherEmails'         => $allTeacherEmails,
                'pdStates'                 => $pdMapper->getStates(),
                'pd'                       => $pd,
                'emailSeparator'           => AbstractMailer::implodeGlue(),
                'showDetails'              => $showDetails,
                'showEmail'                => $showEmails,
                'travelLogs'               => $travelLogs,
                'learningMomentCanComment' => $learningMomentCanComment
            ]
        )
    );
}

/** @var SitePage $this */
if (is_numeric($this->arg[0])) {
    $this->setTitle('Address Book');

    $userHandler  = new User();
    $groupHandler = new Group();
    $config       = ConfigLoader::getInstance();

    $user = $userHandler->getByID($this->arg[0]);

    if (!is_numeric($user['uid'])) {
        error(31);
    }

    $synUserHandler = new External_User();
    if ($groupHandler->isParent($user['uid'])) {
        renderParent(
            $this,
            $user,
            $synUserHandler,
            UserMapper::getInstance(),
            new Eportfolio_Authenticator($_SESSION['user'], $user['uid'], 'profile'),
            $config
        );
    } elseif ($groupHandler->isStudent($user['uid'])) {
        renderStudent(
            $this,
            $user,
            $groupHandler,
            $synUserHandler,
            new External_Class(),
            UserMapper::getInstance(),
            FolderMapper::getInstance(),
            new Eportfolio_Authenticator($_SESSION['user'], $user['uid'], 'profile'),
            ProfessionalDevelopmentMapper::getInstance(),
            $config
        );
    } elseif ($groupHandler->isStaff($user['uid'])) {
        renderStaff(
            $this,
            $user,
            $synUserHandler,
            UserMapper::getInstance(),
            new Eportfolio_Authenticator($_SESSION['user'], $user['uid'], 'profile'),
            $config
        );
    } else {
        $this->pageNotFound();
    }
} else {
    error(2);
}
