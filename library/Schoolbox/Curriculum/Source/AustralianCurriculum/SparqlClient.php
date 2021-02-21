<?php

/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Source\AustralianCurriculum;

use EasyRdf\Exception;
use EasyRdf\Format;
use EasyRdf\Http;
use EasyRdf\Sparql\Client;

/**
 * Extension of SPARQL client to work with ACARA's API.
 *
 * The only actual difference is that ACARA only accepts its query parameter in
 * `$_GET['q']`: SPARQL supposedly takes its query in `$_GET['query']`.
 */
class SparqlClient extends Client
{
    /**
     * The URI for ACARA's API.
     */
    public const API_URI = 'http://rdf.australiancurriculum.edu.au/api/sparql';


    /**
     * Copied out of the parent class: the only change is the aforementioned
     * query parameter.
     *
     * @param string $processed_query
     * @param string $type            Should be either "query" or "update"
     *
     * @return Http\Response
     * @throws Exception
     */
    protected function executeQuery($processed_query, $type)
    {
        // This will always be EasyRDF's HTTP client, not the Zend client
        /** @var Http\Client $client */
        $client = Http::getDefaultHttpClient();
        $client->resetParameters();

        // Tell the server which response formats we can parse
        $sparql_results_types = [
            'application/sparql-results+json' => 1.0,
            'application/sparql-results+xml'  => 0.8,
        ];

        if ($type == 'update') {
            // accept anything, as "response body of a […] update request is implementation defined"
            // @see http://www.w3.org/TR/sparql11-protocol/#update-success
            $accept = Format::getHttpAcceptHeader($sparql_results_types);
            $this->setHeaders($client, 'Accept', $accept);

            $client->setMethod('POST');
            $client->setUri($this->getUpdateUri());
            $client->setRawData($processed_query);
            $this->setHeaders($client, 'Content-Type', 'application/sparql-update');
        } elseif ($type == 'query') {
            $re = '(?:(?:\s*BASE\s*<.*?>\s*)|(?:\s*PREFIX\s+.+:\s*<.*?>\s*))*' .
                '(CONSTRUCT|SELECT|ASK|DESCRIBE)[\W]';

            $result = null;
            mb_eregi($re, $processed_query, $result);

            if (count($result) !== 2) {
                // non-standard query. is this something non-standard?
                $query_verb = null;
            } else {
                $query_verb = strtoupper($result[1]);
            }

            if ($query_verb === 'SELECT' or $query_verb === 'ASK') {
                // only "results"
                $accept = Format::formatAcceptHeader($sparql_results_types);
            } elseif ($query_verb === 'CONSTRUCT' or $query_verb === 'DESCRIBE') {
                // only "graph"
                $accept = Format::getHttpAcceptHeader();
            } else {
                // both
                $accept = Format::getHttpAcceptHeader($sparql_results_types);
            }

            $this->setHeaders($client, 'Accept', $accept);

            // This is the only functional change in this subclass.
            // Usually the query string key is "query": here, it must be "q".
            $encodedQuery = 'q=' . urlencode($processed_query);

            // Use GET if the query is less than 2kB
            // 2046 = 2kB minus 1 for '?' and 1 for NULL-terminated string on server
            if (strlen($encodedQuery) + strlen($this->getQueryUri()) <= 2046) {
                $queryPart = parse_url($this->getQueryUri(), PHP_URL_QUERY) ?? '';
                $delimiter = strlen($queryPart) > 0 ? '&' : '?';

                $client->setMethod('GET');
                $client->setUri($this->getQueryUri() . $delimiter . $encodedQuery);
            } else {
                // Fall back to POST instead (which is un-cacheable)
                $client->setMethod('POST');
                $client->setUri($this->getQueryUri());
                $client->setRawData($encodedQuery);
                $this->setHeaders($client, 'Content-Type', 'application/x-www-form-urlencoded');
            }
        } else {
            throw new Exception('unexpected request-type: ' . $type);
        }

        return $client->request();
    }
}
