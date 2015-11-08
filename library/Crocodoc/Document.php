<?php
/**
 * Document
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Crocodoc;

use Schoolbox\Assessment\Component\SubmissionBox\Submission;
use Schoolbox\Resource\Storage\Model;

class Document
{

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
     * The student submission containing the file we wish to preview
     *
     * @var Submission\File
     */
    private $submission = null;

    /**
     * The url to upload to
     *
     * @var string
     */
    private $uploadUrl = 'https://crocodoc.com/api/v2/document/upload';

    /**
     * Constructor
     *
     * @param Submission\File $submission
     */
    public function __construct(Submission\File $submission)
    {
        $this->setAuthenticator(new Authenticator());
        $this->setSubmission($submission);

        /** @var Model $storage */
        $storage     = $submission->getStorage();
        $destination = \Cfg::get('submission_upload_tmp_dir') . '/' . $this->getSubmission()->getId() . '_' . $this->getSubmission()->getStorage()->getBasename();

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
     * Upload a file to crocodoc
     */
    public function upload()
    {
        $curl = new \Alaress_Curl_Url($this->getUploadUrl());

        $postfields = array(
            'token' => $this->getAuthenticator()->getApiToken(),
            'file' => new \CurlFile($this->getUrl(), $this->getSubmission()->getMimetype()->getMime())
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

        /* Unlink copied file after uploading
         */
        unlink($this->getUrl());

        // Crocodoc-related errors, when a file dies in crocodoc
        $result = json_decode($curl->getResult());

        if (isset($result->uuid)) {
            /* Save reference to uploaded file */
            $this->getSubmission()->setUuid($result->uuid);
            Submission\FileMapper::getInstance()->save($this->getSubmission());
        }

        return array(
            'result'       => $result,
            'errors'       => isset($result->error) ? $result->error : implode(', ',$curl->getErrors()),
            'responseCode' => $curl->getResponseCode(),
        );
    }

    /* Getters/Setters
     */

    /**
     * Set authenticator
     *
     * @param Authenticator $authenticator
     */
    private function setAuthenticator(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;
    }

    /**
     * Get authenticator
     *
     * @return Authenticator
     */
    private function getAuthenticator()
    {
        return $this->authenticator;
    }

    /**
     * Set url
     *
     * @param string $url
     */
    private function setUrl($url)
    {
        $this->url = $url;
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
     */
    private function setUploadUrl($uploadUrl)
    {
        $this->uploadUrl = $uploadUrl;
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

    /**
     * Set submission
     *
     * @param Submission\File $submission
     */
    private function setSubmission(Submission\File $submission)
    {
        $this->submission = $submission;
    }

    /**
     * Get submission
     *
     * @return Submission\File
     */
    private function getSubmission()
    {
        return $this->submission;
    }
}