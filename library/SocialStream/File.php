<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace SocialStream;

use Schoolbox\Resource\Storage\Model;

/**
 * Class File
 *
 * @package SocialStream
 */
class File
{

    /**
     * The url to curl, to obtain an file
     */
    private $url = '';


    /**
     * The owner of the file
     */
    private $ownerId = -1;


    /**
     * An instance of Schoolbox\File
     *
     * @var null
     */
    private $savedFile = null;


    /**
     * Constructor
     *
     * Finds the file at url $url
     *
     * @param string $url
     * @param int $ownerId
     */
    public function __construct($url, $ownerId)
    {
        $this->setUrl($url);
        $this->setOwnerId($ownerId);
    }


    /**
     * Save image
     */
    public function saveImage()
    {
        $brownhole = tmpfile();

        $curl = new \Alaress_Curl_Url($this->getUrl());

        $curl->addOptions(array(
            CURLOPT_FILE => $brownhole,
        ));

        $curl->exec();

        $metadata = stream_get_meta_data($brownhole);

        if (file_exists($metadata['uri'])) {
            $savedFile = \Schoolbox\Resource\Storage\Mapper::getInstance()->accept(basename($this->getUrl()), $metadata['uri']);

            $this->setSavedFile($savedFile);
        }

        fclose($brownhole);
    }

    /* Getters/Setters
     */

    /**
     * Set owner id
     *
     * @param int $ownerId
     */
    public function setOwnerId($ownerId)
    {
        $this->ownerId = $ownerId;
    }

    /**
     * Get owner id
     *
     * @return int
     */
    public function getOwnerId()
    {
        return $this->ownerId;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Get url
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set saved file
     *
     * @param Model $savedFile
     */
    public function setSavedFile($savedFile)
    {
        $this->savedFile = $savedFile;
    }

    /**
     * Get saved file
     *
     * @return Model
     */
    public function getSavedFile()
    {
        return $this->savedFile;
    }
}
