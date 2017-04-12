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
use Zend\Stdlib\Exception\DomainException;

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
     * @throws DomainException
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
     * @return int
     * @throws DomainException
     */
    public function transmit(SubmissionFile $submissionFile)
    {
        /* Check valid extensions and die early if trying
         * to analyse an illegal file type
         */
        if (!$this->getValidFiles()->isPlagscannable($submissionFile->getBasename())) {
            throw new \DomainException('Invalid file type');
        }

        $filepath = $this->makeTempFile($submissionFile);

        if ($filepath === null) {
            throw new \DomainException('Could not create temp file');
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
        $content = json_decode($response->getBody());

        if ($response->getStatusCode() == 201) {
            return $content->data->docID;
        }

        /* If you ended up here, the document can't be transmitted for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content->error->code}: {$content->error->message}");
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

    /**
     * Analyse a document that we sent to plag
     *
     * @param $plagscanId
     * @return bool
     * @throws DomainException
     */
    public function analyse($plagscanId)
    {
        if (!$plagscanId) {
            throw new \DomainException('No plagscan document id - document has not been transmitted yet');
        }

        $postData = [
            'access_token' => $this->getAccessToken(),
        ];

        $client = new Client([
            "base_uri" => $this->getApiUrl(),
            "timeout" => 20.0,
            "http_errors" => false
        ]);

        $response = $client->put("documents/{$plagscanId}/check", [
            'form_params' => $postData
        ]);

        /* What was the outcome?
         */
        $content = json_decode($response->getBody());

        /* As far as I know, the only success state is statusCode 204
         */
        if ($response->getStatusCode() == 204) {

            /* noop: need to wait for plagscan to do its thing
             */
        }
        elseif ($response->getStatusCode() == 400) {

            /* This document has already been checked
             * Get the latest version of the report and cache it locally
             * Sadly must check error strings, because plagscan
             */
            if ($content->error->message == 'The document is already checked') {
                return true;
            }

            /* Otherwise document is one of the following and we don't need
             * to take any further action at the moment
             *
             * - in the queue to be checked
             * - currently being checked
             */
        }

        /* If you ended up here, the document can't be analysed for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content->error->code}: {$content->error->message}");
    }

    /**
     * Fetch report contents. I formatted their crappy paragraph for ease of use
     *
     * 0 Retrieve statistics only, such as plagiarism level, number of words, wait etc. (n.b.: wait is in ms, the report timestamp - creation timestamp, so a negative value indicates that the analysis has not started at all or finished yet.)
     * 1 Retrieve document's plain text and report links; to list of possible plagiarism sources and in-document view of possible plagiarism sources; e.g. https://www.plagscan.com/view?6055
     * 2 Retrieve XML with data on all possible plagiarism sources
     * 3 Retrieve annotated Docx document (if available, depending on user configuration)
     * 4 Retrieve HTML document with annotations
     * 5 Retrieve HTML report of matches sorted by relevance
     * 6 Retrieve new style (interactive) HTML document with annotations
     * 7 Retrieve latest report type (with formatting and images) - returns URL (link gives temporary access for one hour)
     * 8 PDF version of HTML document with annotations
     * 9 PDF version of HTML report of matches sorted by relevance
     *
     * @param $plagscanId
     * @return string
     * @throws DomainException
     */
    public function fetchReport($plagscanId)
    {
        $postData = [
            'access_token' => $this->getAccessToken(),
            'mode' => 6
        ];

        $client = new Client([
            "base_uri" => $this->getApiUrl(),
            "timeout" => 20.0,
            "http_errors" => false
        ]);

        $response = $client->get("documents/{$plagscanId}/retrieve", [
            'query' => $postData
        ]);

        $content = json_decode($response->getBody());

        if ($response->getStatusCode() == 200) {
            return $response->getBody();
        }

        /* If you ended up here, the document can't be reported for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content->error->code}: {$content->error->message}");
    }

    /**
     * Have to et the score separately -_-
     *
     * @param $plagscanId
     * @return string
     * @throws DomainException
     */
    public function fetchScore($plagscanId)
    {
        $postData = [
            'access_token' => $this->getAccessToken(),
            'mode' => 0
        ];

        $client = new Client([
            "base_uri" => $this->getApiUrl(),
            "timeout" => 20.0,
            "http_errors" => false
        ]);

        $response = $client->get("documents/{$plagscanId}/retrieve", [
            'query' => $postData
        ]);

        $content = json_decode($response->getBody());

        if ($response->getStatusCode() == 200) {
            return $content->data->level;
        }

        /* If you ended up here, the document can't be reported for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content->error->code}: {$content->error->message}");
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