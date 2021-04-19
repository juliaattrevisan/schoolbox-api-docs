<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace Schoolbox\Resource\Attachment\Context;

use Schoolbox\Resource\Attachment\Filesystem;

/**
 * Class Factory
 *
 * Produce controllers which present each of the modal's accordions
 *
 * @package Schoolbox\Resource\Attachment\Context
 */
class Factory
{

    /**
     * Provide this set of controllers by default
     */
    private const DEFAULT_FILESYSTEM = 'files';


    /**
     * Default configuration of resource picker filesystems
     * These are chosen via the javascript allowedFilesystems config var
     *
     * @var array
     */
    private const OPTIONS = [

        /* Enable every filesystem option
         */
        'all' => [
            'upload'    => Filesystem\UploadController::class,
            'usermedia' => Filesystem\UsermediaController::class,
            'browse'    => Filesystem\BrowseController::class,
            'teams'     => Filesystem\TeamsController::class,
            'myfile'    => Filesystem\MyfileController::class,
            'webdav'    => Filesystem\WebdavController::class,
            'google'    => Filesystem\GoogleController::class,
            'office'    => Filesystem\Office365Controller::class,
        ],

        /* Controllers for the Link modal
         */
        'link' => [
            'upload'    => Filesystem\UploadController::class,
            'usermedia' => Filesystem\UsermediaController::class,
            'browse'    => Filesystem\BrowseController::class,
            'teams'     => Filesystem\TeamsController::class
        ],

        /* Folders only, which roughly equates to the socialstream picker
         */
        'folder' => [
            'browse'    => Filesystem\BrowseController::class,
        ],

        /* Controllers for File-related modals
         *
         * This primarily affects the "video/audio" and "image" buttons in ckeditor
         * as those both use schoolboxresource.js
         *
         * These might currently differ a little to alpha.schoolbox.com.au
         * because I haven't figured out how to configure webdav/office to compare
         */
        'files' => [
            'upload'    => Filesystem\UploadController::class,
            'usermedia' => Filesystem\UsermediaController::class,
            'browse'    => Filesystem\BrowseController::class,
            'myfile'    => Filesystem\MyfileController::class,
            'webdav'    => Filesystem\WebdavController::class,
            'google'    => Filesystem\GoogleController::class,
            'office'    => Filesystem\Office365Controller::class,
        ],

        /* Removes the Recording option for contexts where you only wanted images
         * or other non-video/non-audio sources
         */
        'images' => [
            'upload'    => Filesystem\UploadController::class,
            'browse'    => Filesystem\BrowseController::class,
            'myfile'    => Filesystem\MyfileController::class,
            'webdav'    => Filesystem\WebdavController::class,
            'google'    => Filesystem\GoogleController::class,
            'office'    => Filesystem\Office365Controller::class,
        ],

        /* Learning/Tasks
         * Tasks have a separate attachzone for uploading files, though they should retain
         * other non-upload filesystems for adding Links to a Task.
         */
        'task' => [
            'browse'    => Filesystem\BrowseController::class,
            'teams'     => Filesystem\TeamsController::class,
            // I really don't think that Tasks (which are links) should allow WebDAV:
            // permissions between staff and students could be wildly different
//            'webdav'    => Filesystem\WebdavController::class,
            'google'    => Filesystem\GoogleController::class,
            'office'    => Filesystem\Office365Controller::class,
        ],

        /* Social Stream: a link which may be to a file,
         * everything except WebDAV is allowed
         */
        'socialstream' => [
            'upload'    => Filesystem\UploadController::class,
            'usermedia' => Filesystem\UsermediaController::class,
            'browse'    => Filesystem\BrowseController::class,
            'teams'     => Filesystem\TeamsController::class,
            'myfile'    => Filesystem\MyfileController::class,
            'google'    => Filesystem\GoogleController::class,
            'office'    => Filesystem\Office365Controller::class,
        ],
    ];


    /**
     * Context macro
     *
     * @var string
     */
    private $context;


    /**
     * Factory constructor.
     *
     * @param string $context
     */
    public function __construct(string $context) {
        $this->context = $context;
    }


    /**
     * Manufacture a set of predetermined filesystemType/filesystem options
     * before passing it into Factory.
     */
    public function produce(): array
    {

        /* Default context; use default options!
         *
         * or, unrecognised context; use default options
         * (Alternatively return empty array? not sure how/why this might manifest)
         */
        if (!$this->context || !isset(self::OPTIONS[$this->context])) {
            return self::OPTIONS[self::DEFAULT_FILESYSTEM];
        }

        /* Context provided; use whatever that specification is
         */
        return self::OPTIONS[$this->context];
    }
}
