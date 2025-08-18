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
use Schoolbox\Editor\OpenAI;
use Schoolbox\User;
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
     * The route for image generation
     * Avoiding passing through the route generator here
     */
    private const IMAGE_GENERATION_ROUTE = '/openai/image';


    /**
     * @param bool   $multi
     * @param string $mimeFilter
     * @param ?IConfigLoader $conf
     * @param ?User   $user
     * @param bool   $useFolder
     * @param int    $defaultFolderId
     * @throws OutOfRangeException
     */
    public function __construct(
        $multi,
        $mimeFilter = null,
        private ?IConfigLoader $conf = null,
        private ?User $user = null,
        $useFolder = true,
        $defaultFolderId = 0
    ) {
        /* when $mimeFilter is null, it's valid */
        if ($mimeFilter !== null && !$this->allowsImage($mimeFilter)) {
            throw new OutOfRangeException("mimeType is not an image.");
        }

        /* is AI image generation enabled? */
        $endpoint = $this->conf->get(ConfigMapper::NAME_OPENAI_IMAGE_ENDPOINT);
        if (OpenAI\Helper::ENDPOINT_TYPE_OFF === $endpoint) {
            throw new OutOfRangeException("Image generation is not enabled.");
        }

        /* only staff can access this */
        if (!$this->user || !$user->getRole()->isStaff()) {
            throw new OutOfRangeException("Image generation is not available for this user.");
        }

        parent::__construct($multi, $mimeFilter, $useFolder, $defaultFolderId);
    }

    /**
     * checks to see if the mimetype allows an image
     * @param string $mimeFilter
     * @return bool
     */
    protected function allowsImage(string $mimeFilter): bool
    {
        $csv = str_getcsv($mimeFilter, escape: '');
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

        $page->setVar(
            array(
                'MAX_FILES'      => self::MAX_FILES,
                'MAX_FILE_SIZE'  => $this->conf->get(ConfigMapper::NAME_MAX_FILE_SIZE),
                'ACCEPTED_FILES' => $mime,
                'PROMPT_ATTRIBUTES' => Scrub::text(json_encode(OpenAI\Payload\ImageGeneration::getAttributes())),
                'GENERATE_ROUTE'    => self::IMAGE_GENERATION_ROUTE,
            ),
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
