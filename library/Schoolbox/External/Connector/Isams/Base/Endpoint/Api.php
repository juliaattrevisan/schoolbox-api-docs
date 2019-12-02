<?php
/**
 * Api
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

namespace Schoolbox\External\Connector\Isams\Base\Endpoint;

use Alaress_Curl_Url;
use RuntimeException;
use Zend\Uri\Exception;
use Zend\Uri\Uri;

class Api implements IApi
{
    /**
     * The host of the iSAMS server
     *
     * @var string
     */
    private $host;


    /**
     * The API key for this call, provided by the school
     *
     * @var string
     */
    private $apiKey;


    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     */
    public function __construct(string $host, string $apiKey)
    {
        $this->host   = $host;
        $this->apiKey = $apiKey;
    }


    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @param string $filter An XML document representing a filter
     * @return string|null
     * @throws RuntimeException if the retrieval of the XML doc failed
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    public function retrieveDocument(string $filter = ''): ?string
    {
        $curl = $this->initCurl($filter);
        $curl->exec();

        if (empty($curl->getErrors())) {
            // Too big to parse
            return $curl->getResult();
        }

        throw new RuntimeException(
            sprintf(
                'XML retrieval failed: response code %d, message: %s',
                $curl->getResponseCode(),
                implode(', ', $curl->getErrors())
            ),
            $curl->getResponseCode()
        );
    }


    /**
     * Set up curl instance
     *
     * @param string $filter
     * @return Alaress_Curl_Url
     * @throws Exception\InvalidArgumentException
     * @throws Exception\InvalidUriPartException
     */
    private function initCurl(string $filter = ''): \Alaress_Curl_Url
    {
        $url = (new Uri())
            ->setScheme('https')
            ->setHost($this->host)
            ->setPath('/api/batch/1.0/xml.ashx')
            ->setQuery(['apikey' => $this->apiKey]);

        $curl = new Alaress_Curl_Url($url);

        if ('' !== $filter) {
            $curl->addOptions([
                CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $filter,
            ]);
        }

        return $curl;
    }
}
