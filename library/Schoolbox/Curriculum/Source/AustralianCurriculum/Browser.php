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

use Alaress_Curl_Url;
use EasyRdf_Sparql_Client;
use Schoolbox\Collection;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\Curriculum\Source;

class Browser extends Source\BrowserAbstract
{
    /**
     * @var EasyRdf_Sparql_Client
     */
    protected $client;

    /**
     * Post-construct hook - creates a SPARQL client for the Australian Curriculum.
     */
    public function setup()
    {
        $this->setClient(
            new EasyRdf_Sparql_Client('http://rdf.australiancurriculum.edu.au/sparql')
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getLearningAreas()
    {
        // Fetch a list of all standard documents.
        // Then, group by available education levels?
        $sparql = <<<SPARQL
prefix asn: <http://purl.org/ASN/schema/core/>
prefix dct: <http://purl.org/dc/terms/>
SELECT * WHERE {
    ?doc a asn:StandardDocument .
    ?doc dct:description ?description .
    ?doc dct:source ?source .
    ?doc dct:modified ?modified .
    ?doc dct:subject ?subject .
    OPTIONAL { ?doc dct:abstract ?abstract } .
}
SPARQL
        ;
        $models = array();
        foreach ($this->getClient()->query($sparql) as $row) {
            $desc = $row->description->getValue();
            if (!array_key_exists($desc, $models)) {
                $area = new LearningArea\Model();
                $area
                    ->setSource($this->getSource())
                    ->setName($desc)
                    ->setCreatedAt($row->modified->getValue())
                    ->setModifiedAt($row->modified->getValue())
                    ->setReference($row->doc->getUri())
                    ->setDescription(sprintf('<h1>%s</h1>', htmlspecialchars($desc, ENT_QUOTES)));
                $models[$desc] = $area;
            }
            // Use an existing area if possible.
            // Otherwise, use a new one.
            // Build a description for this item, consisting of desc, abstracts
            // and the subject.
            $abstract   = $row->abstract->getValue();
            $titleParts = explode('/', $abstract);
            $title      = array_pop($titleParts);
            $desc       = $area->getDescription();
            $area->setDescription(
                sprintf(
                    "%s\n<a href=\"%s\">%s</a>",
                    $desc,
                    htmlspecialchars($abstract, ENT_QUOTES),
                    htmlspecialchars($title, ENT_QUOTES)
                )
            );
        }
        $collection = new Collection(array_values($models));
        return $collection;
    }

    /**
     * {@inheritdoc}
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
            $level
                ->setSource($this->getSource())
                ->setReference($row->level->getUri());
            $label = null;
            if (isset($row->label)) {
                $label = $row->label->getValue();
            } else {
                // fetch the label in a separate request.
                // TODO get the query working properly
                $curl = new Alaress_Curl_Url($row->level->getUri().'.rdf');
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
     * {@inheritdoc}
     */
    public function getOutcomes(LearningArea\Model $area, LearningLevel\Model $level)
    {
        // Given learning area and learning level:
        // http://vocabulary.curriculum.edu.au/schoolLevel/0
        // http://rdf.australiancurriculum.edu.au/elements/2012/08/7f6bd186-fcdf-4e46-a727-9e4600a2a39b
        $areaReference  = $area->getReference();
        $modelReference = $model->getReference();

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
