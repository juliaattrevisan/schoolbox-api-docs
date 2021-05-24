<?php

/**
 * SocialStream_Post_Url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class SocialStream_Post_Image extends SocialStream_Post_Abstract
{

    /**
     * @var string
     */
    public const TYPE = 'image';

    /**
     * SocialStream_Post_Image constructor.
     *
     * @throws UnexpectedValueException
     */
    public function __construct()
    {
        parent::__construct();

        $this->type = self::TYPE;
    }

    /**
     * Determine whether a thumbnail was successfully found
     *
     * @return bool
     */
    public function hasThumbnail(): bool
    {
        return '' !== $this->getThumbnail();
    }

    /**
     * Contextually render thumbnail images
     *
     * @param string   $context
     * @param SitePage $page
     * @return bool
     */
    public function renderThumbnail(string $context, SitePage $page): bool
    {

        /* Determine which context to render blocks
         * Display a mime icon
         * Otherwise display thumbnail via image transform
         */
        $returnValue = false;

        if ($context === 'post') {
            if ($this->hasThumbnail()) {
                $page->parse('socialstream_post_has_image_block', 'socialstream_post_has_image');
                $returnValue = true;
            }
        } elseif ($context === 'comment') {
            if ($this->hasThumbnail()) {
                $page->parse('socialstream_comment_has_image_block', 'socialstream_comment_has_image');
                $returnValue = true;
            }
        } elseif ($context === 'reply') {
            if ($this->hasThumbnail()) {
                $page->parse('socialstream_reply_has_image_block', 'socialstream_reply_has_image');
                $returnValue = true;
            }
        }

        return $returnValue;
    }
}
