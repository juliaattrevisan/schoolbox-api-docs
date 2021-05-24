<?php

/**
 * SocialStream_Post_String
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

class SocialStream_Post_String extends SocialStream_Post_Abstract
{

    /**
     * @var string
     */
    public const TYPE = 'string';

    /**
     * SocialStream_Post_String constructor.
     *
     * @throws UnexpectedValueException
     */
    public function __construct()
    {
        parent::__construct();

        $this->type = self::TYPE;
    }

    /**
     * Nothing to render here
     *
     * @param string   $context
     * @param SitePage $page
     * @return mixed|void
     */
    public function renderThumbnail(string $context, SitePage $page)
    {
    }
}
