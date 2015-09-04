<?php

use Schoolbox\UserMapper;
use Schoolbox\Resource\Folder;

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
    try {
        $spouse = $userMapper->findByExternalId($externalUser['SpouseID']);
    } catch (\Schoolbox\Mapper\Exception $e) {
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
 * @param SitePage                 $page
 * @param array                    $user
 * @param External_User            $synUserHandler
 * @param UserMapper               $userMapper
 * @param Eportfolio_Authenticator $authenticator
 */
function renderStaff(
    SitePage $page,
    $user,
    External_User $synUserHandler,
    UserMapper $userMapper,
    Eportfolio_Authenticator $authenticator
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
 * @param SitePage                 $page
 * @param array                    $user
 * @param Group                    $groupHandler
 * @param External_User            $synUserHandler
 * @param External_Class           $classHandler
 * @param UserMapper               $userMapper
 * @param Folder\Mapper            $folderMapper
 * @param Eportfolio_Authenticator $authenticator
 */
function renderStudent(
    SitePage $page,
    $user,
    Group $groupHandler,
    External_User $synUserHandler,
    External_Class $classHandler,
    UserMapper $userMapper,
    Folder\Mapper $folderMapper,
    Eportfolio_Authenticator $authenticator
) {
    $externalUser = $synUserHandler->getStudent($user['synergy_id']);
    $externalUser['AlertMessages'] = preg_split(
        '/[\\n\\r]+/',
        $externalUser['AlertMessage'],
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    // Load the list of subjects, and their teacher.
    // Then group by subject type?
    $subjects      = [];
    $splitSubjects = \Config::get('mysubjects_split');
    $teacherRoles  = $groupHandler->getStaffGroupTypeIds();
    foreach ($synUserHandler->getSubjectList($user['synergy_id'], $user['uid']) as $subject) {
        $group = $splitSubjects ? $subject['classType'] : 'Subject';
        // Load a bunch of category and teacher info too
        try {
            $subject['folder'] = $folderMapper->findByCode($subject['code']);
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
            'synergy_id' => $classHandler->getTeacherName($subject['code']),
            'group_id'   => $teacherRoles,
        ]);
        $subjects[$group][] = $subject;
    }

    // Load the list of groups, and their owner.
    $groups = $groupHandler->involvedInCategoryList($user['uid'], false);
    foreach ($groups as &$group) {
        $owner = $groupHandler->getOwner($group['id']);
        if (is_array($owner)) {
            $group['owner'] = $userMapper->findById($owner['uid']);
        }
    }
    unset($group);

    // Load users for form teacher, tutor, etc.
    $formTeacher = null;
    if ($externalUser['StudentFormStaffName'] && $externalUser['TeacherFormID']) {
        try {
            $formTeacher = $userMapper->findByExternalId($externalUser['TeacherFormID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $formTeacher = new \Schoolbox\User();
            $formTeacher->setLastname($externalUser['StudentFormStaffName']);
        }
    }

    $tutorTeacher = null;
    if ($externalUser['StudentTutorName'] && $externalUser['TeacherTutorID']) {
        try {
            $tutorTeacher = $userMapper->findByExternalId($externalUser['TeacherTutorID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $tutorTeacher = new \Schoolbox\User();
            $tutorTeacher->setLastname($externalUser['StudentTutorName']);
        }
    }

    $hohTeacher = null;
    if ($externalUser['StudentHeadOfHouse'] && $externalUser['HeadOfHouseID']) {
        try {
            $hohTeacher = $userMapper->findByExternalId($externalUser['HeadOfHouseID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $hohTeacher = new \Schoolbox\User();
            $hohTeacher->setLastname($externalUser['StudentHeadOfHouse']);
        }
    }

    $coordTeacher = null;
    if ($externalUser['StudentYearLevelCoordinator'] && $externalUser['YearLevelCoordID']) {
        try {
            $coordTeacher = $userMapper->findByExternalId($externalUser['YearLevelCoordID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            $coordTeacher = new \Schoolbox\User();
            $coordTeacher->setLastname($externalUser['StudentYearLevelCoordinator']);
        }
    }

    // Load contact's user IDs
    $contacts = [];
    foreach ($synUserHandler->getStudentContactList($user['synergy_id']) as $contact) {
        $main = null;
        try {
            $main = $userMapper->findByExternalId($contact['StudentContactID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
        $spouse = null;
        try {
            $spouse = $userMapper->findByExternalId($contact['StudentContactSpouseID']);
        } catch (\Schoolbox\Mapper\Exception $e) {
            // noop
        }
        $contact['main']   = $main;
        $contact['spouse'] = $spouse;
        $contacts[]        = $contact;
    }

    // TwigLib
    // TODO make preg_replace available to Twig
    // TODO make session contents available to Twig by default, either through whole _SESSION or Aura
    // TODO make $now available to Twig by default
    $userObject = $userMapper->findById($user['uid']);
    $userObject->loadParents();
    $page->setFile(TEMPLATE_content, 'output.html');
    $page->setVar(
        'OUTPUT',
        $page->getTwig()->render(
            'user/student.twig',
            [
                'session'        => $_SESSION,
                'user'           => $userObject,
                'externalUser'   => $externalUser,
                'eportfolioAuth' => $authenticator->canView(),
                'now'            => Alaress_Date_Format::now(),
                'boarderType'    => $synUserHandler->determineBoarderType($externalUser['StudentBoarder']),
                'contacts'       => $contacts,
                'subjects'       => $subjects,
                'groups'         => $groups,
                'formTeacher'    => $formTeacher,
                'tutorTeacher'   => $tutorTeacher,
                'hohTeacher'     => $hohTeacher,
                'coordTeacher'   => $coordTeacher,
            ]
        )
    );
}

/** @var SitePage $this */
if (is_numeric($this->arg[0])) {
    $this->setTitle('Address Book');

    $userHandler  = new User();
    $groupHandler = new Group();

    $user = $userHandler->getByID($this->arg[0]);

    if (!is_numeric($user['uid'])) {
        error(31);
    }

    // advanced synergetic information
    if (Cfg::get('external_enable') && $user['synergy_id']) {
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
                new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile')
            );
        } elseif ($groupHandler->isStaff($user['uid'])) {
            renderStaff(
                $this,
                $user,
                $synUserHandler,
                new UserMapper(),
                new Eportfolio_Authenticator($_SESSION['user']->getId(), $user['uid'], 'profile')
            );
        } else {
            $this->pageNotFound();
        }
    } else {
        // Show something else (non-twig)
        // Probably a 404 screen
        $this->pageNotFound();
    }
} else {
    error(2);
}
