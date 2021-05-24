<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

/**
 * Class SocialStream_Post_Video
 */
class SocialStream_Post_Video extends SocialStream_Post_Abstract
{

    /**
     * @var string
     */
    public const TYPE = 'video';


    /**
     * SocialStream_Post_Video constructor.
     *
     * @throws UnexpectedValueException
     */
    public function __construct()
    {
        parent::__construct();

        $this->type = self::TYPE;
    }


    /**
     * Never render metadata for videos
     *
     * @param \Alaress_Curl_Url $curl
     */
    public function spawnMetadata($curl)
    {
    }


    /**
     * Contextually render thumbnail images
     *
     * @param string   $context
     * @param SitePage $page
     * @return bool
     */
    public function renderThumbnail(string $context, SitePage $page)
    {
        $chain = new \SocialStream\Post\Player\Chain($this->getUrl());

        /* If it was interpreted, embed that player
         */
        if (null !== $chain->getResult()) {
            if ($context === 'post') {
                $page->setVar(array(
                    'SOCIALSTREAM_POST_URL'            => Scrub::text($chain->getResult()),
                    'SOCIALSTREAM_POST_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()),
                    'SOCIALSTREAM_POST_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
                ));

                /* Never parse metadata for videos
                 */
                $page->setVar(array(
                    'socialstream_post_has_metadata_block' => ''
                ));

                if ('' !== $chain->getResult()) {
                    $page->parse('socialstream_post_has_player_block', 'socialstream_post_has_player');

                    return true;
                }

                $page->setVar(array(
                    'socialstream_post_has_player_block' => ''
                ));
            } elseif ($context === 'comment') {
                $page->setVar(array(
                    'SOCIALSTREAM_COMMENT_URL'            => Scrub::text($chain->getResult()),
                    'SOCIALSTREAM_COMMENT_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()),
                    'SOCIALSTREAM_COMMENT_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
                ));

                /* Never parse metadata for videos
                 */
                $page->setVar(array(
                    'socialstream_comment_has_metadata_block' => ''
                ));

                if ('' !== $chain->getResult()) {
                    $page->parse('socialstream_comment_has_player_block', 'socialstream_comment_has_player');

                    return true;
                }

                $page->setVar(array(
                    'socialstream_comment_has_player_block' => ''
                ));
            } elseif ($context === 'reply') {
                $page->setVar(array(
                    'SOCIALSTREAM_REPLY_URL'            => Scrub::text($chain->getResult()),
                    'SOCIALSTREAM_REPLY_EXTERNAL_TITLE' => Scrub::text($this->getExternalTitle()),
                    'SOCIALSTREAM_REPLY_EXTERNAL_BODY'  => Scrub::text($this->getExternalBody()),
                ));

                /* Never parse metadata for videos
                 */
                $page->setVar(array(
                    'socialstream_reply_has_metadata_block' => ''
                ));

                if ('' !== $chain->getResult()) {
                    $page->parse('socialstream_reply_has_player_block', 'socialstream_reply_has_player');

                    return true;
                }

                $page->setVar(array(
                    'socialstream_reply_has_player_block' => ''
                ));
            }
        }

        // Do a video chaining thing
        $chain  = new \SocialStream\Post\Video\Chain($this->getUrl());
        $result = $chain->getResult();
        if (!empty($result)) {
            $prefix = null;
            if ($context == 'post') {
                $prefix = 'SOCIALSTREAM_POST_VIDEO';
            } elseif ($context == 'comment') {
                $prefix = 'SOCIALSTREAM_COMMENT_VIDEO';
            } elseif ($context == 'reply') {
                $prefix = 'SOCIALSTREAM_REPLY_VIDEO';
            }
            if (isset($prefix)) {
                $page->setVar(
                    array(
                        $prefix . '_POSTER' => Scrub::text($result['poster']),
                        $prefix . '_SRC'    => Scrub::text($result['src']),
                        $prefix . '_STATUS' => Scrub::text(strtolower($result['status'])),
                    )
                );
                if ($result['status'] === 'completed') {
                    $page->parse("socialstream_{$context}_has_video_block", "socialstream_{$context}_has_video");
                } elseif ($result['status'] !== 'failed') {
                    $page->parse("socialstream_{$context}_has_video_pending_block", "socialstream_{$context}_has_video_pending");
                }
                return true;
            }
        }

        return false;
    }
}
