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
     * internal
     * Publicly-accessible filesystems
     */
    private const INTERNAL = 'internal.upload,internal.usermedia,internal.browse,internal.teams';


    /**
     * internal-myfile
     * Publicly-accessible filesystems plus My File
     */
    private const INTERNAL_MYFILE = 'internal.upload,internal.usermedia,internal.browse,internal.myfile';


    /**
     * external
     * External filesystems only
     */
    private const EXTERNAL = 'external.webdav,external.google,external.office';


    /**
     * Default configuration of resource picker filesystems
     *
     * @var array
     */
    private $defaultOptions = [

        /* Legacy aliases
         * I think we can actually remove these, but for now, I'm going to hold on
         */
//        'internal' => [
//            'upload'    => Internal\UploadController::class,
//            'usermedia' => Internal\UsermediaController::class,
//            'browse'    => Internal\BrowseController::class,
//            'myfile'    => Internal\MyfileController::class,
//        ],
//        'external' => [
//            'webdav' => External\WebdavController::class,
//            'google' => External\GoogleController::class,
//            'office' => External\Office365Controller::class,
//        ],

        /* Controllers for the Link modal
         */
        'link' => [
            'upload'    => Filesystem\UploadController::class,
            'usermedia' => Filesystem\UsermediaController::class,
            'browse'    => Filesystem\BrowseController::class,
            'myfile'    => Filesystem\MyfileController::class,
            'teams'     => Filesystem\TeamsController::class
        ],

        'default' => [
            'upload'    => Filesystem\UploadController::class,
        ]
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
         */
        if (!$this->context) {
            return $this->defaultOptions['default'];
        }

        /* Unrecognised context; use default options
         * (Alternatively return empty array?)
         */
        if (!isset($this->defaultOptions[$this->context])) {
            return $this->defaultOptions['default'];
        }

        /* Context provided; use whatever that specification is
         */
        return $this->defaultOptions[$this->context];
    }
}
