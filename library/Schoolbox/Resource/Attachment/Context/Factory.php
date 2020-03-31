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
     *
     * @var array
     */
    private const OPTIONS = [

        /* Controllers for the Link modal
         */
        'link' => [
            'upload'    => Filesystem\UploadController::class,
            'usermedia' => Filesystem\UsermediaController::class,
            'browse'    => Filesystem\BrowseController::class,
            'myfile'    => Filesystem\MyfileController::class,
            'teams'     => Filesystem\TeamsController::class
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
    ];


    /**
     * Context macro
     *
     * @var string
     */
    private $context;


    /**
     * Constructor
     *
     * Factory constructor.
     *
     * @param string $context
     */
    public function __construct(string $context) {
        $this->context = $context;
    }


    /**
     * Manufacture a restricted set of filesystemType/filesystem options
     * before passing it into Factory. I still need the classnames mapped
     * by $defaultOptions only to truncate them out later...thus, maybe
     * all this should be done inside the Attachment Factory instead.
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
