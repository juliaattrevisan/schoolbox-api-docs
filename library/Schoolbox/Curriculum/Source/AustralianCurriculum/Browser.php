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
use Schoolbox\Collection;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;

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
     * @return Collection|LearningArea\Model[]
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
        $collection = new Collection();
        foreach ($this->getClient()->query($sparql) as $row) {
            // Use an existing area if possible.
            // Otherwise, use a new one.
            $area = new LearningArea\Model();
            $area
//                ->setSource($this->getSource())
                ->setName($row->description->getValue());
            $collection->add($area);
        }
        return $collection;
    }

    /**
     * Returns a collection of learning levels.
     *
     * Optionally, it may only return levels that apply to a learning area?
     *
     * @return Collection|LearningLevel\Model[]
     */
    public function getLearningLevels()
    {
        // Fetch all unique learning levels from the curriculum here.
        // I'd like to fetch the label from the level document too, but I don't
        // know how.
        $sparql = <<<SPARQL
prefix asn: <http://purl.org/ASN/schema/core/>
prefix dct: <http://purl.org/dc/terms/>
prefix sko: <http://www.w3.org/2004/02/skos/core#>
SELECT DISTINCT ?level ?label WHERE {
    ?subject a asn:StandardDocument .
    {
        ?subject dct:educationLevel ?level .
        OPTIONAL { ?level sko:prefLabel ?label }
    }
}
SPARQL
        ;
        $collection = new Collection();
        foreach ($this->getClient()->query($sparql) as $row) {
            $level = new LearningLevel\Model();
            $label = null;
            if (isset($row->label)) {
                $label = $row->label->getValue();
            } else {
                // fetch the label in a separate request.
                // TODO get the query working properly
                $curl = new \Alaress_Curl_Url($row->level->getUri().'.rdf');
                $curl->exec();
                if (!count($curl->getErrors())) {
                    $document = simplexml_load_string($curl->getResult());
                    foreach ($document->xpath('//skos:prefLabel') as $labelElement) {
                        $label = "$labelElement";
                        break;
                    }
                }
            }
            // No label - don't add
            if (isset($label)) {
                $level->setName($label);
                $collection->add($level);
            }
        }
        return $collection;
    }

    /**
     * @param string $learningArea
     *
     * @return object
     */
    public function getStrands($learningArea)
    {
        // Given learning area and learning level:

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
