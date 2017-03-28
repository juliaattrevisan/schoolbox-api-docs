<?php

/**
 * Document
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */
namespace Schoolbox\Learning\InlineGrader;

use \Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use \Schoolbox\Resource\Storage\Model as Storage;
use \Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Mapper as SwampMapper;

class Document
{

    /**
     * @var SubmissionFile
     */
    private $file;

    /**
     * Contains API keys
     *
     * @var Authenticator
     */
    private $authenticator = null;

    /**
     * The url of the file to upload
     *
     * @var string
     */
    private $url = '';

    /**
     * The url to upload to
     *
     * @var string
     */
    private $uploadUrl = 'https://crocodoc.com/api/v2/document/upload';

    /**
     * Document constructor.
     *
     * @param SubmissionFile $file
     */
    public function __construct(SubmissionFile $file)
    {
        $this->setFile($file);
        $this->setAuthenticator(new Authenticator());

        /** @var Storage $storage */
        $storage     = $this->getFile()->getStorage();
        $destination = \Cfg::get('submission_upload_tmp_dir') . '/' . $this->getFile()->getId() . '_' . $storage->getBasename();

        // copy the file contents to the temp directory
        if ($storage->exists() && $fp = fopen($destination, 'w')) {
            $input = $storage->readStream();
            while($chunk = fgets($input, 1024)) {
                fwrite($fp, $chunk);
            }

            $this->setUrl($destination);
            fclose($fp);
        }
    }

    /**
     * Upload file to crocodoc
     */
    public function upload()
    {

        /* Unlink copied file after uploading but only if it exists
         * it might not, if it's been blown away from storage via gc
         */
        if (!file_exists($this->getUrl())) {
            return [
                'result'       => null,
                'errors'       => 'Missing file',
                'responseCode' => null,
            ];
        }

        /* Otherwise continue as intended
         */
        $curl = new \Alaress_Curl_Url($this->getUploadUrl());

        $postfields = array(
            'token' => $this->getAuthenticator()->getApiToken(),
            'file' => new \CurlFile($this->getUrl(), $this->getFile()->getMimetype()->getMime())
        );

        $curl->addOptions(
            array(
                CURLOPT_HTTPHEADER => array('Content-Type: multipart/form-data'),
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $postfields,
                CURLOPT_FOLLOWLOCATION => 1,
                CURLOPT_TIMEOUT_MS => 240000,
            )
        );

        $curl->exec();

        unlink($this->getUrl());

        // Crocodoc-related errors, when a file dies in crocodoc
        $result = json_decode($curl->getResult());

        if (isset($result->uuid)) {
            $this->getFile()->setCrocodocId($result->uuid);

            /* TODO Maybe one day there'll be a nicer way than this
             *
             * Save crocodocok uuid
             *
             * Quick "translation" into the swamp because there isn't a MapperProxy
             * equivalent that saves individual Assessment/Duework/Submission/File/Models
             */
            try {
                $file = SwampMapper::getInstance()->findById($this->getFile()->getId());
                $file->setUuid($result->uuid);

                SwampMapper::getInstance()->save($file);
            }
            catch (\Exception $e) {
                return [
                    'result'       => $result,
                    'errors'       => 'Submission file does not exist',
                    'responseCode' => $curl->getResponseCode(),
                ];
            }
        }

        return [
            'result'       => $result,
            'errors'       => isset($result->error) ? $result->error : implode(', ', $curl->getErrors()),
            'responseCode' => $curl->getResponseCode(),
        ];
    }

    /* Getters/Setters
     */

    /**
     * @return Authenticator
     */
    private function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * @param Authenticator $authenticator
     * @return $this
     */
    private function setAuthenticator($authenticator)
    {
        $this->authenticator = $authenticator;
        return $this;
    }

    /**
     * @return SubmissionFile
     */
    private function getFile()
    {
        return $this->file;
    }

    /**
     * @param SubmissionFile $file
     * @return $this
     */
    private function setFile($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return $this
     */
    private function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Get url
     *
     * @return string
     */
    private function getUrl()
    {
        return $this->url;
    }

    /**
     * Set upload url
     *
     * @param string $uploadUrl
     * @return $this
     */
    private function setUploadUrl($uploadUrl)
    {
        $this->uploadUrl = $uploadUrl;
        return $this;
    }

    /**
     * Get upload url
     *
     * @return string
     */
    private function getUploadUrl()
    {
        return $this->uploadUrl;
    }
}