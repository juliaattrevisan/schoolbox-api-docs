<?php
/**
 * SocialStream_Post_Url
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
use Schoolbox\File\Exception as FileException;
use Schoolbox\File\MimetypeMapper;

class SocialStream_Post_Url extends SocialStream_Post_Abstract {

    /**
     * Determine whether this should display an icon or something
     * via the image transform
     *
     * @param $iconSize
     * @return string
     */
    public function determineIcon($iconSize = 'square64'): string
    {
        $iconDirectory = '/images/mime/';

        /* Default icon as a fallback */
        $icon = $iconDirectory . 'application-document.svg';

        /* Attempt to load the icon for this mimetype as set in Admin > Allowed Files
         * (sysadmins can choose their own icons)
         */
        try {
            $iconFile = MimetypeMapper::getInstance()->getByFilename($this->getExternalTitle())->getIconFile();
            $icon = $iconDirectory . $iconFile;
        } catch (FileException $e) {
            /* Mimetype unknown? Try to load either an icon or a thumbnail image
             * specified in the DB for this social stream post
             */
            if (stristr($this->getThumbnail(), 'icon:')) {
                // Icon
                $iconFile = str_replace('icon:', '', $this->getThumbnail());
                $icon = $iconDirectory . $iconFile;
            } elseif ($this->hasThumbnail()) {
                // Thumbnail image
                $icon = "/socialstream/image.php?size={$iconSize}&id=" . $this->getPostId();
            }
        }

        return $icon;
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
    public function renderThumbnail($context, SitePage $page): bool
    {

        /* Determine which context to render blocks
         * Display a mime icon
         * Otherwise display thumbnail via image transform
         */
        $icon = $this->determineIcon();

        if ($context === 'post') {
            $page->setVar(array(
                'SOCIALSTREAM_POST_URL'            => Scrub::text($this->getUrl()),
                'SOCIALSTREAM_POST_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()) ?: Scrub::text($this->getUrl()),
                'SOCIALSTREAM_POST_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
                'SOCIALSTREAM_POST_ICON' => $icon,
            ));

            /* Parse metadata
              */
            if ('' !== $this->getExternalTitle() || '' !== $this->getExternalBody()) {
                $page->parse('socialstream_post_has_metadata_block', 'socialstream_post_has_metadata');
            }
            $page->parse('socialstream_post_has_icon_block', 'socialstream_post_has_icon');

            return true;
        }
        elseif ($context === 'comment') {
            $page->setVar(array(
                'SOCIALSTREAM_COMMENT_URL'            => Scrub::text($this->getUrl()),
                'SOCIALSTREAM_COMMENT_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()) ?: Scrub::text($this->getUrl()),
                'SOCIALSTREAM_COMMENT_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
                'SOCIALSTREAM_COMMENT_ICON' => $icon,
            ));

            /* Parse metadata
             */
            if ('' !== $this->getExternalTitle() || '' !== $this->getExternalBody()) {
                $page->parse('socialstream_comment_has_metadata_block', 'socialstream_comment_has_metadata');
            }
            $page->parse('socialstream_comment_has_icon_block', 'socialstream_comment_has_icon');

            return true;
        }
        elseif ($context === 'reply') {
            $page->setVar(array(
                'SOCIALSTREAM_REPLY_URL'            => Scrub::text($this->getUrl()),
                'SOCIALSTREAM_REPLY_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()) ?: Scrub::text($this->getUrl()),
                'SOCIALSTREAM_REPLY_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
                'SOCIALSTREAM_REPLY_ICON' => $icon,
            ));

            /* Parse metadata
              */
            if ('' !== $this->getExternalTitle() || '' !== $this->getExternalBody()) {
                $page->parse('socialstream_reply_has_metadata_block', 'socialstream_reply_has_metadata');
            }
            $page->parse('socialstream_reply_has_icon_block', 'socialstream_reply_has_icon');

            return true;
        }

        return false;
    }
}
