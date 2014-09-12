<?php
/**
 * A browser for Australian Curriculum classes.
 *
 * This gets all the curricula that can be fetched from this source.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2014, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Curriculum\Source\AustralianCurriculum;

use EasyRdf_Sparql_Client;

class Browser
{
    /**
     * @var EasyRdf_Sparql_Client
     */
    protected $client;

    /**
     * Constructor - creates this field with a preset SPARQL client.
     */
    public function __construct()
    {
        $this->setClient(
            new EasyRdf_Sparql_Client('http://rdf.australiancurriculum.edu.au/sparql')
        );
    }

    /**
     * Retrieves a collection of learning areas.
     *
     * Optionally, it may only return areas that apply to a learning level?
     *
     * @return object
     */
    public function getLearningAreas()
    {
        // Fetch a list of all standard documents.
        // Then, group by available education levels?
        $sparql = <<<SPARQL
prefix asn: <http://purl.org/ASN/schema/core/>
prefix dct: <http://purl.org/dc/terms/>
SELECT * WHERE {
    ?subject a asn:StandardDocument .
    ?subject dct:description ?description .
    ?subject dct:source ?source .
}
SPARQL
        ;
        return $this->getClient()->query($sparql);

//        foreach ($client->query($sparql) as $row) {
//
//        }
//        $uri->setQuery(array('query' => $sparql));
//        $document = EasyRdf_Graph::newAndLoad($uri->toString());
//        foreach ($document->)
    }

    /**
     * Returns a collection of learning levels.
     *
     * Optionally, it may only return levels that apply to a learning area?
     */
    public function getLearningLevels($learningArea = null)
    {
        $sparql = <<<SPARQL
prefix asn: <http://purl.org/ASN/schema/core/>
prefix dct: <http://purl.org/dc/terms/>
SELECT DISTINCT ?level WHERE {
    ?subject a asn:StandardDocument .
    %s
    { ?subject dct:educationLevel ?level . }
}
SPARQL
        ;

        // Did I supply a learning area?
        // Only return levels in that area.
        $learningAreaQuery = '';
        if ($learningArea) {
            $learningAreaQuery = "?subject dct:description \"$learningArea\"@en-au";
        }
        $sparql = sprintf($sparql, $learningAreaQuery);
        $result = $this->getClient()->query($sparql);
        return $result;
    }

    /**
     * @param string $learningArea
     *
     * @return object
     */
    public function getStrands($learningArea)
    {
        // Get all strands of the given learning area
        $sparql = <<<SPARQL
prefix asn: <http://purl.org/ASN/schema/core/>
prefix dct: <http://purl.org/dc/terms/>
SELECT ?strand WHERE {
    ?subject a asn:StandardDocument .
    ?subject dct:description \"$learningArea\"@en-au
    { ?subject dct:educationLevel ?level . }
}
SPARQL
        ;

        // Did I supply a learning area?
        // Only return levels in that area.
        $learningAreaQuery = '';
        if ($learningArea) {
            $learningAreaQuery = "?subject dct:description \"$learningArea\"@en-au";
        }
        $sparql = sprintf($sparql, $learningAreaQuery);
        $result = $this->getClient()->query($sparql);
        return $result;
    }

    public function getOutcomes()
    {
    }

    /**
     * @param  EasyRdf_Sparql_Client $client
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;
        return $this;
    }

    /**
     * @return EasyRdf_Sparql_Client
     */
    public function getClient()
    {
        return $this->client;
    }
}
