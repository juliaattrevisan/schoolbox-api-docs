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
prefix sko: <http://www.w3.org/2004/02/skos/core#>
SELECT DISTINCT ?subject ?level ?label WHERE {
    ?subject a asn:StandardDocument .
    %s
    {
        ?subject dct:educationLevel ?level .
        OPTIONAL { ?level sko:prefLabel ?label }
    }
}
SPARQL
        ;

        // I wish I could join to learning level here, but I don't know how.
        // The query errors out.

        // Did I supply a learning area?
        // Only return levels in that area.
        $learningAreaQuery = '';
        if ($learningArea) {
            $learningAreaQuery = "?subject dct:description \"$learningArea\"@en-au";
        }
        $sparql = sprintf($sparql, $learningAreaQuery);
        try {
            $result = $this->getClient()->query($sparql);
        } catch (\Exception $e) {
            var_dump($e);
            throw $e;
        }

        return $result;
    }

    /**
     * @param string $learningArea
     *
     * @return object
     */
    public function getStrands($learningArea)
    {
        // Get the learning area, then get all the strands that are part of it?
        // Get all strands of the given learning area
        $sparql = <<<SPARQL
prefix asn: <http://purl.org/ASN/schema/core/>
prefix dct: <http://purl.org/dc/terms/>
SELECT * WHERE {
    {
        ?subject asn:statementLabel "Strand"@en-au .
        ?subject dct:description ?description .
        ?subject dct:isPartOf ?part .
        ?part    dct:description ?learningArea .
    }
    UNION {
        ?subject asn:statementLabel "Year level strand"@en-au .
        ?subject dct:description ?description .
        ?subject dct:educationLevel ?level .
        ?subject dct:isPartOf ?part .
        ?part    dct:description ?learningArea .
    }
}
ORDER BY ASC(?learningArea) ASC(?description)
SPARQL
        ;
        // Create a recursive tree of strands?
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
