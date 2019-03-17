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
     * The type of SocialStream Post to manufacture
     * one of ["image", "string", "url", "video"]
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
     * @throws \UnexpectedValueException
     */
    public function produce(array $data = null): \SocialStream_Post_Abstract
    {
        switch ($this->type) {
            case \SocialStream_Post_Image::TYPE:
                return new \SocialStream_Post_Image();

            case \SocialStream_Post_Url::TYPE:
                return new \SocialStream_Post_Url();

            case \SocialStream_Post_Video::TYPE:
                return new \SocialStream_Post_Video();

            /* If it was not a video, link to the internet, then assume it's
             * just a plain old text message
             */
            default:
                return new \SocialStream_Post_String();
        }
    }
}