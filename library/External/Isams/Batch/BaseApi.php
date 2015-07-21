<?php
/**
 * Base class for queries to the iSAMS Batch API.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

class External_Isams_Batch_BaseApi
{
    /**
     * The host of the iSAMS server
     *
     * @var string
     */
    protected $host;

    /**
     * The API key for this call, provided by the school
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Constructor
     *
     * @param string $host
     * @param string $apiKey
     */
    public function __construct($host, $apiKey)
    {
        $this->host   = $host;
        $this->apiKey = $apiKey;
    }

    /**
     * Retrieves a XML document representing the result from the API call.
     *
     * @param  string $filter An XML document representing a filter
     * @return string
     * @throws RuntimeException if the retrieval of the XML doc failed
     */
    public function retrieveDocument($filter = '')
    {
        $url = new \Zend\Uri\Uri();
        $url->setScheme('https')
            ->setHost($this->getHost())
            ->setPath('/api/batch/1.0/xml.ashx')
            ->setQuery(['apikey' => $this->getApiKey()])
        ;
        $curl         = new Alaress_Curl_Url($url);
        $filterString = "$filter";
        if (strlen($filterString) > 0) {
            $curl->addOptions([
                CURLOPT_HTTPHEADER => ['Content-Type: text/xml'],
                CURLOPT_POST       => true,
                CURLOPT_POSTFIELDS => $filterString,
            ]);
        }
        $curl->exec();
        if (empty($curl->getErrors())) {
            // Too big to parse
            return $curl->getResult();
        } else {
            throw new RuntimeException(
                sprintf(
                    'XML retrieval failed: response code %d, message: %s',
                    $curl->getResponseCode(),
                    implode(', ', $curl->getErrors())
                ),
                $curl->getResponseCode()
            );
        }
    }

    /**
     * @return string
     */
    protected function getHost()
    {
        return $this->host;
    }

    /**
     * @param  string $host
     *
     * @return $this
     */
    protected function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @return string
     */
    protected function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param  string $apiKey
     *
     * @return $this
     */
    protected function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        return $this;
    }
}
