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
use Alaress_Date_Format;
use DateTime;
use EasyRdf_Sparql_Client;
use Schoolbox\Collection;
use Schoolbox\Curriculum\LearningArea;
use Schoolbox\Curriculum\LearningLevel;
use Schoolbox\Curriculum\Node;
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
        if (count($savedAreas = $this->getSavedLearningAreas())) {
            return $savedAreas;
        }
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
                $modified = new Alaress_Date_Format($row->modified->getValue());
                $area = new LearningArea\Model();
                $area
                    ->setSource($this->getSource())
                    ->setName($desc)
                    ->setCreatedAt($modified)
                    ->setModifiedAt($modified)
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
        if (count($savedLevels = $this->getSavedLearningLevels())) {
            return $savedLevels;
        }
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
            $label    = null;
            $created  = null;
            $modified = null;
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
                    foreach ($document->xpath('//dcterms:issued') as $createdElement) {
                        $created = "$createdElement";
                        break;
                    }
                    foreach ($document->xpath('//dcterms:modified') as $modifiedElement) {
                        $modified = "$modifiedElement";
                        break;
                    }
                }
            }
            if ($created) {
                $level->setCreatedAt(new Alaress_Date_Format(new DateTime($created)));
            }
            if ($modified) {
                $level->setModifiedAt(new Alaress_Date_Format(new DateTime($modified)));
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
        if (count($savedOutcomes = $this->getSavedOutcomes($area, $level))) {
            return $savedOutcomes;
        }
        $collection = new Collection();
        // Get everything out of the manifest instead of out of RDF queries.
        $curl = new Alaress_Curl_Url($area->getReference().'-manifest.json');
        $curl->exec();
        if (!count($curl->getErrors())) {
            $manifest = json_decode($curl->getResult());
            foreach ($manifest as $part) {
                if ($part->dcterms_educationLevel->uri === $level->getReference()) {
                    // This is the right year level within this learning area.
                    // Build an outcome tree out of it.
                    $collection->add($this->buildOutputTree($part));
                }
            }
        }
        return $collection;
    }

    /**
     * @param  object $jsonTree
     * @return Node\Model
     */
    protected function buildOutputTree($jsonTree)
    {
        $node = new Node\Model();
        // Name: maybe it doesn't have one at all.
        // Use the code instead.
        $node
            ->setReference($jsonTree->id)
            ->setDescription($jsonTree->text);
        if ($jsonTree->asn_statementNotation) {
            $node->setCode($jsonTree->asn_statementNotation->literal);
        }
        if ($jsonTree->dcterms_modified) {
            $modified = new Alaress_Date_Format(
                new DateTime($jsonTree->dcterms_modified->literal)
            );
            $node
                ->setCreatedAt($modified)
                ->setModifiedAt($modified);
        }
        if ($jsonTree->children) {
            foreach ($jsonTree->children as $child) {
                $node->addChild($this->buildOutputTree($child));
            }
        }
        return $node;
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
