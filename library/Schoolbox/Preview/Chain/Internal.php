<?php
/**
 * Chain
 *
 * Seeks preview from an External (non-local) url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Preview\Chain;

use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\File\Model as FileModel;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Zend\Uri\Uri;

class Internal extends Base {

    /**
     * Constructor
     *
     * @param string $url
     * @param User   $user The user to check permissions against
     */
    public function __construct($url, User $user) {

        /* Storage item: find the hash, and analyse it to find out
         * how to display it (or redirect, if it can't be displayed)
         */
        $uriObject = new Uri($url);
        if ($hash = $this->isStorage($uriObject)) {
            try {
                $item = StorageMapper::getInstance()->findByHash($hash);
                $this->processStorage($url, $item);
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isSend($uriObject)) {
            try {
                $item = FileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isSubmission($uriObject)) {
            try {
                $item = SubmissionBox\Submission\SubmissionMapper::getInstance()->findById($id);
                $item->setSubmissionBox(SubmissionBoxMapper::getInstance()->findById($item->getSubmissionBox()->getId()));
                if ($item->hasPermission($user, UserAccessMapper::getInstance(), ShowcaseMapper::getInstance())) {
                    $this->processStorage($url, StorageMapper::getInstance()->findByHash($item->getStorage()->getHash()));
                } else {
                    // throw some error
                }
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isReturn($uriObject)) {
            try {
                $item = SubmissionBox\ReturnedWorkMapper::getInstance()->findById($id);
                if ($item->hasPermission($user)) {
                    $this->processStorage($url, StorageMapper::getInstance()->findByHash($item->getStorage()->getHash()));
                } else {
                    // error
                }
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        }
        /* Just redirect straight there
         */
        else {
            $this->start([
                new Redirect($url, null),
            ]);
        }
    }

    /**
     * Get the hash out of a storage url
     *
     * @param  Uri $url
     * @return string
     */
    private function isStorage(Uri $url)
    {
        if ($url->getPath() == '/storage/fetch.php' || $url->getPath() == '/storage/image.php') {
            $query = $url->getQueryAsArray();
            return $query['hash'];
        }
        return null;
    }

    /**
     * Get the id from a send URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isSend(Uri $url)
    {
        if ($url->getPath() == '/send.php') {
            $query = $url->getQueryAsArray();
            return $query['id'];
        }
        return null;
    }

    /**
     * Checks whether this SBFS file is viewable by the current user.
     * (Cribbed from /send.php)
     *
     * It's viewable if the current user has at least read access to the folder.
     *
     * @param  FileModel $file
     * @param  User      $user
     * @return bool
     */
    private function checkSendPermissions(FileModel $file, User $user)
    {
        return true;
    }

    /**
     * Get the id from a submission URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isSubmission(Uri $url)
    {
        if ($url->getPath() == '/submission/send.php') {
            $query = $url->getQueryAsArray();
            if (empty($query['save']) && empty($query['return'])) {
                return $query['id'];
            }
        }
        return null;
    }

    /**
     * Checks whether this submission is viewable by the current user.
     * (Cribbed from /submission/send.php)
     *
     * It's viewable if any of the following are true:
     * * The current user is the owner of the submission
     * * The current user has write level access to the page of the submission
     * * The submission has been showcased
     *
     * @param  SubmissionBox\ReturnedWork $file
     * @param  User                       $user
     * @return bool
     */
    private function checkSubmissionPermissions(SubmissionBox\ReturnedWork $file, User $user)
    {
        if ($file->getOwner()->getId() == $user->getId()) {
            return true;
        }
        return false;
    }

    /**
     * Get the id from a return URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isReturn(Uri $url)
    {
        if ($url->getPath() == '/submission/send.php') {
            $query = $url->getQueryAsArray();
            if (empty($query['save']) && !empty($query['return'])) {
                return $query['id'];
            }
        }
        return null;
    }

    /**
     * Checks whether this return is viewable by the current user.
     * (Cribbed from /submission/send.php)
     *
     * It's viewable if any of the following are true:
     * * The current user is the owner of the return
     * * The current user is a parent of the owner
     * * The current user is a staff member
     *
     * @param  SubmissionBox\Submission\File $file
     * @param  User                          $user
     * @return bool
     */
    private function checkReturnPermissions(SubmissionBox\Submission\File $file, User $user)
    {
        if ($file->getOwner()->getId() == $user->getId()) {
            return true;
        }
        return false;
    }

    /**
     * Process Storage file analysis
     *
     * @param $url
     * @param $item
     */
    private function processStorage($url, $item) {
        $this->start([
            new Image($url, $item),
            new Media($url, $item),
            new IFrame($url, $item),
            new Redirect($url, $item),
        ]);
    }
}
