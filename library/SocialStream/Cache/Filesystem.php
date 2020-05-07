<?php
/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
namespace SocialStream\Cache;

/**
 * Class Filesystem
 *
 * Load images from some arbitrary path on the filesystem
 *
 * @package SocialStream\Cache
 */
class Filesystem extends \Alaress\Image\Cache\BrokenFS
{

    /**
     * Constructor
     *
     * Remembers what image you are trying to load from cache
     *
     * @param string $filename
     * @param string $key
     * @throws \Schoolbox\File\Exception
     */
    public function __construct($filename, $key) {
        parent::__construct(\Config::get('stream_dir') . '/socialstream/thumbnails', $filename, $key);
    }
}
