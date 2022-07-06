<?php

/**
 * @author      Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use OutOfRangeException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\AbstractController;
use Scrub;
use SitePage;

/**
 * Class UsermediaController
 *
 * An attachment controller for user media (audio/video recordings).
 *
 * @see https://w3c.github.io/mediacapture-main/getusermedia.html
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class UsermediaController extends AbstractController
{
    /**
     * The maximum number of files that can be uploaded when multi-uploads
     * are enabled.
     */
    private const DEFAULT_MAX_FILES = 32;


    /**
     * @param bool   $multi
     * @param string $mimeFilter
     * @param bool   $useFolder
     * @param int    $defaultFolderId
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $useFolder = true, $defaultFolderId = 0)
    {
        //$mimeFilter = 'image/*';
        if ($this->isRecordable($mimeFilter)) {
            parent::__construct($multi, $mimeFilter, $useFolder, $defaultFolderId);
        } else {
            throw new OutOfRangeException("mimeType is not recordable.");
        }
    }

    /**
     * checks to see if the mimetype is able to be recorded.
     * (voice, video, screencapture)
     * @param string|null   $mimeFilter
     * @returns bool
     */
    protected function isRecordable($mimeFilter)
    {
        $recordable = false;
        $csv = str_getcsv($mimeFilter);
        foreach ($csv as $mime) {
            $tmp = explode('/', $mime);
            if (in_array($tmp[0], array("video","audio","*",""))) {
                $recordable = true;
            }
        }
        return $recordable;
    }

    /**
     * @param SitePage $page
     */
    protected function pparse(SitePage $page)
    {
        if (is_null($this->mimeFilter) || $this->mimeFilter == '*/*') {
            $mime = 'null';
        } else {
            $mime = "'" . Scrub::text($this->mimeFilter) . "'";
        }

        /** @var IConfigLoader $config */
        $config = $page->getDi()->get('config');
        $page->setVar(
            array(
                'MAX_FILES'      => $this->multi ? self::DEFAULT_MAX_FILES : '1',
                'MAX_FILE_SIZE'  => $config->get(ConfigMapper::NAME_MAX_FILE_SIZE),
                'ACCEPTED_FILES' => $mime
            )
        );
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Recording';
    }


    /**
     * @return string
     */
    public function getRenderMethod()
    {
        return self::RENDER_INLINE;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return 'mic';
    }
}
