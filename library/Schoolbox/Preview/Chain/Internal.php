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

use Schoolbox\Assessment\Component\SubmissionBox;
use Schoolbox\Preview\Chain\Strategy\Internal\Image;
use Schoolbox\Preview\Chain\Strategy\Internal\Media;
use Schoolbox\Preview\Chain\Strategy\Internal\IFrame;
use Schoolbox\Preview\Chain\Strategy\Internal\Redirect;
use Schoolbox\Resource\File\Mapper as FileMapper;
use Schoolbox\Resource\Storage\Mapper as StorageMapper;
use Zend\Uri\Uri;

class Internal extends Base {

    /**
     * Constructor
     *
     * @param $url
     */
    public function __construct($url) {

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
        }
        else if($id = $this->isSend($uriObject)) {
            try {
                $item = FileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item);
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        }
        else if($id = $this->isSubmission($uriObject)) {
            try {
                $item = SubmissionBox\Submission\FileMapper::getInstance()->findById($id);
                $this->processStorage($url, $item->getStorage());
            } catch (\Schoolbox\Mapper\Exception $e ) {
                //noop
            }
        }
        else if($id = $this->isReturn($uriObject)) {
            try {
                $item = SubmissionBox\ReturnedWorkMapper::getInstance()->findById($id);
                $this->processStorage($url, $item->getStorage());
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
        if ($url->getPath() == '/storage/fetch.php') {
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
     * Get the id from a submission URL
     *
     * @param  Uri $url
     * @return string
     */
    private function isSubmission(Uri $url)
    {
        if ($url->getPath() == '/submission/send.php') {
            $query = $url->getQueryAsArray();
            if (empty($query['return'])) {
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
    private function isReturn(Uri $url)
    {
        if ($url->getPath() == '/submission/send.php') {
            $query = $url->getQueryAsArray();
            if (!empty($query['return'])) {
                return $query['id'];
            }
        }
        return null;
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
