<?php

/**
 * @copyright   Copyright 2025, Schoolbox Pty Ltd
 * @link        https://schoolbox.education/
 */

declare(strict_types=1);

namespace Schoolbox\Resource\Attachment\Filesystem;

use OutOfRangeException;
use Schoolbox\Config\ConfigMapper;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Attachment\AbstractController;
use Scrub;
use SitePage;

/**
 * Class ImagegeneratorController
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class ImagegeneratorController extends AbstractController
{
    /**
     * There is no multi-selection for this at the moment.
     */
    private const MAX_FILES = 1;


    /**
     * @param bool   $multi
     * @param string $mimeFilter
     * @param bool   $useFolder
     * @param int    $defaultFolderId
     * @throws OutOfRangeException
     */
    public function __construct($multi, $mimeFilter = null, $useFolder = true, $defaultFolderId = 0)
    {
        if (!$this->allowsImage($mimeFilter)) {
            throw new OutOfRangeException("mimeType is not an image.");
        }
    }

    /**
     * checks to see if the mimetype allows an image
     * @param string $mimeFilter
     * @return bool
     */
    protected function allowsImage(string $mimeFilter): bool
    {
        $csv = str_getcsv($mimeFilter);
        foreach ($csv as $mime) {
            $tmp = explode('/', $mime);
            if (in_array($tmp[0], array("image","*",""))) {
                return true;
            }
        }
        return false;
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
                'MAX_FILES'      => self::MAX_FILES,
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
        return 'Image Generator';
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
        return 'ai';
    }
}
