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

        /* Load the current icon as set by Admin > Allowed Files because sysadmins can choose their own icons
         * If the filetype is indeterminate, return a default icon rather than nothing
         */
        try {
            $iconFile = MimetypeMapper::getInstance()->getByFilename($this->getExternalTitle())->getIconFile();
            return '/images/mime/' . $iconFile;
        } catch (FileException $e) {
            // noop;
        }

        if(stristr($this->getThumbnail(), 'icon:')) {
            $iconFile = str_replace('icon:', '', $this->getThumbnail());
            return '/images/mime/' . $iconFile;
        }

        if ($this->hasThumbnail()) {
            return "/socialstream/image.php?size={$iconSize}&id=" . $this->getPostId();
        }
        // default icon
        return '/images/mime/application-document.svg';
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
