<?php
/**
 * Controller for pastoral care
 *
 * @author    Damian Sloane <damian.sloane@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 *
 */


namespace Schoolbox\Pastoral\Pastoral;

use Alaress\Web\Controller\Base;
use Schoolbox\Resource\Attachment\Zone;

class Controller extends Base
{
    // Insert an attach zone into the template

    private function getAttachZone($attachments = null)
    {
        $zone = new Zone('attachment');
        $zone
            ->setThumbnailSize(64)
            ->setModalTitle('Select Image')
            ->setAttachmentsLabel('')
            ->setAttachPrompt('Upload Image')
            ->setMimeFilter('*/*')
            ->setReadOnly(false)
            ->setMulti(true);

        if ($attachments != null) {
            foreach ($attachments as $attachment) {
                $zone->attachFiles(array($attachment->getHash()));
            }
        }

        return $zone->getTwigContext();
    }

    // Calculate age of student

    private function calculateAge($timestamp = 0, $now = 0) {

        if ($now == 0)
            $now = time();

        $yearDiff   = date("Y", $now) - date("Y", $timestamp);
        $monthDiff  = date("m", $now) - date("m", $timestamp);
        $dayDiff    = date("d", $now) - date("d", $timestamp);

        if ($monthDiff < 0)
            $yearDiff--;
        elseif (($monthDiff == 0) && ($dayDiff < 0))
            $yearDiff--;

        $result = intval($yearDiff);

        return $result;
    }

    // Generate a pastoral report with the relevant filtering applied

    public function getReport()
    {
        // Get current users role

        $role = $_SESSION["user"]->getRole()->getType();

        // Kick them back to the kerb if they aren't allowed to view this page

        $moderators = \Schoolbox\UserMapper::getInstance()->findByColumn("pastoral_moderator", 1);

        $found = false;

        foreach ($moderators as $moderator) {
            if ($moderator->getId() == $_SESSION["user"]->getId()) {
                $found = true;
                break;
            }
        }

        if (!$found || $role != "staff") {

            // User is not a moderator/staff, kick them to the kerb

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Make sure all the criteria exist before we starting trying to use them, and if they don't get passed
        // across, just set blank defaults

        if (!isset($_GET["students"]))  $_GET["students"] = '';
        if (!isset($_GET["authors"]))   $_GET["authors"] = '';
        if (!isset($_GET["types"]))     $_GET["types"] = '';
        if (!isset($_GET["severity"]))  $_GET["severity"] = '';
        if (!isset($_GET["before"]))    $_GET["before"] = '';
        if (!isset($_GET["after"]))     $_GET["after"] = '';

        if ($_GET["students"] != "") {
            $students = explode(",", $_GET["students"]);
        } else {
            $students = array();
        }

        if ($_GET["authors"] != "") {
            $authors = explode(",", $_GET["authors"]);
        } else {
            $authors = array();
        }

        if ($_GET["types"] != "") {
            $types = explode(",", $_GET["types"]);
        } else {
            $types = array();
        }

        $before_date = $_GET["before"];
        $after_date = $_GET["after"];
        $severity = $_GET["severity"];

        // Get instances of mappers we are going to use

        $fileMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();
        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $severityMapper = \Schoolbox\Pastoral\Severity\Mapper::getInstance();

        // Start with all records and whittle them down

        $records = \Schoolbox\Pastoral\Pastoral\Mapper::getInstance()->findAll()->sortBy("postDate", "desc");

        // Keep an array of the records we are going to display

        $results = array();

        foreach ($records as $record) {

            // Check if we have a date filter

            if ($before_date != '' || $after_date != '')
            {
                // We have a date filter

                $postDate = $record->getPostDate()->getUnixTimeStamp();

                if ($before_date != '' && $after_date != '') {

                    // Posted date must be between both dates

                    $before = date_create_from_format('d/m/Y', $before_date)->getTimestamp();
                    $after = date_create_from_format('d/m/Y', $after_date)->getTimestamp();

                    if ($postDate > $before || $postDate < $after) {

                        // Failed date test, move to the next record
                        continue;
                    }

                } else if ($before_date != '') {

                    // Posted date must be before date

                    $before = date_create_from_format('d/m/Y', $before_date)->getTimestamp();

                    if ($postDate > $before) {

                        // Failed date test, move to the next record
                        continue;
                    }

                } else {

                    // Posted date must be after date

                    $after = date_create_from_format('d/m/Y', $after_date)->getTimestamp();

                    if ($postDate < $after) {

                        // Failed date test, move to the next record
                        continue;
                    }

                }
            }

            // Check if we are restricting to specific students

            if (count($students) > 0)
            {
                $found = false;

                foreach ($students as $student) {
                    if ($record->getStudentId() == $student) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {

                    // Required student not found, move to the next record
                    continue;
                }
            }

            // Check if we are restricting to specific authors

            if (count($authors) > 0) {

                $found = false;
                foreach ($authors as $author) {
                    if ($record->getOwner()->getId() == $author) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {

                    // Required author not found, move to the next record
                    continue;
                }
            }

            // Check record types

            if (count($types) > 0) {

                $found = false;
                foreach ($types as $type) {
                    if ($record->getTypeId() == $type) {
                        $found = true;
                        break;
                    }
                }

                if (!$found) {

                    // Required record type not found, move to the next record
                    continue;
                }
            }

            // Check severity

            if ($severity != '') {
                if ($record->getSeverityId() != $severity) {

                    // Required severity not found, move to the next record
                    continue;
                }
            }

            // Get the records tags

            $record->tags = \Schoolbox\Pastoral\Tags\Mapper::getInstance()->findByColumn("pastoral_id", $record->getId());

            // Check if we are filtering on tags

            if ($_GET["tags"] != "")
            {
                // Filter on the tags

                $tagsArray = explode(",", $_GET["tags"]);

                if ($record->tags->count() == 0) {
                    // There are no tags on this record and we are searching for a tag, so we must skip this one
                    continue;
                }

                // Iterate through the tags

                $skip = false;

                $found = false;

                foreach ($record->tags as $tag)
                {
                    foreach ($tagsArray as $tagCurrent) {
                        if (strtolower($tagCurrent) == strtolower($tag->getName())) {
                            $found = true;
                            break;
                        }
                    }
                }

                if (!$found) {
                    continue;
                }
            }

            // Hydrate the record

            $attachmentMapper = \Schoolbox\Pastoral\Attachment\Mapper::getInstance();
            $record->attachments = $attachmentMapper->findByPastoralId($record->getId());

            $record->student = \Schoolbox\UserMapper::getInstance()->findById($record->getStudentId());

            foreach ($record->attachments as $attachment) {

                // Get file icon using mime type

                $file = $fileMapper->findByHash($attachment->getHash());
                $attachment->setIcon($file->getMimetype()->getIconFile());
                $attachment->setFilename($file->getFilename());
            }

            // Get the audience list for each record

            $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();
            $accessRoles = $accessMapper->findByPastoralId($record->getId());

            $record->roles = [];
            $record->users = [];
            $record->access = false;

            $commentMapper = \Schoolbox\Pastoral\Comment\Mapper::getInstance();
            $record->comments = $commentMapper->findByPastoralId($record->getId());

            foreach ($accessRoles as $role) {
                if (is_numeric($role->getRoleId())) {
                    $roleMapper = \Schoolbox\RoleMapper::getInstance();
                    $record->roles[] = $roleMapper->findById($role->getRoleId());
                } else {
                    $record->users[] = \Schoolbox\UserMapper::getInstance()->findById($role->getUserId());
                    if ($role->getUserId() == $_SESSION["user"]->getId()) {
                        $record->access = true;
                    }
                }
            }

            // Work out if the current user should be seeing this record

            $found = false;

            foreach ($accessRoles as $role_current) {
                if ($_SESSION["user"]->getRole()->getId() == $role_current->getRoleId() ||
                    $_SESSION["user"]->getId() == $role_current->getUserId())
                {
                    $found = true;
                    break;
                }
            }

            // If the user is not in the audience for this record, and is not the owner
            // hide the record from the list

            if (!$found && $_SESSION["user"]->getId() != $record->getOwner()->getId()) {
                $record->hidden = true;
            } else {
                $record->hidden = false;
            }

            // Set the pastoral care record type, should be stuck in the SQL query

            $record->setTypeName($typeMapper->findById($record->getTypeId())->getName());
            $record->setSeverityName($severityMapper->findById($record->getSeverityId())->getName());

            $results[] = $record;
        }

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/report.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'results'           => $results
                ),
            )
        );
    }


    public function getDashboard()
    {
        // Get current users role. The list screen is restricted to parents and staff

        $role = $_SESSION["user"]->getRole()->getType();

        // Kick them back to the kerb if they shouldn't be looking at this page

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Make sure the user is a moderator also

        $moderators = \Schoolbox\UserMapper::getInstance()->findByColumn("pastoral_moderator", 1);

        $found = false;

        foreach ($moderators as $moderator) {
            if ($moderator->getId() == $_SESSION["user"]->getId()) {
                $found = true;
                break;
            }
        }

        if (!$found) {

            // User is not a moderator, kick them to the kerb

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Get list of pastoral types to allow filtering

        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $types = $typeMapper->findAll();

        // Get list of severities

        $severityMapper = \Schoolbox\Pastoral\Severity\Mapper::getInstance();
        $severities = $severityMapper->findAll()->sortBy('order', 'asc');

        $tagsAll = \Schoolbox\Pastoral\Tags\Mapper::getInstance()->findAll();

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/dashboard.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'moderators'        => $moderators,
                    'types'             => $types,
                    'severities'        => $severities,
                    'tagsAll'           => $tagsAll
                ),
            )
        );
    }

    public function getIndex($id)
    {
        // Get current users role. The list screen is restricted to parents and staff

        $role = $_SESSION["user"]->getRole()->getType();

        // Get the students parents

        $externalUserMapper = new \External_User();
        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findWithSubjects($id);
        $user->loadParents();

        // Kick them back to the kerb if they shouldn't be looking at this page

        if ($role != "staff" && !$_SESSION["user"]->isParentOf($user)) {

            // The logged in user is not staff/parent, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Hydrate the school house group

        $user->setSchoolHouse(\HouseMapper::getInstance()->findById($user->getSchoolHouse()->getId()));

        // Get the students pastoral records

        $pastoral = \Schoolbox\Pastoral\Pastoral\Mapper::getInstance();
        $records = $pastoral->findByStudentId($id);

        // Get file attachments and stick them on the pastoral care records

        $fileMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();
        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $tagsMapper = \Schoolbox\Pastoral\Tags\Mapper::getInstance();
        $severityMapper = \Schoolbox\Pastoral\Severity\Mapper::getInstance();

        foreach ($records as $record)
        {
            $attachmentMapper = \Schoolbox\Pastoral\Attachment\Mapper::getInstance();
            $record->attachments = $attachmentMapper->findByPastoralId($record->getId());

            foreach ($record->attachments as $attachment) {

                // Get file icon using mime type

                $file = $fileMapper->findByHash($attachment->getHash());
                $attachment->setIcon($file->getMimetype()->getIconFile());
                $attachment->setFilename($file->getFilename());
            }

            // Get the audience list for each record

            $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();
            $accessRoles = $accessMapper->findByPastoralId($record->getId());

            $commentMapper = \Schoolbox\Pastoral\Comment\Mapper::getInstance();
            $record->comments = $commentMapper->findByPastoralId($record->getId());

            $record->roles = [];
            $record->users = [];
            $record->access = false;

            foreach ($accessRoles as $role) {
                if (is_numeric($role->getRoleId())) {
                    $roleMapper = \Schoolbox\RoleMapper::getInstance();
                    $record->roles[] = $roleMapper->findById($role->getRoleId());
                } else {
                    $record->users[] = \Schoolbox\UserMapper::getInstance()->findById($role->getUserId());
                    if ($role->getUserId() == $_SESSION["user"]->getId()) {
                        $record->access = true;
                    }
                }
            }

            if ($_SESSION["user"]->getPastoralModerator()) {
                $record->access = true;
            }

            // Work out if the current user should be seeing this record

            $found = false;

            foreach ($accessRoles as $role_current) {
                if ($_SESSION["user"]->getRole()->getId() == $role_current->getRoleId() ||
                    $_SESSION["user"]->getId() == $role_current->getUserId())
                {
                    $found = true;
                    break;
                }
            }

            // If the user is not in the audience for this record, and is not the owner
            // hide the record from the list

            if (!$found && $_SESSION["user"]->getId() != $record->getOwner()->getId() && !$_SESSION["user"]->getPastoralModerator()) {
                $record->hidden = true;
            } else {
                $record->hidden = false;
            }

            // Set the pastoral care record type, should be stuck in the SQL query

            $record->setTypeName($typeMapper->findById($record->getTypeId())->getName());
            $record->setSeverityName($severityMapper->findById($record->getSeverityId())->getName());
            $record->tags = $tagsMapper->findByColumn("pastoral_id", $record->getId());
        }

        // Get the students contact details

        $contacts = [];
        $contactList = $externalUserMapper->getStudentContactList($user->getExternalId());
        $student = $externalUserMapper->getStudent($user->getExternalId());

        if (is_array($contactList)) {
            foreach ($contactList as $contact) {
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
                $contact['main'] = $main;
                $contact['spouse'] = $spouse;
                $contacts[] = $contact;
            }
        }

        // Work out the students age

        $student['age'] = $this->calculateAge($student['StudentBirthDate']);

        // Get list of pastoral types to allow filtering

        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $types = $typeMapper->findAll();

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/index.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'records'           => $records,
                    'user'              => $user,
                    'student'           => $student,
                    'contacts'          => $contacts,
                    'role'              => $_SESSION["user"]->getRole()->getType(),
                    'types'             => $types
                ),
            )
        );
    }

    public function getRecord($studentId, $id)
    {
        // Get the current users role

        $role = $_SESSION["user"]->getRole()->getType();

        // Get the students parents

        $userMapper = \Schoolbox\UserMapper::getInstance();
        $user = $userMapper->findWithSubjects($studentId);
        $user->loadParents();

        // Kick them back to the kerb if they shouldn't be looking at this page

        if ($role != "staff" && !$_SESSION["user"]->isParentOf($user) && !$_SESSION["user"]->getPastoralModerator()) {

            // The logged in user is not staff/parent, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Get the pastoral care record

        $pastoral = \Schoolbox\Pastoral\Pastoral\Mapper::getInstance();
        $record = $pastoral->findByIdWithOwner($id);

        // Get any attachments

        $attachmentMapper = \Schoolbox\Pastoral\Attachment\Mapper::getInstance();
        $attachments = $attachmentMapper->findByPastoralId($id);

        $fileMapper = \Schoolbox\Resource\Storage\Mapper::getInstance();

        // Work out the audience

        $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();
        $accessRoles = $accessMapper->findByPastoralId($record->getId());

        $record->roles = [];
        $record->users = [];

        // Work out if the current user should be seeing this record

        $found = false;

        foreach ($accessRoles as $role) {
            $roleMapper = \Schoolbox\RoleMapper::getInstance();
            if (is_numeric($role->getRoleId())) {
                $record->roles[] = $roleMapper->findById($role->getRoleId());
            } else if ($_SESSION["user"]->getId() == $role->getUserId()) {
                // The user had been granted specific access to the record
                $record->users[] = \Schoolbox\UserMapper::getInstance()->findById($role->getUserId());
                $found = true;
            }
        }


        if (!$found && !$_SESSION["user"]->getPastoralModerator()) {
            foreach ($record->roles as $role_current) {
                if ($_SESSION["user"]->getRole()->getId() == $role_current->getId()) {

                    // The user has been granted role based access to the record

                    $found = true;
                    break;
                }
            }
        }

        // Shouldn't be looking at this record because they're not in the audience, or the
        // comment is private and they aren't the owner. Back to the kerb...

        if (!$_SESSION["user"]->getPastoralModerator()) {
            if ((!$found && $_SESSION["user"]->getId() != $record->getOwner()->getId()) || ($record->getPrivate() && $_SESSION["user"]->getId() != $record->getOwner()->getId())) {
                $this->getResponse()->redirect->afterPost("/");
                return;
            }
        }

        // Get filename and mime icon

        foreach ($attachments as $attachment) {
            $file = $fileMapper->findByHash($attachment->getHash());
            $attachment->setIcon($file->getMimetype()->getIconFile());
            $attachment->setFilename($file->getFilename());
        }

        // Get the pastoral comment type

        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $record->setTypeName($typeMapper->findById($record->getTypeId())->getName());

        $severityMapper = \Schoolbox\Pastoral\Severity\Mapper::getInstance();
        $record->setSeverityName($severityMapper->findById($record->getSeverityId())->getName());

        $tagsMapper = \Schoolbox\Pastoral\Tags\Mapper::getInstance();
        $record->tags = $tagsMapper->findByColumn("pastoral_id", $record->getId());

        // Get any comments

        $commentMapper = \Schoolbox\Pastoral\Comment\Mapper::getInstance();
        $comments = $commentMapper->findByPastoralId($id);

        // Lets render this thing

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/record.twig',
                'data'     => array(
                    'session'         => $_SESSION,
                    'user'            => $userMapper->findById($studentId),
                    'id'              => $record->getId(),
                    'student_id'      => $record->getStudentId(),
                    'record'          => $record,
                    'comments'        => $comments,
                    'attachments'     => $attachments
                ),
            )
        );
    }

    public function getInsert($id)
    {
        // Only staff should be inserting comments

        $role = $_SESSION["user"]->getRole()->getType();

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Get list of comment types

        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $types = $typeMapper->findAll();

        // Get list of severities

        $severityMapper = \Schoolbox\Pastoral\Severity\Mapper::getInstance();
        $severities = $severityMapper->findAll()->sortBy('order', 'asc');

        // Get list of roles for audiencing

        $roleMapper = \Schoolbox\RoleMapper::getInstance();
        $roles = $roleMapper->findAll();

        // Get the student

        $userMapper = \Schoolbox\UserMapper::getInstance();

        // Render this thing...

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'user'            => $userMapper->findById($id),
                    'id'                => '',
                    'student_id'        => $id,
                    'record'            => null,
                    'types'             => $types,
                    'severities'        => $severities,
                    'roles'             => $roles,
                    'attachzone'        => $this->getAttachZone(),
                ),
            )
        );
    }

    public function getUpdate($studentId, $id)
    {
        // Get the record we are updating

        $pastoral = \Schoolbox\Pastoral\Pastoral\Mapper::getInstance();
        $record = $pastoral->findById($id);

        // Only the staff member that owns the record should be updating it

        $role = $_SESSION["user"]->getRole()->getType();

        if ($role != "staff" || $_SESSION["user"]->getId() != $record->getOwner()->getId()) {

            // The logged in user is not staff or is not the owner, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Get the student

        $userMapper = \Schoolbox\UserMapper::getInstance();

        // Get the attachments

        $attachmentMapper = \Schoolbox\Pastoral\Attachment\Mapper::getInstance();
        $attachments = $attachmentMapper->findByPastoralId($id);

        // Get the pastoral record types

        $typeMapper = \Schoolbox\Pastoral\Type\Mapper::getInstance();
        $types = $typeMapper->findAll();

        // Get list of severities

        $severityMapper = \Schoolbox\Pastoral\Severity\Mapper::getInstance();
        $severities = $severityMapper->findAll()->sortBy('order', 'asc');

        // Get all roles for audiencing checkboxes

        $roleMapper = \Schoolbox\RoleMapper::getInstance();
        $roles = $roleMapper->findAll();

        // Get list of people who current have access to this record

        $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();
        $accessRoles = $accessMapper->findByPastoralId($record->getId());

        // Get tags

        $tagsMapper = \Schoolbox\Pastoral\Tags\Mapper::getInstance();
        $tags = $tagsMapper->findByColumn("pastoral_id", $record->getId());

        $tagCsv = "";

        foreach ($tags as $tag) {
            if ($tagCsv != "") {
                $tagCsv .= ",";
            }

            $tagCsv .= $tag->getName();
        }

        $userPermissions = array();

        foreach ($accessRoles as $access) {
            if ($access->getUserId() != null) {
                $userPermissions[] = \Schoolbox\UserMapper::getInstance()->findById($access->getUserId());
            }
        }

        $tagsAll = $tagsMapper->findAll()->sortBy('name', 'asc');

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/modify.twig',
                'data'     => array(
                    'session'         => $_SESSION,
                    'user'            => $userMapper->findById($studentId),
                    'id'              => $record->getId(),
                    'student_id'      => $record->getStudentId(),
                    'record'          => $record,
                    'types'           => $types,
                    'severities'      => $severities,
                    'roles'           => $roles,
                    'rolesSelected'   => $accessRoles,
                    'userPermissions' => $userPermissions,
                    'tags'            => $tagCsv,
                    'tagsAll'         => $tagsAll,
                    'attachzone'      => $this->getAttachZone($attachments),
                ),
            )
        );
    }

    public function postCommentDelete()
    {
        $role = $_SESSION["user"]->getRole()->getType();

        // Only staff should be doing this

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        $commentMapper = \Schoolbox\Pastoral\Comment\Mapper::getInstance();
        $commentMapper->deleteByColumn("id", $_POST["id"]);
    }

    public function postComment()
    {
        $role = $_SESSION["user"]->getRole()->getType();

        // Only staff should be doing this

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        $postDate = date("Y-m-d H:i:s");

        $commentMapper = \Schoolbox\Pastoral\Comment\Mapper::getInstance();
        $comment = new \Schoolbox\Pastoral\Comment\Model();
        $comment->setPastoralId($_POST["pastoral_id"]);
        $comment->setBody($_POST["body"]);
        $comment->setOwner($_SESSION["user"]);
        $comment->setPostDate($postDate);
        $commentMapper->save($comment);

        // Go back to pastoral record

        $this->getResponse()->redirect->afterPost("/pastoral/{$_POST["student_id"]}/{$_POST["pastoral_id"]}");
    }

    public function postSave()
    {
        $role = $_SESSION["user"]->getRole()->getType();

        // Only staff should be doing this

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Insert or update a pastoral care record

        $pastoralMapper = \Schoolbox\Pastoral\Pastoral\Mapper::getInstance();
        $accessMapper = \Schoolbox\Pastoral\Access\Mapper::getInstance();
        $tagsMapper = \Schoolbox\Pastoral\Tags\Mapper::getInstance();

        // External interface

        $pastoralExternal = new \External_Pastoral();

        if (empty($_POST["id"])) {

            $students = array();
            $students[$_POST["student_id"]] = 1;

            // Also create on any other students specified

            if (is_array($_POST["dupliate_id"])) {
                foreach ($_POST["duplicate_id"] as $studentId) {
                    $students[$studentId] = 1;
                }
            }

            $postDate = date("Y-m-d H:i:s");

            foreach ($students as $student_id => $value) {

                // Inserting new pastoral record

                $record = new \Schoolbox\Pastoral\Pastoral\Model();
                $record->setStudentId($student_id);
                $record->setHeading($_POST["heading"]);
                $record->setTypeId($_POST["typeId"]);
                $record->setSeverityId($_POST["severityId"]);
                $record->setBody($_POST["body"]);
                $record->setPrivate($_POST["private"]);
                $record->setOwner($_SESSION["user"]);
                $record->setPostDate($postDate);
                $pastoralMapper->save($record);

                $pastoralExternal->createPastoral($record);

                // Save the authorised users

                if (isset($_POST["user_id"])) {
                    if (is_array($_POST["user_id"])) {
                        foreach ($_POST["user_id"] as $user_id) {
                            $access = new \Schoolbox\Pastoral\Access\Model();
                            $access->setPastoralId($record->getId());
                            $access->setUserId($user_id);
                            $accessMapper->save($access);
                        }
                    }
                }

                // Save the tags

                $tags = explode("," , $_POST["tags"]);

                foreach ($tags as $tagCurrent) {
                    if (trim($tagCurrent) != '') {
                        $tag = new \Schoolbox\Pastoral\Tags\Model();
                        $tag->setName($tagCurrent);
                        $tag->setPastoralId($record->getId());
                        $tagsMapper->save($tag);
                    }
                }

                // Save the audiences

                foreach ($_POST["role"] as $role_id => $checked) {
                    if ($checked) {
                        $access = new \Schoolbox\Pastoral\Access\Model();
                        $access->setPastoralId($record->getId());
                        $access->setRoleId($role_id);
                        $accessMapper->save($access);
                    }
                }

            }

        } else {

            // Modifying existing record

            $record = $pastoralMapper->findById($_POST["id"]);

            // Only the owner should be updating a record

            if ($_SESSION["user"]->getId() != $record->getOwner()->getId()) {

                // The logged in user is not the owner, get them outta here

                $this->getResponse()->redirect->afterPost("/");
                return;
            }

            $record->setHeading($_POST["heading"]);
            $record->setTypeId($_POST["typeId"]);
            $record->setSeverityId($_POST["severityId"]);
            $record->setBody($_POST["body"]);
            $record->setPrivate($_POST["private"]);
            $record->setOwner($_SESSION["user"]);
            $record->setModifiedDate(date("Y-m-d H:i:s"));
            $pastoralMapper->save($record);

            $pastoralExternal->updatePastoral($record);

            $accessRoles = $accessMapper->findByPastoralId($record->getId());

            // Delete old tags

            $tagsMapper->deleteByColumn("pastoral_id", $_POST["id"]);

            // Save the tags

            $tags = explode(",", $_POST["tags"]);

            foreach ($tags as $tagCurrent) {
                if (trim($tagCurrent) != '') {
                    $tag = new \Schoolbox\Pastoral\Tags\Model();
                    $tag->setName($tagCurrent);
                    $tag->setPastoralId($_POST["id"]);
                    $tagsMapper->save($tag);
                }
            }

            // Delete user permissions

            $deleteIds = explode(",", $_POST["deleteIds"]);

            foreach ($deleteIds as $user_id) {
                foreach ($accessRoles as $role) {
                    if ($role->getUserId() == $user_id) {
                        $accessMapper->delete($role);
                        break;
                    }
                }
            }

            // Update the authorised users

            if (isset($_POST["user_id"])) {
                if (is_array($_POST["user_id"])) {
                    foreach ($_POST["user_id"] as $user_id) {
                        foreach ($accessRoles as $role) {
                            if ($role->getUserId() == $user_id) {
                                if (!in_array($user_id, $deleteIds)) {
                                    $found = true;
                                }
                                break;
                            }
                        }

                        if (!$found) {
                            $access = new \Schoolbox\Pastoral\Access\Model();
                            $access->setPastoralId($record->getId());
                            $access->setUserId($user_id);
                            $accessMapper->save($access);
                        }
                    }
                }
            }

            // Update the role based audience

            foreach ($_POST["role"] as $role_id => $checked) {

                if ($checked) {

                    // Add this access if it doesn't already exist

                    $found = false;

                    foreach ($accessRoles as $role) {
                        if ($role->getRoleId() == $role_id) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $access = new \Schoolbox\Pastoral\Access\Model();
                        $access->setPastoralId($record->getId());
                        $access->setRoleId($role_id);
                        $accessMapper->save($access);
                    }
                } else {

                    // Delete this access if it exists because its no longer ticked

                    $found = false;

                    foreach ($accessRoles as $role) {
                        if ($role->getRoleId() == $role_id) {
                            $accessMapper->delete($role);
                            break;
                        }
                    }
                }
            }
        }

        // Delete attachments if required

        // Keep track of attachments that aren't deleted so we don't add them again

        $retainedAttachments = array();

        // Get all attachments associated with this record

        $attachmentMapper = \Schoolbox\Pastoral\Attachment\Mapper::getInstance();
        $attachments = $attachmentMapper->findByPastoralId($record->getId());

        foreach ($attachments as $attachment) {

            $foundAttachment = false;

            if (is_array($_POST["attachment"])) {
                foreach ($_POST["attachment"] as $hash) {

                    $hash_array = explode(":", $hash);

                    if ($attachment->getHash() == $hash_array[1]) {
                        $foundAttachment = true;
                        break;
                    }
                }
            }

            if (!$foundAttachment) {

                // The attachment was found in the database, remove it

                $attachment->setModifiedDate(date("Y-m-d H:i:s"));
                $attachmentMapper->delete($attachment);

            } else {

                // We are keeping this attachment

                $retainedAttachments[] = $attachment->getHash();
            }
        }

        // Save any new attachments

        $attachmentMapper = \Schoolbox\Pastoral\Attachment\Mapper::getInstance();

        if (is_array($_POST["attachment"])) {
            foreach ($_POST["attachment"] as $hash) {

                $hash_array = explode(":", $hash);

                // Only add attachments which don't already exist

                if (!in_array($hash_array[1], $retainedAttachments)) {
                    $attachment = new \Schoolbox\Pastoral\Attachment\Model();
                    $attachment->setPastoralId($record->getId());
                    $attachment->setHash($hash_array[1]);
                    $attachment->setModifiedDate(date("Y-m-d H:i:s"));
                    $attachmentMapper->save($attachment);
                }
            }
        }

        // Send notifications

        if ($_POST["notify"] == 1) {

            $userMapper = \Schoolbox\UserMapper::getInstance();
            $user = $userMapper->findById($_POST["student_id"]);

            if (!$_POST["private"]) {
                $link = "/pastoral/{$_POST["student_id"]}/" . $record->getId();
            } else {
                $link = "/pastoral/{$_POST["student_id"]}";
            }

            if (empty($_POST["id"])) {
                $body = "A new pastoral alert has been entered for " . $user->getFullName() . ". Click here to view this alert.";
            } else {
                $body = "An existing pastoral alert has been updated for " . $user->getFullName() . ". Click here to view this alert.";
            }

            $bob = new \Alaress\Async\Notification\Instance\Builder();
            $bob->setTitle($body)
                ->setSeverity(\Monolog\Logger::INFO)
                ->setNamespace('ALERT')
                ->setHref($link)
                ->setIcon('/images/icon.php?icon=bolt&size=64')
                ->addTopic("notify.user." . $_POST["student_id"])
                ->putMeta('ttl', 60)
                ->putMeta('title', 'Pastoral Care Alert');

            $accessRoles = $accessMapper->findByPastoralId($record->getId());

            $ids = array();

            $userMapper = \Schoolbox\UserMapper::getInstance();
            $users = $userMapper->findAll();

            foreach ($_POST["role"] as $role_id => $checked) {

                // Find all users with this role

                foreach ($users as $user) {
                    if ($user->getRole()->getId() == $role_id) {

                        // Add them to the notification list

                        $ids[$user->getId()] = 1;
                    }
                }
            }

            // Dont send it to the user making the change

            $ids[$_SESSION["user"]->getId()] = 0;

            // Trigger the notifications

            foreach ($ids as $id => $value) {
                if ($value == 1) {
                    $n = new \Schoolbox\Notif\Notif("instant.user.{$id}.alert");
                    $n->log(\Monolog\Logger::INFO, json_encode($bob->build()->jsonSerialize()));
                }
            }

            // Send notifications to all individually added users

            foreach ($_POST["user_id"] as $user_id) {
                $n = new \Schoolbox\Notif\Notif("instant.user.$user_id.alert");
                $n->log(\Monolog\Logger::INFO, json_encode($bob->build()->jsonSerialize()));
            }
        }

        // Go back to students pastoral record

        $this->getResponse()->redirect->afterPost("/pastoral/{$_POST["student_id"]}");
    }

    public function postDelete()
    {
        // Get the record we are deleting

        $pastoralMapper = \Schoolbox\Pastoral\Pastoral\Mapper::getInstance();
        $record = $pastoralMapper->findById($_POST["id"]);

        // Work out if this person should be allowed to delete the record

        $role = $_SESSION["user"]->getRole()->getType();

        if (!$_SESSION["user"]->getPastoralModerator()) {
            if ($role != "staff" || $_SESSION["user"]->getId() != $record->getOwner()->getId()) {

                // The logged in user is not staff or is not the owner, send them back to the homepage

                $this->getResponse()->redirect->afterPost("/");
                return;
            }
        }

        \Schoolbox\Pastoral\Comment\Mapper::getInstance()->deleteByColumn("pastoral_id", $_POST["id"]);

        // Get ready for some external ninja'ing

        $pastoralExternal = new \External_Pastoral();

        // Delete on external database

        $pastoralExternal->deletePastoral($record);

        // Delete locally

        $pastoralMapper->delete($record);

        exit;
    }

}

/*
 * This code will self-destruct in 5 seconds...
 *
          ,--.!,
       __/   -*-
     ,d08b.  '|`
     0088MM
     `9MMP'
*/
