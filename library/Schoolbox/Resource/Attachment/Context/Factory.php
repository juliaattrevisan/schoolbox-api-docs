<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Context;

use Schoolbox\Resource\Attachment\External;
use Schoolbox\Resource\Attachment\Internal;

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
        'internal' => [
            'upload'    => Internal\UploadController::class,
            'usermedia' => Internal\UsermediaController::class,
            'browse'    => Internal\BrowseController::class,
            'myfile'    => Internal\MyfileController::class,
            'teams'     => Internal\TeamsController::class
        ],
        'external' => [
            'webdav' => External\WebdavController::class,
            'google' => External\GoogleController::class,
            'office' => External\Office365Controller::class,
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
        $defaultOptions = $this->defaultOptions;

        /* Default context; use default options!
         */
        if (!$this->context) {
            return $defaultOptions;
        }

        /* Specific (selective usage) context
         * Display nothing if you try an unlisted context
         */
        switch($this->context) {
            case 'internal':
                $filesystemFilter = self::INTERNAL;
                break;

            case 'internal-myfile':
                $filesystemFilter = self::INTERNAL_MYFILE;
                break;

            case 'external':
                $filesystemFilter = self::EXTERNAL;
                break;

            default:
                $filesystemFilter = '';
                break;
        }

        /* Interpret context string
         */
        $filteredOptions = [];

        $allFilesystemFilters = explode(',', $filesystemFilter);

        /* Manufacture a restricted set
         */
        foreach ($allFilesystemFilters as $currentFilter) {
            [$filesystemType, $filesystem] = explode('.', $currentFilter);

            $filteredOptions[$filesystemType][$filesystem] = $defaultOptions[$filesystemType][$filesystem];
        }

        return $filteredOptions;
    }
}
