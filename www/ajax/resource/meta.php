<?php
/**
 * Ajax callback for retrieving metadata about a folder / file (or files) in the resource browser view
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2013, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once('../../../include/Bootstrap.php');
use Schoolbox\UserMapper;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Permission\Access\UserAccessMapper;

// kickstart a session
new SitePage();

// disallow SitePage context, this is an ajax script
if (isset($this)) {
    SitePage::localRedir('/ajax/resource/meta.php');
    exit;
}

// check 1: folder specified and sane
if (!(isset($_POST['folderId']) && is_numeric($_POST['folderId']))) {
    sendError('No folder ID specified');
}

$folderId = $_POST['folderId'];

// check 2: user logged in an has at minimum read access
$groupHandler = new Group();
$access = $groupHandler->getPermission($folderId);
if ($access < READ_ACCESS) {
    sendError('User does not have access to this folder');
}

// check 3: if no files specified, return folder
if (!isset($_POST['files'])) {
    $response = getFolderMeta($folderId);
    die(json_encode($response));
}

// check 4: files correctly specified
if (!is_array($_POST['files'])) {
    sendError('Invalid file ID(s) specified');
}


// check 5: single or multi?
if (count($_POST['files']['file']) + count($_POST['files']['link']) == 1) {
    if (isset($_POST['files']['file'])) {
        $response = getSingleFileMeta($folderId, $_POST['files']['file'][0]);
    } else {
        $response = getSingleLinkMeta($folderId, $_POST['files']['link'][0]);
    }
} else {
    $response = getMultiFileMeta($folderId);
}

header('Content-Type: application/json');
die(json_encode($response));


/**
 * Send an appropriate error and bug out
 *
 * @param $errString
 */
function sendError($errString)
{
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    die(json_encode(array('error', $errString)));
}


/**
 * Send the metadata for the specified folder
 *
 * @param $folderId
 * @return array
 */
function getFolderMeta($folderId)
{

    // handler setup
    $groupHandler = new Group();

    $folderMapper = new \Schoolbox\Resource\CategoryMapper();

    try {
/* Only throw error exception if a folderId is specified
         * and it can't be found; $folderId 0 is used to add a
         * folder to the root node
         */
        if ($folderId) {
            $folder = $folderMapper->findById($folderId);
        }
    } catch (Schoolbox\Mapper\Exception $e) {
        sendError('No folder with specified ID exists');
        exit;
    }


    // if it's the root node, return add-folder functions if admin
    if ($folderId == 0) {
        $actions = $groupHandler->isSuperuser() ? array('folder') : array();

        $folder = array(
            'name'        => 'Resources',
            'id'          => 0,
            'template'    => '',
            'type'        => '',
            'joinable'    => 0,
            'permissions' => array(),
            'actions'     => $actions,
        );
        return $folder;
    }

    // determine the name of the template, if the folder has a homepage


    // determine the group type (open, restricted, moderated)
    if ($folder->getGroup()->isPrivate()) {
        $type = 'Restricted';
    } elseif ($folder->getGroup()->isModerated()) {
        $type = 'Moderated';
    } elseif ($folder->getGroup()->isFree()) {
        $type = 'Open';
    } else {
        $type = '';
    }

    if ($type) {
        // joinable: is the logged in user not already a member?
        $accessType = $groupHandler->involvedInGroup($_SESSION['id'], $folderId);

        $joinable = 0; // either don't have access, or are already a member
        if ($accessType == DENY_ACCESS) {
            if (!is_null($accessType)) {
                $joinable = 2; // pending approval
            } elseif ($groupHandler->canSubscribe($folderId)) {
                $joinable = 1; // can join
            }
        } elseif ($accessType >= READ_ACCESS) {
            $joinable = 3; //member of group
        }

        $members = UserAccessMapper::getInstance()->findAdminsInCategory($folder);
        if (count($members) > 0) {
            $admin = $members->first(); //only every 1 admin
        } else {
            $admin = UserMapper::getInstance()->getUnknownUser();
        }
    }


    // permissions: must have write access to see it
    $userAccess = $groupHandler->getPermission($folderId);
    if ($userAccess >= WRITE_ACCESS) {
        $permissions = array();

        $roleAccessMapper = new \Schoolbox\Permission\Access\RoleAccessMapper();
        $allGroupPerms = $roleAccessMapper->findAllForFolder($folder);

        $j = 0;

        foreach ($allGroupPerms as $currentGroupPerm) {
            if ($currentGroupPerm->getType() > 0) {
                $permissions[] = array(
                    'group'  => $currentGroupPerm->getRole()->getName(),
                    'access' => $currentGroupPerm->getType(),
                    'on_off' => ++$j % 2 ? '' : 'dark',
                );
            }
        }
    } else {
        $permissions = array();
    }

    // actions: if write, some; if full, all.
    $actions = array();
    switch ($userAccess) {
        case FULL_ACCESS:
            $actions[] = 'multi';
            if ($groupHandler->isSuperuser()) {
                $actions[] = 'zip';
            }
        // no break
        case WRITE_ACCESS:
            $actions = array_merge($actions, array('file', 'link', 'folder', 'modify', 'download', 'multi'));
            $actions[] = $groupHandler->isSuperuser() ? 'delete_all' : 'delete';

            /* Allow teachers to upload a zip file
             */
            if ($groupHandler->isStaff() && \Cfg::get('enable_teacher_zip_upload')) {
                $actions[] = 'zip';
            }


        // no break
        case READ_ACCESS:
        default:
            // no actions available
            break;
    }


    if ($folder->hasHomepage()) {
        $actions[] = 'homepage';
    }

    $folder = array(
        'name'        => $folder->getName(),
        'id'          => $folder->getId(),
        'template'    => $folder->hasHomepage(),
        'type'        => $type,
        'joinable'    => $joinable,
        'permissions' => $permissions,
        'actions'     => $actions
    );

    if ($type) {
        $folder['admins'] = array('externalID'=>$admin->getExternalId(), 'name'=>$admin->getFullName());
        $folder['manage'] = $_SESSION['id'] == $admin->getId() || $_SESSION['user']->isSuperuser() ? true : false;
    }

    return $folder;

}


/**
 * Send the metadata for a single file
 *
 * @param $folderId
 * @param $fileId
 * @return array
 */
function getSingleFileMeta($folderId, $fileId)
{

    $groupHandler = new Group();

    $fmap = FileMapper::getInstance();
    $file = $fmap->findById($fileId);
    try {
        $owner = UserMapper::getInstance()->findById($file->getOwner()->getId());
    } catch (Schoolbox\Mapper\Exception $e) {
        $owner = UserMapper::getInstance()->getUnknownUser();
    }


    if (!$file) {
        sendError('No file with the specified ID exists');
    }

    $userAccess = $groupHandler->getPermission($folderId);

    // write access provides actions
    $actions = array();
    switch ($userAccess) {
        case FULL_ACCESS:
        case WRITE_ACCESS:
            $actions = array_merge($actions, array('modify', 'delete'));
        // no break
        case READ_ACCESS:
            $actions = array_merge($actions, array('open', 'download', 'properties'));
        // no break
        default:
            // no actions available
            break;
    }

    $modified = new Alaress_Date_Format($file->getModifiedAt());

    $file = array(
        'kind'        => 'file',
        'id'          => $file->getId(),
        'name'        => $file->getTitle(),
        'mime'        => $file->getStorage()->getMimeType()->getIconFile(),
        'filesize'    => Alaress_Byte_Format::bytesToString($file->getSize()),
        'hidden'      => $file->getHidden() ? 1 : 0,
        'description' => $file->getDescription(),
        'posted_by'   => $owner->formatName(),
        'posted_date' => $file->getCreatedAt()->getDateOnly(),
        'modified'    => $modified->getRelativeTime(),
        'actions'     => $actions
    );

    return $file;
}

/**
 * @param $folderId
 * @param $linkId
 * @return array
 */
function getSingleLinkMeta($folderId, $linkId)
{
    $groupHandler = new Group();

    try {
        $linkMapper = new \Schoolbox\Resource\LinkMapper();
        $link = $linkMapper->findById($linkId);

        try {
            $userMapper = new \Schoolbox\UserMapper();
            $user = $userMapper->findById($link->getOwner()->getId());
        } catch (\Exception $e) {
            error(31);
        }
    } catch (\Exception $e) {
        error(101);
    }

    $userAccess = $groupHandler->getPermission($folderId);

    // actions based on access
    $actions = array();

    switch ($userAccess) {
        case FULL_ACCESS:
        case WRITE_ACCESS:
            $actions = array_merge($actions, array('modify', 'delete'));
        // no break
        case READ_ACCESS:
            $actions = array_merge($actions, array('open', 'properties'));
        // no break
        default:
            // no actions available
            break;
    }

    $file = array(
        'kind'        => 'link',
        'id'          => $link->getId(),
        'name'        => $link->getDescription(),
        'hidden'      => $link->getHidden(),
        'description' => $link->getComment(),
        'posted_by'   => $user->formatName(),
        'posted_date' => $link->getSubmitted()->getDateOnly(),
        'actions'     => $actions
    );

    return $file;
}


/**
 * Send action-permissions available for the files specified.
 * Pretty much a reduced version of sendFolderMeta()
 *
 * @param $folderId
 * @return array
 */
function getMultiFileMeta($folderId)
{

    // if has write permission, grant delete access
    $groupHandler = new Group();
    $access = $groupHandler->getPermission($folderId);

    if ($access <= READ_ACCESS) {
        return array(
            'actions' => array()
        );
    } else {
        return array(
            'actions' => array('delete')
        );
    }
}
