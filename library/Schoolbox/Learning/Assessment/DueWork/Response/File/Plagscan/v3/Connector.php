<?php
/**
 * Connector
 *
 * API v3.0 Connector for Plagscan
 * https://api.plagscan.com/v3/
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Schoolbox\IConfigLoader;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\ValidFiles;

class Connector
{

    /* Sorry, Plagscan does not have any names for these reports.
     *
     * In fact, they are officially known as the "new style" and "latest style"
     * which is a nomenclature crime against humanity
     *
     * 6 Retrieve new style (interactive) HTML document with annotations
     * 7 Retrieve latest report type (with formatting and images) - returns URL (link gives temporary access for one hour)
     */

    /**
     * An int for a data struct that contains the percentage plagiarised
     */
    const PLAGSCAN_MODE_SCORE = 0;

    /**
     * An int for a data struct that contains the old Mode 6 report
     */
    const PLAGSCAN_MODE_REPORT = 6;

    /**
     * An int for a data struct that contains the new Mode 7 report
     */
    const PLAGSCAN_MODE_INTERACTIVE = 7;


    /**
     * Maximum time that we allow plagscan to upload files
     */
    const PLAGSCAN_TIMEOUT = 360;

    /**
     * Maximum size of files allowed for submission: 100MB
     * @see http://blog.plagscan.com/plagscan-updates-possible-uploading-file-size/
     */
    const PLAGSCAN_FILESIZE_LIMIT = 104857600;

    /**
     * Api url where stuff is sent
     *
     * @var string
     */
    private $apiUrl = 'https://api.plagscan.com/v3/';

    /**
     * A secret hash that Plagscan generates from your
     * Plagscan account somehow. AccessTokenGenerator
     * instances spawns it
     *
     * @var AccessTokenGenerator
     */
    private $accessTokenGenerator;

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
     * Connector constructor.
     *
     * @param IConfigLoader $configLoader
     * @param int           $plagscanClientId
     * @param string        $plagscanApiKey
     */
    public function __construct(IConfigLoader $configLoader, $plagscanClientId, $plagscanApiKey)
    {
        $this->setConfigLoader($configLoader);
        $this->setValidFiles(new ValidFiles());

        $this->setAccessTokenGenerator(new AccessTokenGenerator($plagscanClientId, $plagscanApiKey));
    }

    /**
     * Transmit a document to Plagscan
     *
     * @param SubmissionFile $submissionFile
     * @param string         $filename
     * @return int
     * @throws \RuntimeException if Plagscan is unavailable
     * @throws \InvalidArgumentException if Plagscan changed their API on us
     * @throws \DomainException if some other unexpected thing went wrong
     */
    public function transmit(SubmissionFile $submissionFile, $filename)
    {
        /* Check valid extensions and die early if trying
         * to analyse an illegal file type
         */
        if (!$this->getValidFiles()->isPlagscannable($submissionFile->getBasename())) {
            throw new \DomainException('Invalid file type');
        }

        // Plagscan only allows files that smaller than 100MB
        if ($submissionFile->getSize() > self::PLAGSCAN_FILESIZE_LIMIT) {
            throw new \DomainException('File is too large');
        }

        $filename = $this->makeFileName($submissionFile, $filename);

        $postData = [
            [
                "name"     => "fileUpload",
                "contents" => $submissionFile->getStorage()->readStream(),
                "filename" => $filename,
            ],
        ];

        try {
            $client = new Client([
                "base_uri"    => $this->getApiUrl(),
                "timeout"     => self::PLAGSCAN_TIMEOUT,
                "http_errors" => false,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not transmit file.');
        }

        $response = $client->post("documents?access_token={$this->getAccessTokenGenerator()->generate()}", [
            "multipart" => $postData,
        ]);

        /* What was the outcome?
         */
        $content = json_decode($response->getBody(), true);

        if ($response->getStatusCode() == 201) {
            if (!(array_key_exists('data', $content) && array_key_exists('docID', $content['data']))) {
                throw new \InvalidArgumentException(
                    "response[data][docID] does not exist: PlagScan has changed their API again"
                );
            }
            return $content['data']['docID'];
        }

        /* If you ended up here, the document can't be transmitted for various reasons
         * so just shove them out for logging purposes
         */
        throw new \RuntimeException("Error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Create a filename that will be sent to plagscan to identify the file
     *
     * @param SubmissionFile $submissionFile
     * @param                $filename
     * @return string
     */
    private function makeFileName(SubmissionFile $submissionFile, $filename)
    {
        $documentName = $submissionFile->getStorage()->getFilename();
        $documentExt = $submissionFile->getStorage()->getExtension();

        // Sanitise the file name to ensure valid characters and length
        $name = preg_replace('/[^A-Za-z0-9_\-\. ]/', '',$filename.'_'.$documentName);
        $name = mb_strcut($name, 0, 255 - ($documentExt ? \strlen($documentExt) + 1 : 0));
        $fullName = $name.'.'.$documentExt;

        return $fullName;
    }

    /**
     * Analyse a document that we sent to plag
     *
     * @param int $plagscanId
     * @return bool
     * @throws \RuntimeException if Plagscan is unavailable
     * @throws \DomainException if some unexpected thing went wrong
     */
    public function analyse($plagscanId)
    {
        if (!$plagscanId) {
            throw new \DomainException('No plagscan document id - document has not been transmitted yet');
        }

        $postData = [
            'access_token' => $this->getAccessTokenGenerator()->generate(),
        ];

        $client = new Client([
            "base_uri"    => $this->getApiUrl(),
            "timeout"     => self::PLAGSCAN_TIMEOUT,
            "http_errors" => false,
        ]);

        try {
            $response = $client->put("documents/{$plagscanId}/check", [
                'form_params' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not analyse file.');
        }

        /* What was the outcome?
         */
        $content = json_decode($response->getBody(), true);

        /* As far as I know, the only success state is statusCode 204
         */
        if ($response->getStatusCode() == 204) {

            /* noop: need to wait for plagscan to do its thing
             */
            return false;
        } elseif ($response->getStatusCode() == 400) {

            /* This document has already been checked
             * Get the latest version of the report and cache it locally
             * Sadly must check error strings, because plagscan
             */
            if ($content['error']['message'] == 'The document is already checked') {
                return true;
            }
            if ($content['error']['message'] == 'This document has a report already') {
                return true;
            }
            if ($content['error']['message'] == 'The document has been deleted.') {
                throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");
            }
            /* Otherwise document is one of the following and we don't need
             * to take any further action at the moment
             *
             * - in the queue to be checked
             * - currently being checked
             */
            return false;
        }

        /* If you ended up here, the document can't be analysed for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Fetch report contents
     *
     * This is the Mode 7 Interactive Report that v3.0 api introduced
     *
     * @param int $plagscanId
     * @return string
     * @throws \InvalidArgumentException if Plagscan changed their API on us
     * @throws \DomainException if some other unexpected thing went wrong
     * @throws \RuntimeException if Plagscan is unavailable
     */
    public function fetchInteractiveReportUrl($plagscanId)
    {
        $response = $this->retrieve($plagscanId, self::PLAGSCAN_MODE_INTERACTIVE);

        $content = json_decode($response->getBody(), true);

        /* Save the report url
         */
        if ($response->getStatusCode() == 200) {
            if (!(array_key_exists('data', $content) && array_key_exists('reportLink', $content['data']))) {
                throw new \InvalidArgumentException(
                    "response[data][reportLink] does not exist: PlagScan has changed their API again"
                );
            }
            return $content['data']['reportLink'];
        }

        /* If you ended up here, the document can't be reported for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Fetch report contents
     *
     * This is the old Mode 6 Report which existed prior to v3.0 api
     *
     * @param int $plagscanId
     * @return string
     * @throws \DomainException if some unexpected thing went wrong
     */
    public function fetchReport($plagscanId)
    {
        $response = $this->retrieve($plagscanId, self::PLAGSCAN_MODE_REPORT);

        /* Save the report url
         */
        if ($response->getStatusCode() == 200) {
            return $response->getBody();
        }

        $content = json_decode($response->getBody(), true);

        /* If you ended up here, the document can't be reported for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Have to get the score separately -_-
     *
     * @param $plagscanId
     * @return string
     * @throws \InvalidArgumentException if Plagscan changed their API on us
     * @throws \DomainException if some other unexpected thing went wrong
     */
    public function fetchScore($plagscanId)
    {
        $response = $this->retrieve($plagscanId, self::PLAGSCAN_MODE_SCORE);

        $content = json_decode($response->getBody(), true);

        if ($response->getStatusCode() == 200) {
            if (!(array_key_exists('data', $content) && array_key_exists('plagLevel', $content['data']))) {
                throw new \InvalidArgumentException(
                    "response[data][plagLevel] does not exist: PlagScan has changed their API again"
                );
            }
            /* ...really - is a shame it's not synonymous with fetchReport()
             */
            return $content['data']['plagLevel'];
        }

        /* If you ended up here, the document can't be reported for various reasons
         * so just shove them out for logging purposes
         */
        throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Retrieve report data for $plagscanId in mode selection integer $mode
     *
     * $mode is one of the following:
     *
     * 0 Retrieve statistics only, such as plagiarism level, number of words, wait etc. (n.b.: wait is in ms, the
     * report timestamp - creation timestamp, so a negative value indicates that the analysis has not started at all or
     * finished yet.)
     * 1 Retrieve document's plain text and report links; to list of possible plagiarism sources and in-document view
     * of possible plagiarism sources; e.g. https://www.plagscan.com/view?6055
     * 2 Retrieve XML with data on all possible plagiarism sources
     * 3 Retrieve annotated Docx document (if available, depending on user configuration)
     * 4 Retrieve HTML document with annotations
     * 5 Retrieve HTML report of matches sorted by relevance
     * 6 Retrieve new style (interactive) HTML document with annotations
     * 7 Retrieve latest report type (with formatting and images) - returns URL (link gives temporary access for one
     * hour)
     * 8 PDF version of HTML document with annotations
     * 9 PDF version of HTML report of matches sorted by relevance
     *
     * @param int $plagscanId
     * @param int $mode
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \RuntimeException if Plagscan is unavailable
     */
    private function retrieve($plagscanId, $mode)
    {
        $postData = [
            'access_token' => $this->getAccessTokenGenerator()->generate(),
            'mode'         => $mode,
        ];

        $client = new Client([
            "base_uri"    => $this->getApiUrl(),
            "timeout"     => self::PLAGSCAN_TIMEOUT,
            "http_errors" => false,
        ]);

        try {
            $response = $client->get("documents/{$plagscanId}/retrieve", [
                'query' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not retrieve report.');
        }

        return $response;
    }

    /**
     * Delete a document from the plagscan account
     *
     * This prevents it being used to compare against future documents
     * Used when resubmitting one's own file again
     *
     * @param $plagscanId
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \RuntimeException if Plagscan is unavailable
     */
    public function delete($plagscanId)
    {
        $postData = [
            'access_token' => $this->getAccessTokenGenerator()->generate(),
        ];

        $client = new Client([
            "base_uri"    => $this->getApiUrl(),
            "timeout"     => self::PLAGSCAN_TIMEOUT,
            "http_errors" => false,
        ]);

        try {
            $response = $client->delete("documents/{$plagscanId}", [
                'query' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not delete document.');
        }

        return $response;
    }

    /**
     * Privatise a document in the plagscan account
     *
     * This prevents it being used to compare against future documents
     * Used when resubmitting one's own file again
     *
     * @param $plagscanId
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \RuntimeException if Plagscan is unavailable
     */
    public function privatise($plagscanId)
    {
        $postData = [
            'access_token' => $this->getAccessTokenGenerator()->generate(),
        ];

        $client = new Client([
            "base_uri"    => $this->getApiUrl(),
            "timeout"     => self::PLAGSCAN_TIMEOUT,
            "http_errors" => false,
        ]);

        try {
            $response = $client->put("documents/{$plagscanId}/private", [
                'query' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not privatise document.');
        }

        return $response;
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

    /**
     * @return AccessTokenGenerator
     */
    private function getAccessTokenGenerator()
    {
        return $this->accessTokenGenerator;
    }

    /**
     * @param AccessTokenGenerator $accessTokenGenerator
     * @return $this
     */
    private function setAccessTokenGenerator($accessTokenGenerator)
    {
        $this->accessTokenGenerator = $accessTokenGenerator;
        return $this;
    }
}
