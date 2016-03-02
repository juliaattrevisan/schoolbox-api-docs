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
use Schoolbox\Collection;
use Schoolbox\Pastoral\Pastoral;
use Schoolbox\Pastoral\Pastoral\Mapper as PastoralMapper;
use Schoolbox\Pastoral\Pastoral\Model as PastoralModel;
use Schoolbox\Pastoral\Access\Mapper as PastoralAccessMapper;
use Schoolbox\Pastoral\Access\Model as PastoralAccessModel;
use Schoolbox\Pastoral\Attachment\Mapper as PastoralAttachmentMapper;
use Schoolbox\Pastoral\Attachment\Model as PastoralAttachmentModel;
use Schoolbox\Pastoral\Comment\Mapper as PastoralCommentMapper;
use Schoolbox\Pastoral\Comment\Model as PastoralCommentModel;
use Schoolbox\Pastoral\Type\Mapper as PastoralTypeMapper;
use Schoolbox\Pastoral\Severity\Mapper as PastoralSeverityMapper;
use Schoolbox\Pastoral\Tags\Mapper as PastoralTagsMapper;
use Schoolbox\Pastoral\Tags\Model as PastoralTagsModel;
use Schoolbox\RoleMapper;
use Schoolbox\UserMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
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

        if ($role != "staff") {

            // User is not staff, kick them to the kerb

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

        $students = $_GET["students"];
        $authors = $_GET["authors"];
        $types = $_GET["types"];
        $before_date = $_GET["before"];
        $after_date = $_GET["after"];
        $severity = $_GET["severity"];

        // Get matching records

        $records = PastoralMapper::getInstance()->findAllReport($before_date, $after_date, $severity, $types, $authors, $students);

        // Restrict results based on pagination

        if (!isset($_GET["pageCurrent"]))
            $_GET["pageCurrent"] = 1;

        $resultsPerPage = 3;
        $totalPages = ceil($records->count() / $resultsPerPage);

        // Put some limit checks on page numbers

        if ($_GET["pageCurrent"] < 1)
            $_GET["pageCurrent"] = 1;
        else if ($_GET["pageCurrent"] > $totalPages)
            $_GET["pageCurrent"] = $totalPages;

        // Grab the records we're going to render

        $paginatedRecords = new Collection;

        for ($i = ($_GET["pageCurrent"] - 1) * $resultsPerPage; $i < ($_GET["pageCurrent"] - 1) * $resultsPerPage + $resultsPerPage; $i++) {
            if ($records[$i] != null)
                $paginatedRecords[] = $records[$i];
        }

        // And spit this thing out

        $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($_SESSION["user"])->filterBy("service", PASTORAL_SERVICE)->count() > 0;

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/report.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'results'           => $paginatedRecords,
                    'resultsPerPage'    => $resultsPerPage,
                    'totalPages'        => $totalPages,
                    'totalResults'      => $records->count(),
                    'pastoralModerator' => $pastoralModerator,
                    'pageCurrent'       => $_GET["pageCurrent"],
                    'students'          => $_GET["students"],
                    'authors'           => $_GET["authors"],
                    'severity'          => $_GET["severity"],
                    'types'             => $_GET["types"],
                    'tags'              => $_GET["tags"],
                    'before'            => $_GET["before"],
                    'after'             => $_GET["after"],
                ),
            )
        );
    }

    public function getReportPrint()
    {
        // Get current users role

        $role = $_SESSION["user"]->getRole()->getType();

        // Kick them back to the kerb if they aren't allowed to view this page

        if ($role != "staff") {

            // User is not staff, kick them to the kerb

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

        $students = $_GET["students"];
        $authors = $_GET["authors"];
        $types = $_GET["types"];
        $before_date = $_GET["before"];
        $after_date = $_GET["after"];
        $severity = $_GET["severity"];

        // Get matching records

        $records = PastoralMapper::getInstance()->findAllReport($before_date, $after_date, $severity, $types, $authors, $students);

        // And render this thing out

        $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($_SESSION["user"])->filterBy("service", PASTORAL_SERVICE)->count() > 0;

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/reportPrint.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'results'           => $records,
                    'pastoralModerator' => $pastoralModerator
                ),
            )
        );
    }

    public function getReportBuilder()
    {
        // Get current users role. The screen is restricted to pastoral moderators only

        $role = $_SESSION["user"]->getRole()->getType();

        // Kick them back to the kerb if they shouldn't be looking at this page

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage. This should prevent accidental recording
            // of parents as moderators.

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // And render this thing out

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/reportBuilder.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'moderators'        => $moderators,
                    'types'             => PastoralTypeMapper::getInstance()->findAll(),
                    'severities'        => PastoralSeverityMapper::getInstance()->findAll()->sortBy('order', 'asc'),
                    'tags'              => PastoralTagsMapper::getInstance()->findAll()
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
        $student = UserMapper::getInstance()->findWithSubjects($id);
        $student->loadParents();

        // Get pastoral moderator status

        $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($_SESSION["user"])->filterBy("service", PASTORAL_SERVICE)->count() > 0;

        if (!$pastoralModerator)
        {
            // Kick them back to the kerb if they shouldn't be looking at this page

            if ($role != "staff" && !$_SESSION["user"]->isParentOf($student)) {

                // The logged in user is not staff/parent, send them back to the homepage

                $this->getResponse()->redirect->afterPost("/");
                return;
            }
        }

        // Get the students contact details for display

        $studentContacts = [];
        $contactList = $externalUserMapper->getStudentContactList($student->getExternalId());
        $studentExternal = $externalUserMapper->getStudent($student->getExternalId());

        if (is_array($contactList))
        {
            foreach ($contactList as $contact)
            {
                $main = null;
                try {
                    $main = UserMapper::getInstance()->findByExternalId($contact['StudentContactID']);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    // noop
                }
                $spouse = null;
                try {
                    $spouse = UserMapper::getInstance()->findByExternalId($contact['StudentContactSpouseID']);
                } catch (\Schoolbox\Mapper\Exception $e) {
                    // noop
                }

                $contact['main'] = $main;
                $contact['spouse'] = $spouse;
                $studentContacts[] = $contact;
            }
        }

        // And render this mess out

        $records = PastoralMapper::getInstance()->findByStudentId($id);

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/index.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'records'           => $records,
                    'student'           => $student,
                    'studentAge'        => $this->calculateAge($studentExternal['StudentBirthDate']),
                    'studentContacts'   => $studentContacts,
                    'role'              => $_SESSION["user"]->getRole()->getType(),
                    'types'             => PastoralTypeMapper::getInstance()->findAll(),
                    'pastoralModerator' => $pastoralModerator
                ),
            )
        );
    }

    public function getRecord($studentId, $id)
    {
        /** @var \Schoolbox\User $user */

        $user = $_SESSION["user"];

        // Get the current users role

        $role = $_SESSION["user"]->getRole()->getType();

        // Get the students parents

        $student = UserMapper::getInstance()->findWithSubjects($studentId);
        $student->loadParents();

        $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($user)->filterBy("service", PASTORAL_SERVICE)->count() > 0;

        if (!$pastoralModerator)
        {
            // Kick them back to the kerb if they shouldn't be looking at this page

            if ($role != "staff" && !$_SESSION["user"]->isParentOf($student) && !$pastoralModerator) {

                // The logged in user is not staff/parent, send them back to the homepage

                $this->getResponse()->redirect->afterPost("/");
                return;
            }
        }

        // Get the pastoral care record

        if ($pastoral = PastoralMapper::getInstance()->findById($id)) {

            // Lets render this thing

            $this->getResponse()->content->set(
                array(
                    'template' => '@pastoral/record.twig',
                    'data'     => array(
                        'session'           => $_SESSION,
                        'record'            => $pastoral,
                        'pastoralModerator' => $pastoralModerator
                    ),
                )
            );

        }
        else
        {
            // Record not found, probably don't have access

            $this->getResponse()->redirect->afterPost("/");
            return;
        }
    }

    public function getInsert($id)
    {
        // Only staff should be inserting comments

        $role = $_SESSION["user"]->getRole()->getType();

        if ($role != "staff")
        {
            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // Render this thing...

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/modify.twig',
                'data'     => array(
                    'session'           => $_SESSION,
                    'student'           => UserMapper::getInstance()->findById($id),
                    'id'                => '',
                    'record'            => null,
                    'types'             => PastoralTypeMapper::getInstance()->findAll(),
                    'severities'        => PastoralSeverityMapper::getInstance()->findAll()->sortBy('order', 'asc'),
                    'roles'             => RoleMapper::getInstance()->findAll(),
                    'attachzone'        => $this->getAttachZone(),
                ),
            )
        );
    }

    public function getUpdate($studentId, $id)
    {
        // Get the record we are updating

        $record = PastoralMapper::getInstance()->findById($id);

        // Only the staff member that owns the record should be updating it

        $role = $_SESSION["user"]->getRole()->getType();

        $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($_SESSION["user"])->filterBy("service", PASTORAL_SERVICE)->count() > 0;

        if (!$pastoralModerator)
        {
            if ($role != "staff" || $_SESSION["user"]->getId() != $record->getOwner()->getId()) {

                // The logged in user is not staff or is not the owner, send them back to the homepage

                $this->getResponse()->redirect->afterPost("/");
                return;
            }
        }

        // Lets get the party started...

        $this->getResponse()->content->set(
            array(
                'template' => '@pastoral/modify.twig',
                'data'     => array(
                    'session'         => $_SESSION,
                    'record'          => $record,
                    'types'           => PastoralTypeMapper::getInstance()->findAll(),
                    'severities'      => PastoralSeverityMapper::getInstance()->findAll()->sortBy('order', 'asc'),
                    'roles'           => RoleMapper::getInstance()->findAll(),
                    'tags'            => PastoralTagsMapper::getInstance()->findAll()->sortBy('name', 'asc'),
                    'attachzone'      => $this->getAttachZone(PastoralAttachmentMapper::getInstance()->findByPastoralId($id)),
                ),
            )
        );
    }

    public function postCommentDelete($id)
    {
        // Get the record we are updating

        $pastoral = PastoralMapper::getInstance();
        $record = $pastoral->findByIdWithOwner($id);

        // Only the staff member that owns the record should be updating it

        $role = $_SESSION["user"]->getRole()->getType();

        if ($role != "staff" || $_SESSION["user"]->getId() != $record->getOwner()->getId()) {

            // The logged in user is not staff or is not the owner, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        $commentMapper = PastoralCommentMapper::getInstance();
        $commentMapper->deleteByColumn("id", $_POST["id"]);
    }

    public function postComment()
    {
        // Post a comment on a pastoral record

        $role = $_SESSION["user"]->getRole()->getType();

        // Only staff should be doing this

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // No blank comments thanks

        if (trim($_POST["body"]) != '')
        {
            $postDate = date("Y-m-d H:i:s");

            $commentMapper = PastoralCommentMapper::getInstance();
            $comment = new PastoralCommentModel();
            $comment->setPastoralId($_POST["pastoral_id"]);
            $comment->setBody($_POST["body"]);
            $comment->setOwner($_SESSION["user"]);
            $comment->setPostDate($postDate);
            $commentMapper->save($comment);
        }

        // Go back to pastoral record

        $this->getResponse()->redirect->afterPost("/pastoral/{$_POST["student_id"]}/{$_POST["pastoral_id"]}");
    }

    public function postSave()
    {
        // Update/Insert a pastoral record

        $role = $_SESSION["user"]->getRole()->getType();

        // Only staff should be doing this

        if ($role != "staff") {

            // The logged in user is not staff, send them back to the homepage

            $this->getResponse()->redirect->afterPost("/");
            return;
        }

        // External interface

        $pastoralExternal = new \External_Pastoral();

        // Keep track of IDs created

        $recordIds = [];

        if (empty($_POST["id"])) {

            // Creating new pastoral record

            // Make an array of all students this record needs to be created on

            $students = array();

            // The student the user was currently viewing

            $students[$_POST["student_id"]] = 1;

            // And any other students specified on the pastoral record

            if (is_array($_POST["duplicate_id"])) {
                foreach ($_POST["duplicate_id"] as $studentId) {
                    if (trim($studentId) != '') {
                        $students[$studentId] = 1;
                    }
                }
            }

            // Create a post date, so they are all the same

            $postDate = date("Y-m-d H:i:s");

            // Go through every kid

            foreach ($students as $student_id => $value) {

                $record = new PastoralModel();

                // If this is not the first record, set the parent ID

                if (isset($parentId))
                    $record->setParentId($parentId);

                $record->setStudent(UserMapper::getInstance()->findById($student_id));
                $record->setHeading($_POST["heading"]);
                $record->setType(PastoralTypeMapper::getInstance()->findById($_POST["typeId"]));
                $record->setSeverity(PastoralSeverityMapper::getInstance()->findById($_POST["severityId"]));
                $record->setBody($_POST["body"]);
                $record->setPrivate($_POST["private"]);
                $record->setOwner($_SESSION["user"]);
                $record->setPostDate($postDate);
                PastoralMapper::getInstance()->save($record);

                $recordIds[] = $record->getId();

                // Store the first records ID for use as parent for subsequent copies

                if (!isset($parentId))
                    $parentId = $record->getId();

                // $pastoralExternal->createPastoral($record);

                // Save the authorised users

                if (isset($_POST["user_id"])) {
                    if (is_array($_POST["user_id"])) {
                        foreach ($_POST["user_id"] as $user_id) {
                            $access = new PastoralAccessModel();
                            $access->setPastoralId($record->getId());
                            $access->setUser(UserMapper::getInstance()->findbyId($user_id));
                            PastoralAccessMapper::getInstance()->save($access);
                        }
                    }
                }

                // Save the tags

                $tags = explode("," , $_POST["tags"]);

                foreach ($tags as $tagCurrent) {
                    if (trim($tagCurrent) != '') {
                        $tag = new PastoralTagsModel();
                        $tag->setName($tagCurrent);
                        $tag->setPastoralId($record->getId());
                        PastoralTagsMapper::getInstance()->save($tag);
                    }
                }

                // Save the audiences

                foreach ($_POST["role"] as $role_id => $checked) {
                    if ($checked) {
                        $access = new PastoralAccessModel();
                        $access->setPastoralId($record->getId());
                        $access->setRole(RoleMapper::getInstance()->findById($role_id));
                        PastoralAccessMapper::getInstance()->save($access);
                    }
                }
            }
        }
        else
        {
            // Modifying existing record

            $record = PastoralMapper::getInstance()->findById($_POST["id"]);

            $recordIds[] = $_POST["id"];

            // Only the owner or a moderator should be updating a record

            $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($_SESSION["user"])->filterBy("service", PASTORAL_SERVICE)->count() > 0;

            if (!$pastoralModerator)
            {
                if ($_SESSION["user"]->getId() != $record->getOwner()->getId()) {

                    // The logged in user is not the owner, get them outta here

                    $this->getResponse()->redirect->afterPost("/");
                    return;
                }
            }

            $record->setHeading($_POST["heading"]);
            $record->setType(PastoralTypeMapper::getInstance()->findById($_POST["typeId"]));
            $record->setSeverity(PastoralSeverityMapper::getInstance()->findById($_POST["severityId"]));
            $record->setBody($_POST["body"]);
            $record->setPrivate($_POST["private"]);
            $record->setOwner($_SESSION["user"]);
            $record->setModifiedDate(date("Y-m-d H:i:s"));
            PastoralMapper::getInstance()->save($record);

            // Update external record

            $pastoralExternal->updatePastoral($record);

            // Delete old tags

            PastoralTagsMapper::getInstance()->deleteByColumn("pastoral_id", $record->getId());

            // Save the tags

            $tags = explode(",", $_POST["tags"]);

            foreach ($tags as $tagCurrent) {
                if (trim($tagCurrent) != '') {
                    $tag = new PastoralTagsModel();
                    $tag->setName($tagCurrent);
                    $tag->setPastoralId($record->getId());
                    PastoralTagsMapper::getInstance()->save($tag);
                }
            }

            // Delete user permissions

            $accessRoles = $record->getAccessList();

            if (trim($_POST["deleteIds"]) != "") {
                $deleteIds = explode(",", $_POST["deleteIds"]);

                foreach ($deleteIds as $user_id) {
                    foreach ($accessRoles as $role) {
                        if ($role->getUser()->getId() == $user_id) {
                            PastoralAccessMapper::getInstance()->delete($role);
                            break;
                        }
                    }
                }
            }

            // Update the authorised users

            if (isset($_POST["user_id"])) {
                if (is_array($_POST["user_id"])) {
                    foreach ($_POST["user_id"] as $user_id) {

                        $found = false;

                        foreach ($accessRoles as $role) {
                            if ($role->getUser()->getId() == $user_id) {
                                if (!in_array($user_id, $deleteIds)) {
                                    $found = true;
                                }
                                break;
                            }
                        }

                        if (!$found) {
                            $access = new PastoralAccessModel();
                            $access->setPastoralId($record->getId());
                            $access->setUser(UserMapper::getInstance()->findById($user_id));
                            PastoralAccessMapper::getInstance()->save($access);
                        }
                    }
                }
            }

            // Update the role based audience


            foreach ($_POST["role"] as $role_id => $checked) {

                echo $role_id . " " . $checked . "<BR>";

                if ($checked) {

                    // Add this access if it doesn't already exist

                    $found = false;

                    foreach ($accessRoles as $role) {
                        if ($role->getRole()->getId() == $role_id) {
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $access = new PastoralAccessModel();
                        $access->setPastoralId($record->getId());
                        $access->setRole(RoleMapper::getInstance()->findById($role_id));
                        PastoralAccessMapper::getInstance()->save($access);
                    }

                } else {

                    // Delete this access if it exists because its no longer ticked

                    foreach ($accessRoles as $role) {
                        if ($role->getId() == $role_id) {
                            PastoralAccessMapper::getInstance()->delete($role);
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

        $attachments = PastoralAttachmentMapper::getInstance()->findByPastoralId($record->getId());

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
                PastoralAttachmentMapper::getInstance()->delete($attachment);

            } else {

                // We are keeping this attachment

                $retainedAttachments[] = $attachment->getHash();
            }
        }

        // Save any new attachments

        if (is_array($_POST["attachment"])) {
            foreach ($_POST["attachment"] as $hash) {

                $hash_array = explode(":", $hash);

                // Only add attachments which don't already exist

                foreach ($recordIds as $recordId)
                {
                    if (!in_array($hash_array[1], $retainedAttachments))
                    {
                        $attachment = new PastoralAttachmentModel();
                        $attachment->setPastoralId($recordId);
                        $attachment->setHash($hash_array[1]);
                        $attachment->setModifiedDate(date("Y-m-d H:i:s"));
                        PastoralAttachmentMapper::getInstance()->save($attachment);
                    }
                }
            }
        }

        // Send instant notifications - Must talk to doctor and change this to digestable notification

        if ($_POST["notify"] == 1) {

            $user = UserMapper::getInstance()->findById($_POST["student_id"]);

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

            $ids = array();

            $users = UserMapper::getInstance()->findAll();

            foreach ($_POST["role"] as $role_id => $checked) {

                // Find all users with this role

                foreach ($users as $user) {
                    if ($user->getRole()->getId() == $role_id) {

                        // Add them to the notification list

                        $ids[$user->getId()] = 1;
                    }
                }
            }

            // Don't send it to the user making the change

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

        $pastoralMapper = PastoralMapper::getInstance();
        $record = $pastoralMapper->findById($_POST["id"]);

        // Work out if this person should be allowed to delete the record

        $pastoralModerator = UserAccessMapper::getInstance()->findAllForUser($_SESSION["user"])->filterBy("service", PASTORAL_SERVICE)->count() > 0;

        if (!$pastoralModerator)
        {
            $role = $_SESSION["user"]->getRole()->getType();

            if ($role != "staff" || $_SESSION["user"]->getId() != $record->getOwner()->getId()) {

                // The logged in user is not staff or is not the owner, send them back to the homepage

                $this->getResponse()->redirect->afterPost("/");
                return;
            }
        }

        PastoralCommentMapper::getInstance()->deleteByColumn("pastoral_id", $_POST["id"]);

        // Get ready for some external ninja'ing

        $pastoralExternal = new \External_Pastoral();

        // Delete on external database

        $pastoralExternal->deletePastoral($record);

        // Delete locally

        $pastoralMapper->delete($record);
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
