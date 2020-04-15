<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Preview\Chain;

use Eportfolio\ShowcaseMapper;
use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Assessment\Component\SubmissionBoxMapper;
use Schoolbox\Mapper\Exception;
use Schoolbox\Permission\Access\UserAccessMapper;
use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Schoolbox\User;
use Zend\Uri\Exception\InvalidArgumentException;
use Zend\Uri\Uri;

/**
 * Class Internal
 *
 * Seeks preview from an Internal (local) url
 *
 * @package Schoolbox\Preview\Chain
 */
class Internal extends Base
{

    /**
     * Constructor
     *
     * @param string $url
     * @param User   $user The user to check permissions against
     * @throws InvalidArgumentException
     */
    public function __construct(string $url, User $user) {

        /* Storage item: find the hash, and analyse it to find out
         * how to display it (or redirect, if it can't be displayed)
         */
        $uriObject = new Uri($url);

        /* $item can be one of
         * Schoolbox\Resource\Storage\Model
         * Schoolbox\Resource\File\Model
         * Schoolbox\Assessment\Component\SubmissionBox\Submission\Submission
         * Schoolbox\Assessment\Component\SubmissionBox\ReturnedWork
         */
        if ($hash = $this->isStorage($uriObject)) {
            try {
                $item = StorageMapper::getInstance()->findByHash($hash);
                $this->processStorage($url, $item);
            } catch (Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isSend($uriObject)) {
            try {
                $item = FileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isSubmission($uriObject)) {
            try {
                $item = SubmissionBox\Submission\SubmissionMapper::getInstance()->findById($id);
                $item->setSubmissionBox(SubmissionBoxMapper::getInstance()->findById($item->getSubmissionBox()->getId()));

                /* TODO But submissions (Responses) have multiple files now - this is probably impossible
                 */
                if ($item->hasPermission($user, UserAccessMapper::getInstance(), ShowcaseMapper::getInstance())) {
                    $this->processStorage($url, StorageMapper::getInstance()->findByHash($item->getStorage()->getHash()));
                }
            } catch (Exception $e ) {
                //noop
            }
        } elseif ($id = $this->isReturn($uriObject)) {
            try {
                $item = SubmissionBox\ReturnedWorkMapper::getInstance()->findById($id);

                /* TODO But returns (Feedbacks) have multiple files now - this is probably impossible
                 */
                if ($item->hasPermission($user)) {
                    $this->processStorage($url, StorageMapper::getInstance()->findByHash($item->getStorage()->getHash()));
                }
            } catch (Exception $e ) {
                //noop
            }
        } else {

            /* Just redirect straight there
             */
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
    private function isStorage(Uri $url): ?string
    {
        $path = $url->getPath();

        if ($path === '/storage/fetch.php' || $path === '/storage/image.php') {
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
    private function isSend(Uri $url): ?string
    {
        if ($url->getPath() === '/send.php') {
            $query = $url->getQueryAsArray();

            return $query['id'];
        }

        return null;
    }


    /**
     * Get the id from a submission URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isSubmission(Uri $url): ?string
    {
        if ($url->getPath() === '/submission/send.php') {
            $query = $url->getQueryAsArray();

            if (empty($query['save']) && empty($query['return'])) {
                return $query['id'];
            }
        }

        return null;
    }


    /**
     * Get the id from a return URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isReturn(Uri $url): ?string
    {
        if ($url->getPath() === '/submission/send.php') {
            $query = $url->getQueryAsArray();

            if (empty($query['save']) && !empty($query['return'])) {
                return $query['id'];
            }
        }

        return null;
    }


    /**
     * Process Storage file analysis
     *
     * @param string $url
     * @param $item
     */
    private function processStorage(string $url, $item): void
    {
        $this->start([
            new Image($url, $item),
            new Media($url, $item),
            new IFrame($url, $item),
            new Redirect($url, $item),
        ]);
    }
}
