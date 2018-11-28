<?php
/**
 * @copyright Copyright 2018, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Source\AustralianCurriculum;

use EasyRdf_Exception;
use EasyRdf_Format;
use EasyRdf_Graph;
use EasyRdf_Http;
use EasyRdf_Namespace;
use EasyRdf_Sparql_Client;
use EasyRdf_Sparql_Result;
use EasyRdf_Utils;

/**
 * Extension of SPARQL client to work with ACARA's API.
 *
 * The only actual difference is that ACARA only accepts its query parameter in
 * `$_GET['q']`: SPARQL supposedly takes its query in `$_GET['query']`.
 */
class SparqlClient extends EasyRdf_Sparql_Client
{
    /**
     * The URI for ACARA's API.
     */
    public const API_URI = 'http://rdf.australiancurriculum.edu.au/api/sparql';


    /**
     * @inheritdoc
     */
    protected function request($type, $query)
    {
        // Check for undefined prefixes
        $prefixes = '';
        foreach (EasyRdf_Namespace::namespaces() as $prefix => $uri) {
            if (strpos($query, "$prefix:") !== false and
                strpos($query, "PREFIX $prefix:") === false) {
                $prefixes .= "PREFIX $prefix: <$uri>\n";
            }
        }

        $client = EasyRdf_Http::getDefaultHttpClient();
        $client->resetParameters();

        // Tell the server which response formats we can parse
        $accept = EasyRdf_Format::getHttpAcceptHeader(
            [
                'application/sparql-results+json' => 1.0,
                'application/sparql-results+xml'  => 0.8,
            ]
        );
        $client->setHeaders('Accept', $accept);

        $queryUri = $this->getQueryUri();
        if ($type == 'update') {
            $client->setMethod('POST');
            $client->setUri($this->getUpdateUri());
            $client->setRawData($prefixes . $query);
            $client->setHeaders('Content-Type', 'application/sparql-update');
        } elseif ($type == 'query') {
            // Use GET if the query is less than 2kB
            // 2046 = 2kB minus 1 for '?' and 1 for NULL-terminated string on server
            $encodedQuery = 'q=' . urlencode($prefixes . $query);
            if (strlen($encodedQuery) + strlen($queryUri) <= 2046) {
                $delimiter = parse_url($queryUri, PHP_URL_QUERY) === '' ? '?' : '&';

                $client->setMethod('GET');
                $client->setUri($queryUri . $delimiter . $encodedQuery);
            } else {
                // Fall back to POST instead (which is un-cacheable)
                $client->setMethod('POST');
                $client->setUri($queryUri);
                $client->setRawData($encodedQuery);
                $client->setHeaders('Content-Type', 'application/x-www-form-urlencoded');
            }
        }

        $response = $client->request();
        if ($response->getStatus() == 204) {
            // No content
            return $response;
        } elseif ($response->isSuccessful()) {
            list($type, $params) = EasyRdf_Utils::parseMimeType(
                $response->getHeader('Content-Type')
            );
            if (strpos($type, 'application/sparql-results') === 0) {
                return new EasyRdf_Sparql_Result($response->getBody(), $type);
            } else {
                return new EasyRdf_Graph($queryUri, $response->getBody(), $type);
            }
        } else {
            throw new EasyRdf_Exception(
                "HTTP request for SPARQL query failed: " . $response->getBody()
            );
        }
    }
}
