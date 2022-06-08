<?php

/**
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Proxy\Server;
use Schoolbox\Assessment\Component\SubmissionBox\Submission\SingleFile\Model as SubmissionFile;
use Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\ValidFiles;
use Schoolbox\Resource\Storage\FilesystemProxy;

/**
 * Class Connector
 *
 * API v3.0 Connector for Plagscan
 * https://api.plagscan.com/v3/
 *
 * @package Schoolbox\Learning\Assessment\DueWork\Response\File\Plagscan\v3
 */
class Connector
{
    /**
     * Api url where stuff is sent
     */
    public const API_URL = 'https://api.plagscan.com/v3/';

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
    private const PLAGSCAN_MODE_SCORE = 0;

    /**
     * An int for a data struct that contains the old Mode 6 report
     */
    private const PLAGSCAN_MODE_REPORT = 6;

    /**
     * An int for a data struct that contains the new Mode 7 report
     */
    private const PLAGSCAN_MODE_INTERACTIVE = 7;

    /**
     * Maximum time that we allow plagscan to upload files
     */
    private const PLAGSCAN_TIMEOUT = 360;

    /**
     * Maximum size of files allowed for submission: 100MB
     * @see http://blog.plagscan.com/plagscan-updates-possible-uploading-file-size/
     */
    private const PLAGSCAN_FILESIZE_LIMIT = 104857600;

    /**
     * The Guzzle HTTP client for making API requests
     * @var Client
     */
    private $client;

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
     * Connector constructor.
     *
     * @param int             $plagscanClientId
     * @param string          $plagscanApiKey
     * @param FilesystemProxy $filesystemProxy
     */
    public function __construct(int $plagscanClientId, string $plagscanApiKey, private FilesystemProxy $filesystemProxy)
    {
        $this->validFiles = new ValidFiles();
        $this->client = new Client([
            'base_uri'    => self::API_URL,
            'timeout'     => self::PLAGSCAN_TIMEOUT,
            'http_errors' => false,
            'curl' => Server::getInstance()->buildCurlOptions()
        ]);

        $this->accessTokenGenerator = new AccessTokenGenerator($this->client, $plagscanClientId, $plagscanApiKey);
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
    public function transmit(SubmissionFile $submissionFile, string $filename): int
    {
        /* Check valid extensions and die early if trying
         * to analyse an illegal file type
         */
        if (!$this->validFiles->isPlagscannable($submissionFile->getBasename())) {
            throw new \DomainException('Invalid file type');
        }

        // Plagscan only allows files that smaller than 100MB
        if ($submissionFile->getSize() > self::PLAGSCAN_FILESIZE_LIMIT) {
            throw new \DomainException('File is too large');
        }

        /* The actual file has to exist
         *
         * It might not exist if you're trying to submit stuff to plagscan, but you did a live-dev sync
         * for database only. You'll have the records for the files, but not the data itself, in which
         * case you have no hope of succeeding
         */
        if (!$this->filesystemProxy->fileExists($submissionFile)) {
            throw new \DomainException('Error: File does not exist in storage.');
        }

        $filename = $this->makeFileName($submissionFile, $filename);

        // Note that storage files in multipart form data must be sent as string
        // values (and not resources), because files can't be posted from
        // non-local resources.
        $postData = [
            [
                'name'     => 'fileUpload',
                'contents' => $submissionFile->getStorage()->read(),
                'filename' => $filename,
            ],
        ];

        try {
            $response = $this->client->post("documents?access_token={$this->accessTokenGenerator->generate()}", [
                'multipart' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not transmit file.');
        }

        /* What was the outcome?
         */
        try {
            $content = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            /* We expected $content to be an array after decoding, but that's not always the case
             * json_decode might successfully return null for some reason due to bad data from plag
             * I guess we'll have to log it to see why it dies
             */
            throw new \RuntimeException(sprintf(
                "Runtime error (transmit): json_decode failed (%s) for response body %s",
                $e->getMessage(),
                $response->getBody()
            ));
        }

        /* Otherwise it decoded into something we can presumably inspect
         * Continue as usual
         */
        if ($response->getStatusCode() === 201) {
            if (!(array_key_exists('data', $content) && array_key_exists('docID', $content['data']))) {
                throw new \InvalidArgumentException(
                    'response[data][docID] does not exist: PlagScan has changed their API again'
                );
            }
            return $content['data']['docID'];
        }

        /* The Documents endpoint can throw 4xx and 5xx errors (the latter isn't documented)
         * These cases are considered non-recoverable (they will consistently fail) and become DomainExceptions
         * unlike RuntimeExceptions, which were intended to represent transient problems since plag would
         * occasionally die in new and wonderful ways, then come back
         *
         * All non-201 status codes at this juncture are classified as repeatable failures and will mark documents
         * accordingly, instead of leaving them in a state where it can be tried again
         */
        if ($response->getStatusCode() >= 400) {
            throw new \DomainException("Domain error {$content['error']['code']}: {$content['error']['message']}");
        }

        /* If you ended up here, the document can't be transmitted for various reasons
         * so just shove them out for logging purposes
         */
        throw new \RuntimeException("Runtime error {$content['error']['code']}: {$content['error']['message']}");
    }

    /**
     * Create a filename that will be sent to plagscan to identify the file
     *
     * @param SubmissionFile $submissionFile
     * @param string         $filename
     * @return string
     */
    private function makeFileName(SubmissionFile $submissionFile, string $filename): string
    {
        $documentName = $submissionFile->getStorage()->getFilename();
        $documentExt = $submissionFile->getStorage()->getExtension();

        // Sanitise the file name to ensure valid characters and length
        $name = preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $filename . '_' . $documentName);
        $name = mb_strcut($name, 0, 255 - ($documentExt ? \strlen($documentExt) + 1 : 0));

        return "{$name}.{$documentExt}";
    }


    /**
     * Analyse a document that we sent to plag
     *
     * @param int|null $plagscanId
     * @return bool whether the report is ready or not
     * @throws \DomainException  if some unexpected thing went wrong
     * @throws \InvalidArgumentException
     * @throws \RuntimeException  if Plagscan is unavailable
     */
    public function analyse(?int $plagscanId): bool
    {
        if (!$plagscanId) {
            throw new \DomainException('No plagscan document id - document has not been transmitted yet');
        }

        $postData = [
            'access_token' => $this->accessTokenGenerator->generate(),
        ];

        try {
            $response = $this->client->put("documents/{$plagscanId}/check", [
                'form_params' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not analyse file.');
        }

        /* What was the outcome?
         */
        try {
            $content = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            /* We expected $content to be an array after decoding, but that's not always the case
             * json_decode might successfully return null for some reason due to bad data from plag
             * I guess we'll have to log it to see why it dies
             */
            throw new \RuntimeException(sprintf(
                "Runtime error (analyse) for plagscanId %s: json_decode failed (%s) for response body %s",
                $plagscanId,
                $e->getMessage(),
                $response->getBody()
            ));
        }

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
            switch ($content['error']['message']) {
                /* pseudo-errors: nothing actually went wrong, plagscan puts notices here
                 * for you to interpret
                 */
                case 'The document is already checked':
                case 'This document has a report already':
                    return true;

                /* actual errors: something really went wrong and makes this plagscan record unscannable
                 */
                case 'The document has been deleted.':
                case 'The document has failed during the upload/convertion process: Document without text or broken file - minimum length is 50 characters':
                    throw new \DomainException("Error {$content['error']['code']}: {$content['error']['message']}");

                /* Otherwise document is one of the following and we don't need
                 * to take any further action at the moment
                 *
                 * - in the queue to be checked
                 * - currently being checked
                 */
                default:
                    return false;
            }
        } elseif ($response->getStatusCode() == 404) {
            /* This document is getting checked; wait for plagscan to do its thing
             * The current string is "The document is on conversion process.",
             * but I'm just going to assume it's still waiting.
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
    public function fetchInteractiveReportUrl(int $plagscanId): string
    {
        $response = $this->retrieve($plagscanId, self::PLAGSCAN_MODE_INTERACTIVE);

        /* What was the outcome?
         */
        try {
            $content = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            /* We expected $content to be an array after decoding, but that's not always the case
             * json_decode might successfully return null for some reason due to bad data from plag
             * I guess we'll have to log it to see why it dies
             */
            throw new \RuntimeException(sprintf(
                "Runtime error (fetchInteractiveReportUrl) for plagscanId %s: json_decode failed (%s) for response body %s",
                $plagscanId,
                $e->getMessage(),
                $response->getBody()
            ));
        }

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
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function fetchReport(int $plagscanId): string
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
     * @param int $plagscanId
     * @return string
     * @throws \InvalidArgumentException if Plagscan changed their API on us
     * @throws \DomainException if some other unexpected thing went wrong
     * @throws \RuntimeException
     */
    public function fetchScore(int $plagscanId): string
    {
        $response = $this->retrieve($plagscanId, self::PLAGSCAN_MODE_SCORE);

        /* What was the outcome?
         */
        try {
            $content = json_decode($response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            /* We expected $content to be an array after decoding, but that's not always the case
             * json_decode might successfully return null for some reason due to bad data from plag
             * I guess we'll have to log it to see why it dies
             */
            throw new \RuntimeException(sprintf(
                "Runtime error (fetchScore) for plagscanId %s: json_decode failed (%s) for response body %s",
                $plagscanId,
                $e->getMessage(),
                $response->getBody()
            ));
        }

        /* json_decode succeeded, but has a different format than previously known
         * check what the api is now returning, adapt accordingly
         */
        if ($response->getStatusCode() === 200) {
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
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException if Plagscan is unavailable
     */
    private function retrieve(int $plagscanId, int $mode): \Psr\Http\Message\ResponseInterface
    {
        $postData = [
            'access_token' => $this->accessTokenGenerator->generate(),
            'mode'         => $mode,
        ];

        try {
            $response = $this->client->get("documents/{$plagscanId}/retrieve", [
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
     * @param int $plagscanId
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException if Plagscan is unavailable
     */
    public function delete(int $plagscanId): \Psr\Http\Message\ResponseInterface
    {
        $postData = [
            'access_token' => $this->accessTokenGenerator->generate(),
        ];

        try {
            $response = $this->client->delete("documents/{$plagscanId}", [
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
     * @param int $plagscanId
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \DomainException
     * @throws \InvalidArgumentException
     * @throws \RuntimeException if Plagscan is unavailable
     */
    public function privatise(int $plagscanId): \Psr\Http\Message\ResponseInterface
    {
        $postData = [
            'access_token' => $this->accessTokenGenerator->generate(),
        ];

        try {
            $response = $this->client->put("documents/{$plagscanId}/private", [
                'query' => $postData,
            ]);
        } catch (TransferException $e) {
            throw new \RuntimeException('Error: Plagscan unavailable. Could not privatise document.');
        }

        return $response;
    }
}
