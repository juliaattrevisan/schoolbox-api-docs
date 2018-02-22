<?php

use Schoolbox\IConfigLoader;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Permission\Access\Access as Access;
use Schoolbox\ProfessionalDevelopment\Mapper as ProfessionalDevelopmentMapper;
use Schoolbox\Resource\Folder;
use Schoolbox\UserMapper;

//require_once 'include/permission.php';

//display user description page

/**
 * Parent rendering, extracted to a method.
 * @param SitePage                 $page
 * @param array                    $user
 * @param External_User            $synUserHandler
 * @param UserMapper               $userMapper
 * @param Eportfolio_Authenticator $authenticator
 */
function renderParent(
    SitePage $page,
    $user,
    External_User $synUserHandler,
    UserMapper $userMapper,
    Eportfolio_Authenticator $authenticator
) {

    $externalUser = $synUserHandler->getParent($user['synergy_id']);
    if(isset($externalUser['SpouseID'])) {
        try {
            $spouse = $userMapper->findByExternalId($externalUser['SpouseID'], Role::PARENT);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $spouse = null;
        }
    }
    else {
        $spouse = null;
    }

    // TwigLib
    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/parent.twig',
            [
                'session'         => $_SESSION,
                'user'            => $userMapper->findById($user['uid']),
                'externalUser'    => $externalUser,
                'spouse'          => $spouse,
                'eportfolioAuth'  => $authenticator->canView(),
                'now'             => Alaress_Date_Format::now(),
            ]
        )
    );
}

/**
 * Staff rendering, extracted to a method.
 *
 * @param SitePage                      $page
 * @param array                         $user
 * @param External_User                 $synUserHandler
 * @param UserMapper                    $userMapper
 * @param Eportfolio_Authenticator      $authenticator
 * @param IConfigLoader                 $config
 * @throws RuntimeException
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function renderStaff(
    SitePage $page,
    $user,
    External_User $synUserHandler,
    UserMapper $userMapper,
    Eportfolio_Authenticator $authenticator,
    IConfigLoader $config
) {
    $externalUser = $synUserHandler->getStaff($user['synergy_id']);

    // TwigLib
    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/staff.twig',
            [
                'session'         => $_SESSION,
                'user'            => $userMapper->findById($user['uid']),
                'externalUser'    => $externalUser,
                'eportfolioAuth'  => $authenticator->canView(),
                'now'             => Alaress_Date_Format::now(),
            ]
        )
    );
}

/**
 * Student rendering, extracted to a method.
 *
 * @param SitePage                      $page
 * @param array                         $user
 * @param Group                         $groupHandler
 * @param External_User                 $synUserHandler
 * @param External_Class                $classHandler
 * @param UserMapper                    $userMapper
 * @param Folder\Mapper                 $folderMapper
 * @param Eportfolio_Authenticator      $authenticator
 * @param ProfessionalDevelopmentMapper $pdMapper
 * @throws MapperException
 * @throws RuntimeException
 * @throws Twig_Error_Loader
 * @throws Twig_Error_Runtime
 * @throws Twig_Error_Syntax
 */
function renderStudent(
    SitePage $page,
    $user,
    Group $groupHandler,
    External_User $synUserHandler,
    External_Class $classHandler,
    UserMapper $userMapper,
    Folder\Mapper $folderMapper,
    Eportfolio_Authenticator $authenticator,
    ProfessionalDevelopmentMapper $pdMapper
) {
    $externalUser = $synUserHandler->getStudent($user['synergy_id']);
    $externalUser['AlertMessages'] = preg_split(
        '/[\\n\\r]+/',
        $externalUser['AlertMessage'],
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    /** @var Schoolbox\User $sessionUser */
    $sessionUser = $_SESSION["user"];

    $student = $userMapper->findById($user['uid']);

    // Load the list of subjects, and their teacher.
    // Then group by subject type?
    $subjects      = [];
    $splitSubjects = \Config::get('mysubjects_split');
    $teacherRoles  = $groupHandler->getStaffGroupTypeIds();
    foreach ($synUserHandler->getSubjectList($student) as $subject) {
        $group = $splitSubjects && $subject['classType'] ? $subject['classType'] : 'Classes';

        // Load a bunch of category and teacher info too
        try {
            $subject['folder'] = $folderMapper->findByCode($subject['code']);
            $subject['perms'] = $groupHandler->getPermission($subject['folder']->getId());

        } catch (\Exception $e) {
            if (\Config::get('show_all_subjects')) {
                $code = new Folder\Code\Model();
                $code->setCode($subject['code']);
                $subject['folder'] = new Folder\Model();
                $subject['folder']->setName($subject['name'])->addCode($code);
            } else {
                continue;
            }
        }

        $subject['teachers'] = $userMapper->findByColumnList([
            'synergy_id' => $classHandler->getTeacherName(
                $subject['folder']->getCodeString() ?: $subject['code']
            ),
            'group_id' => $teacherRoles,
        ]);

        $subjects[$group][] = $subject;
    }

    // Load the list of groups, and their owner.
    $groups = $groupHandler->involvedInCategoryList($user['uid'], false);
    foreach ($groups as &$group) {
        $group['perms'] = $groupHandler->getPermission($group['id']);

        $owner = $groupHandler->getOwner($group['id']);
        if (is_array($owner)) {
            try {
                $group['owner'] = $userMapper->findById($owner['uid']);
            } catch (MapperException $e) {
                $group['owner'] = $userMapper->getUnknownUser();
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
            } catch (\Schoolbox\Mapper\Exception $e) {
                $formTeacher = new \Schoolbox\User();
                $formTeacher->setLastname($externalUser['StudentFormStaffName']);
            }
        } else {
            $formTeacher = new \Schoolbox\User();
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
            } catch (\Schoolbox\Mapper\Exception $e) {
                $tutorTeacher = new \Schoolbox\User();
                $tutorTeacher->setLastname($externalUser['StudentTutorName']);
            }
        }
        else {
            $tutorTeacher = new \Schoolbox\User();
            $tutorTeacher->setLastname($externalUser['StudentTutorName']);
        }
    }

    $hohTeacher = null;
    if ($externalUser['StudentHeadOfHouse']) {
        if ($externalUser['HeadOfHouseID']) {
            try {
                $hohTeacher = $userMapper->findByExternalId($externalUser['HeadOfHouseID'], Role::STAFF);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $hohTeacher = new \Schoolbox\User();
                $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
            }
        } else {
            $hohTeacher = new \Schoolbox\User();
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
            } catch (\Schoolbox\Mapper\Exception $e) {
                $coordTeacher = new \Schoolbox\User();
                $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
            }
        } else {
            $coordTeacher = new \Schoolbox\User();
            $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
        }
    }

    // Get coach
    $studentCoach = null;
    if ($externalUser['StudentCoach']) {
        if ($externalUser['CoachID']) {
            try {
                $studentCoach = $userMapper->findByExternalId($externalUser['CoachID']);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $studentCoach = new \Schoolbox\User();
                $studentCoach->setLastname($externalUser['StudentCoach']);
            }
        } else {
            $studentCoach = new \Schoolbox\User();
            $studentCoach->setLastname($externalUser['StudentCoach']);
        }
    }

    // Load contact's user IDs
    $contacts = [];
    $contactList = $synUserHandler->getStudentContactList($user['synergy_id']);
    if(is_array($contactList)) {
        foreach ($contactList as $contact) {
            $main = null;
            try {
                $main = $userMapper->findByExternalId($contact['StudentContactID'], Role::PARENT);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop
            }
            $spouse = null;
            try {
                $spouse = $userMapper->findByExternalId($contact['StudentContactSpouseID'], Role::PARENT);
            } catch (\Schoolbox\Mapper\Exception $e) {
                // noop
            }

            // Only show the parent contacts to the parent themselves, hide divorced parents from each other
            if($sessionUser->getRole()->isStaff() || ( $sessionUser->getRole()->isParent() && (
                    $contact['StudentContactID'] == $sessionUser->getExternalId() ||
                    $contact['StudentContactSpouseID'] == $sessionUser->getExternalId())
                )
            ) {
                $contact['main'] = $main;
                $contact['spouse'] = $spouse;
                $contacts[] = $contact;
            }
        }
    }

    // Determine whether current user is student's parent, and if not if they
    // are related in some other way.

    $student->loadParents();
    $isParentOfStudent  = $sessionUser->isParentOf($student);
    $isRelatedToStudent = false;
    $isPrimaryCarer = false;
    if (!$isParentOfStudent) {
        $studentList = $synUserHandler->getEnrolledStudents($sessionUser->getExternalId());
        if(is_array($studentList)) {
            foreach ($studentList as $enrolledStudentId) {
                if ($enrolledStudentId['RelatedID'] == $student->getExternalId()) {
                    $isRelatedToStudent = true;
                    break;
                }
            }
        }
    } else {
        $isRelatedToStudent = true;
        // Only the primary parent will see emergency contact information
        if($contactList[0]['StudentContactID'] == $sessionUser->getExternalId() || $contactList[0]['StudentContactSpouseID'] == $sessionUser->getExternalId()) {
            $isPrimaryCarer = true;
        }
    }

    // TwigLib
    // TODO make preg_replace available to Twig
    // TODO make session contents available to Twig by default, either through whole _SESSION or Aura
    // TODO make $now available to Twig by default

    $page->setFile(TEMPLATE_content, 'output.html');

    $showPastoral = Config::get('pastoral_enabled');

    if ($sessionUser->getRole()->isParent() && !Config::get('pastoral_parent_access'))
        $showPastoral = false;

    $protectStudentContacts = \Config::get('protect_student_contacts');

    $hideContacts = $student->getHideContacts() ? 1 : 0;

    switch ($protectStudentContacts) {
        case Access::VISIBILITY_EVERYONE : // Everyone
            break;
        case Access::VISIBILITY_STAFF: // Staff Only
            if (!$sessionUser->getRole()->isStaff())
                $hideContacts = true;
            break;
        case Access::VISIBILITY_STAFF_PARENT: // Staff & Parents Only
            if (!$sessionUser->getRole()->isStaff() && !$sessionUser->getRole()->isParent())
                $hideContacts = true;
            break;
    }

    $emails = [];

    foreach ($subjects as $subject) {
        foreach ($subject as $class) {
            /** @var \Schoolbox\User $teacher */
            foreach ($class["teachers"] as $teacher) {
                $emails[] = $teacher->getEmail();
            }
        }
    }
    if ($formTeacher instanceof Schoolbox\User) {
        $emails[] = $formTeacher->getEmail();
    }
    if ($tutorTeacher instanceof Schoolbox\User) {
        $emails[] = $tutorTeacher->getEmail();
    }
    if ($hohTeacher instanceof Schoolbox\User) {
        $emails[] = $hohTeacher->getEmail();
    }
    if ($coordTeacher instanceof Schoolbox\User) {
        $emails[] = $coordTeacher->getEmail();
    }
    if ($studentCoach instanceof Schoolbox\User) {
        $emails[] = $studentCoach->getEmail();
    }

    $allTeacherEmails = implode(',', array_filter($emails));

    $pd = null;
    if (\Config::get('site_location') === 'help') {
        /* Load Professional Development details if they exist
         */
        try {
            $pd = $pdMapper->findOneByColumn('user_id', $user['uid']);
        } catch (MapperException $e) {
            // noop
        }
    }

    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/student.twig',
            [
                'session'            => $_SESSION,
                'user'               => $student,
                'externalUser'       => $externalUser,
                'showPastoral'       => $showPastoral,
                'eportfolioAuth'     => $authenticator->canView(),
                'hideContacts'       => $hideContacts,
                'showClasses'        => $student->getHideTimetable() ? 0 : 1,
                'now'                => Alaress_Date_Format::now(),
                'boarderType'        => $synUserHandler->determineBoarderType($externalUser['StudentBoarder']),
                'contacts'           => $contacts,
                'subjects'           => $subjects,
                'groups'             => $groups,
                'formTeacher'        => $formTeacher,
                'tutorTeacher'       => $tutorTeacher,
                'hohTeacher'         => $hohTeacher,
                'coordTeacher'       => $coordTeacher,
                'studentCoach'       => $studentCoach,
                'isParentOfStudent'  => $isParentOfStudent,
                'isRelatedToStudent' => $isRelatedToStudent,
                'isPrimaryCarer'     => $isPrimaryCarer,
                'allTeacherEmails'   => $allTeacherEmails,
                'pdStates'           => $pdMapper->getStates(),
                'pd'                 => $pd,
            ]
        )
    );
}

/** @var SitePage $this */
if (is_numeric($this->arg[0])) {
    $this->setTitle('Address Book');

    $userHandler  = new \User();
    $groupHandler = new Group();
    $config = new \Schoolbox\Config\Loader();

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
            new UserMapper(),
            new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile')
        );
    } elseif ($groupHandler->isStudent($user['uid'])) {
        renderStudent(
            $this,
            $user,
            $groupHandler,
            $synUserHandler,
            new External_Class(),
            new UserMapper(),
            new Folder\Mapper(),
            new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile'),
            ProfessionalDevelopmentMapper::getInstance()
        );
    } elseif ($groupHandler->isStaff($user['uid'])) {
        renderStaff(
            $this,
            $user,
            $synUserHandler,
            new UserMapper(),
            new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile'),
            $config
        );
    } else {
        $this->pageNotFound();
    }
} else {
    error(2);
}
