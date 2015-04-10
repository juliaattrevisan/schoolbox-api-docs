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
     * An instance of Schoolbox\Assessment\Component\SubmissionBox\Submission
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
        $destination = \Cfg::get('submission_upload_tmp_dir') . '/' . $this->getSubmission()->getId() . '_' . $this->getSubmission()->getFilename();

        // copy the file contents to the temp directory
        if ($fp = fopen($destination, 'w')) {
            if (fwrite($fp, $storage->readStream())) {
                $this->setUrl($destination);
            }
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
            'file' => '@' . $this->getUrl(),
        );

        $curl->addOptions(
            array(
                CURLOPT_HTTPHEADER => array('Content-Type: multipart/form-data'),
                CURLOPT_POST => 1,
                CURLOPT_POSTFIELDS => $postfields,
                CURLOPT_FOLLOWLOCATION => 1,
            )
        );

        $curl->exec();

        /* Unlink copied file after uploading
         */
        unlink($this->getUrl());

        return array(
            'result' => json_decode($curl->getResult()),
            'errors' => $curl->getErrors()
        );
    }

    /* Getters/Setters
     */

    /**
     * Set authenticator
     *
     * @param Authenticator $authenticator
     */
    private function setAuthenticator($authenticator)
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
    private function setSubmission($submission)
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