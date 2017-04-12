<?php
/**
 * WarpGateway
 *
 * API v3.0 Gateay for Plagscan
 * https://api.plagscan.com/v3/
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\v3;

use Schoolbox\IConfigLoader;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Plagscan\ValidFiles;
use Schoolbox\Learning\Assessment\DueWork\Submission\File\Model as SubmissionFile;
use GuzzleHttp\Client;

class WarpGate
{

    /**
     * Api url where stuff is sent
     *
     * @var string
     */
    private $apiUrl = 'https://api.plagscan.com/v3/';

    /**
     * A secret hash that Plagscan generates from your
     * Plagscan account somehow
     *
     * @var string
     */
    private $accessToken = '';

    /**
     * A registry of allowed extension-mime tuples
     *
     * @var ValidFiles
     */
    private $validFiles;

    /**
     * A ConfigLoader instance
     *
     * @var IConfigLoader
     */
    private $configLoader;

    /**
     * WarpGate constructor.
     *
     * @param $configLoader
     * @param $accessToken
     */
    public function __construct(IConfigLoader $configLoader, $accessToken)
    {
        $this->setConfigLoader($configLoader);
        $this->setAccessToken($accessToken);
        $this->setValidFiles(new ValidFiles());
    }

    /**
     * Transmit a document to Plagscan
     *
     * @param SubmissionFile $submissionFile
     */
    public function transmit(SubmissionFile $submissionFile)
    {
        $filepath = $this->makeTempFile($submissionFile);

        if ($filepath === null) {
            throw new \DomainException("Could not create temp file");
        }

        /* Temp file created, ready to transmit
         */
        $filepointer = fopen($filepath, 'r');

        $postData = [[
            "name" => "fileUpload",
            "contents" => $filepointer
        ]];

        $client = new Client([
            "base_uri" => $this->getApiUrl(),
            "timeout" => 20.0,
            "http_errors" => false
        ]);

        $response = $client->post("documents/?access_token={$this->getAccessToken()}", [
            "multipart" => $postData
        ]);

        /* What was the outcome?
         */
        $content = json_decode($response->getBody(), true);

        if ($response->getStatusCode() == 201){
            $data = $content["data"];

            return $data['docID'];
        }

        throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Move $submissionFile data to a temp location for upload
     *
     * @param SubmissionFile $submissionFile
     * @return null|string
     */
    private function makeTempFile(SubmissionFile $submissionFile)
    {
        $documentName = $submissionFile->getStorage()->getBasename();

        /* Shank the file into a temp dir; the file extension must be included
         */
        $destination = \Cfg::get('plagscan_upload_tmp_dir') . '/' . $submissionFile->getId() . '_' . $documentName;

        // copy the file contents to the temp directory
        if ($submissionFile->getStorage()->exists() && $fp = fopen($destination, 'w')) {
            $input = $submissionFile->getStorage()->readStream();

            while($chunk = fgets($input, 1024)) {
                fwrite($fp, $chunk);
            }

            fclose($fp);

            return $destination;
        }

        return null;
    }

    /* Getters/Setters
     */

    /**
     * @return IConfigLoader
     */
    private function getConfigLoader()
    {
        return $this->configLoader;
    }

    /**
     * @param IConfigLoader $configLoader
     */
    private function setConfigLoader($configLoader)
    {
        $this->configLoader = $configLoader;
    }

    /**
     * @return string
     */
    private function getApiUrl()
    {
        return $this->apiUrl;
    }

    /**
     * @param string $apiUrl
     */
    private function setApiUrl($apiUrl)
    {
        $this->apiUrl = $apiUrl;
    }

    /**
     * @return string
     */
    private function getAccessToken()
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     */
    private function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    }

    /**
     * @return ValidFiles
     */
    private function getValidFiles()
    {
        return $this->validFiles;
    }

    /**
     * @param ValidFiles $validFiles
     * @return $this
     */
    private function setValidFiles($validFiles)
    {
        $this->validFiles = $validFiles;
        return $this;
    }
}