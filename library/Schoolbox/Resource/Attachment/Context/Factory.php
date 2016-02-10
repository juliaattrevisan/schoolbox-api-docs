<?php

namespace Schoolbox\Resource\Attachment\Context;

use Schoolbox\Resource\Attachment\External;
use Schoolbox\Resource\Attachment\Internal;

/**
 * Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
class Factory {

    /**
     * internal
     * Publicly-accessible filesystems
     */
    const INTERNAL = 'internal.upload,internal.usermedia,internal.browse';

    /**
     * internal-myfile
     * Publicly-accessible filesystems plus My File
     */
    const INTERNAL_MYFILE = 'internal.upload,internal.usermedia,internal.browse,internal.myfile';

    /**
     * external
     * External filesystems only
     */
    const EXTERNAL = 'external.webdav,external.google,external.office';

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
        ],
        'external' => [
            'webdav' => External\WebdavController::class,
            'google' => External\GoogleController::class,
            'office' => External\Office365Controller::class
        ]
    ];

    /**
     * Context macro
     *
     * @var string
     */
    private $context = '';

    /**
     * Constructor
     *
     * Factory constructor.
     * @param $context
     */
    public function __construct($context) {
        $this->setContext($context);
    }

    /**
     * Manufacture a restricted set of filesystemType/filesystem options
     * before passing it into Factory. I still need the classnames mapped
     * by $defaultOptions only to truncate them out later...thus, maybe
     * all this should be done inside the Attachment Factory instead.
     */
    public function produce() {
        $defaultOptions = $this->getDefaultOptions();

        /* Default context; use default options!
         */
        if (!$this->getContext()) {
            return $defaultOptions;
        }
        /* Specific (selective usage) context
         * Display nothing if you try an unlisted context
         */
        else {
            switch($this->getContext()) {
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
            $filteredOptions = array();

            $allFilesystemFilters = explode(',', $filesystemFilter);

            /* Manufacture a restricted set
             */
            foreach ($allFilesystemFilters as $currentFilter) {
                list($filesystemType, $filesystem) = explode('.', $currentFilter);

                $filteredOptions[$filesystemType][$filesystem] = $defaultOptions[$filesystemType][$filesystem];
            }

            return $filteredOptions;
        }
    }

    /* Getters/Setters
     */

    /**
     * Get default options
     *
     * @return array
     */
    private function getDefaultOptions()
    {
        return $this->defaultOptions;
    }

    /**
     * Set default options
     *
     * @param array $defaultOptions
     */
    private function setDefaultOptions($defaultOptions)
    {
        $this->defaultOptions = $defaultOptions;
    }

    /**
     * Get context
     *
     * @return string
     */
    private function getContext()
    {
        return $this->context;
    }

    /**
     * Set context
     *
     * @param string $context
     */
    private function setContext($context)
    {
        $this->context = $context;
    }
}