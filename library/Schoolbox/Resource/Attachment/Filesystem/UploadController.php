<?php

/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Resource\Attachment\Filesystem;

use Alaress_Date_Format;
use Laminas\Uri\Uri;
use Schoolbox\Resource\Attachment\AbstractController;
use Scrub;
use SitePage;

/**
 * Class UploadController
 *
 * @package Schoolbox\Resource\Attachment\Filesystem
 */
class UploadController extends AbstractController
{
    /**
     * The maximum number of files that can be uploaded when multi-uploads
     * are enabled.
     */
    private const DEFAULT_MAX_FILES = 256;

    /**
     * Constructor
     *
     * @param bool                     $multi
     * @param string                   $mimeFilter
     * @param bool                     $useFolder
     * @param int                      $defaultFolderId
     * @param Alaress_Date_Format|null $expiryDate
     */
    public function __construct(
        $multi,
        $mimeFilter = null,
        $useFolder = true,
        $defaultFolderId = 0,
        private ?Alaress_Date_Format $expiryDate = null,
    ) {
        parent::__construct($multi, $mimeFilter, $useFolder, $defaultFolderId);
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

        $uploadUrl = new Uri('/storage/asyncUpload.php');
        if ($this->expiryDate && !$this->expiryDate->isNull()) {
            $uploadUrl = $uploadUrl->setQuery(['expiryDate' => $this->expiryDate->toRfc3339String()]);
        }

        $page->setVar([
            'UPLOAD_URL'     => $uploadUrl->toString(),
            'MAX_FILES'      => $this->multi ? self::DEFAULT_MAX_FILES : '1',
            'ACCEPTED_FILES' => $mime,
        ]);
    }


    /**
     * @return string
     */
    public function getLabel()
    {
        return 'Upload file' . ($this->multi ? 's' : '');
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
        return 'upload';
    }
}
