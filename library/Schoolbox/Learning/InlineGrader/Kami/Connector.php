<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\InlineGrader\Kami;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Schoolbox\IConfigLoader;
use Schoolbox\Resource\Storage\IStorage;

/**
 * Gateway between Schoolbox and Kami
 */
class Connector
{

    // Intentional trailing slash (@see https://github.com/guzzle/guzzle)
    const BASE_URI = 'https://api.notablepdf.com/';

    /**
     * @var IConfigLoader
     */
    private $config;


    /**
     * @param IConfigLoader $config
     */
    public function __construct(IConfigLoader $config)
    {
        $this->config = $config;
    }


    /**
     * Is Kami enabled for this school? (Controlled by Puppet)
     *
     * @return bool
     */
    public function isEnabled()
    {
        return (bool)$this->config->get('kami_api_key');
    }


    /**
     * Is Kami online?
     *
     * @return bool
     */
    public function isOnline()
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $fakeDocId = 'TEST_DOC_ID';
        $path      = $this->getPathGetDocument($fakeDocId);
        $options   = [
            'headers' => $this->getAuthorisationHeader(),
        ];

        $client = $this->buildClient();
        try {
            $client->request('GET', $path, $options);
        } catch (ClientException $e) {
            // We expect to get a 404 'Not Found' because the requested document has an invalid ID
            $response = $e->getResponse();
            if (404 === $response->getStatusCode() && 'Not Found' === $response->getReasonPhrase()) {
                return true;
            }
        } catch (\Exception $e) {
            // Any thing else is considered a failure
        }
        return false;
    }


    /**
     * @param IStorage $storage
     * @return int
     */
    public function uploadDocument(IStorage $storage)
    {
        // TODO upload a storage into Kami and return the ID
        $path = $this->getPathUploadDocument();
        return -1;
    }


    /**
     * @param $documentId
     * @return Document
     */
    public function getDocument($documentId)
    {
        $path = $this->getPathGetDocument($documentId);
        // TODO make GuzzleHttp request to Kami API
        return new Document('exampleId', 'exampleName', Document::FILE_STATUS_DONE, null, new \Alaress_Date_NullDate());
    }


    /**
     * Get the configured API key
     *
     * @return null|string
     */
    private function getApiKey()
    {
        return $this->config->get('kami_api_key');
    }


    /**
     * Get the authorisation header to use for all requests
     *
     * @return string[]
     */
    private function getAuthorisationHeader()
    {
        return ['Authorization' => "Token {$this->getApiKey()}"];
    }


    /**
     * Build the path for the 'Get Document' call
     *
     * @param $documentId
     * @return string
     */
    private function getPathGetDocument($documentId)
    {
        return "embed/documents/{$documentId}";
    }


    /**
     * Build the path for the 'Upload Document' call
     *
     * @return string
     */
    private function getPathUploadDocument()
    {
        return 'upload/embed/documents';
    }


    /**
     * @return Client
     */
    private function buildClient()
    {
        return new Client(['base_uri' => self::BASE_URI]);
    }

}
