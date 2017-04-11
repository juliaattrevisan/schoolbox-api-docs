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
        $documentName = $submissionFile->getBasename();

        /** @var \Schoolbox\Resource\Storage\Model $file */
        $file = $submissionFile->getStorage();

        $postData = [[
            'name' => $documentName,
            'contents' => fopen($this->getConfigLoader()->get('storage_dir') . '/' . $file->getPath(), 'r')
        ]];

        $client = new Client([
            'base_uri' => $this->getApiUrl(),
            'timeout' => 20.0,
            'http_errors' => false
        ]);

        $response = $client->post("documents/?access_token={$this->getAccessToken()}", [
            "multipart" => $postData
        ]);

        if ($response->getStatusCode() == 201) {
            $content = json_decode($response->getBody(), true);
            $data    = $content["data"];

            echo "ID: " . $data["docID"] . "\n";
            echo "Location: " . $content["location"] . "\n";
        }
        else {
            echo $response->getBody();
        }
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