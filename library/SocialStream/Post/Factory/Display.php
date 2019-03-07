<?php
/**
 * SocialStream_Post_Factory
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace SocialStream\Post\Factory;

class Display extends AbstractFactory {

    /**
     * Const for socialstream posts of type Image, String, Url, Video
     */
    public const SOCIALSTREAM_POST_IMAGE = 'image';
    public const SOCIALSTREAM_POST_STRING = 'string';
    public const SOCIALSTREAM_POST_URL = 'url';
    public const SOCIALSTREAM_POST_VIDEO = 'video';

    /**
     * The type of SocialStream Post to manufacture
     *
     * @var string
     */
    private $type;

    /**
     * Constructor
     *
     * @param $type
     */
    public function __construct(string $type) {
        $this->type = trim($type);
    }

    /**
     * Produce an instance of SocialStream_Post_Abstract subclass
     *
     * @param array $data
     * @return \SocialStream_Post_Abstract
     */
    public function produce(array $data = null): \SocialStream_Post_Abstract
    {
        switch ($this->type) {
            case self::SOCIALSTREAM_POST_IMAGE:
                return new \SocialStream_Post_Image();

            case self::SOCIALSTREAM_POST_URL:
                return new \SocialStream_Post_Url();

            case self::SOCIALSTREAM_POST_VIDEO:
                return new \SocialStream_Post_Video();

            /* If it was not a video, link to the internet, then assume it's
             * just a plain old text message
             */
            default:
                return new \SocialStream_Post_String();
        }
    }
}