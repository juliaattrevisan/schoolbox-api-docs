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
use Alaress\Html\ToText as Html2Text;
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
     * @var Html2Text
     */
    protected $htmlConverter;

    /**
     * Post-construct hook - creates a SPARQL client for the Australian Curriculum.
     */
    public function setup()
    {
        $this->setClient(
            new EasyRdf_Sparql_Client('http://rdf.australiancurriculum.edu.au/sparql')
        );
        $this->setHtmlConverter(new Html2Text());
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
SELECT *
       WHERE {
    ?doc a asn:StandardDocument .
    ?doc dct:source ?source .
    ?doc dct:modified ?modified .
    ?doc dct:subject ?subject .
    OPTIONAL { ?doc dct:abstract ?abstract } .
    OPTIONAL { ?doc dct:description ?description } .
    OPTIONAL { ?doc dct:title ?title . } .
}
SPARQL
        ;
        $models = array();
        foreach ($this->getClient()->query($sparql) as $row) {
            if(is_object($row->title)) {
                $desc = $row->title->getValue();
            }
            else if(is_object($row->description)) {
                $desc = $row->description->getValue();
            }
            else {
                continue;
            }

            try {
                $area = $this->getAreaMapper()->findOneByColumn('reference', $row->doc->getUri(), ["deleted" => false]);
            } catch(\Schoolbox\Mapper\Exception $e) {
                $area = new LearningArea\Model();
            }

            $modified = new Alaress_Date_Format($row->modified->getValue());
            $area
                ->setSource($this->getSource())
                ->setName($desc)
                ->setCreatedAt($modified)
                ->setModifiedAt($modified)
                ->setReference($row->doc->getUri());
            $models[$desc] = $area;

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

            try {
                $level = $this->getLevelMapper()->findOneByColumn('reference', $row->level->getUri(), ["deleted" => false]);
            } catch (\Schoolbox\Mapper\Exception $e) {
                $level = new LearningLevel\Model();
                $level
                    ->setSource($this->getSource())
                    ->setReference($row->level->getUri());
            }

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

            // No label - don't add
            if (isset($label)) {

                $level->setName($label);
                if ($created) {
                    $level->setCreatedAt(new Alaress_Date_Format(new DateTime($created)));
                }
                if ($modified) {
                    $level->setModifiedAt(new Alaress_Date_Format(new DateTime($modified)));
                }

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

        $collection = new Collection();
        // Get everything out of the manifest instead of out of RDF queries.
        $curl = new Alaress_Curl_Url($area->getReference().'-manifest.json');
        $curl->exec();
        if (!count($curl->getErrors())) {
            $manifest = json_decode($curl->getResult());
            foreach ($manifest as $part) {
                if(($nodes = $this->buildOutputTree($part, $level)) != false) {
                    $collection->add($nodes);
                }
            }
        }
        return $collection;
    }

    /**
     * Creates this node and its children from the JSON object.
     *
     * @param  object $jsonTree
     * @return Node\Model
     */
    protected function buildOutputTree($jsonTree, $level)
    {

        if ($jsonTree->dcterms_educationLevel->uri === $level->getReference()) {
            // This is the right year level within this learning area.
            // Build an outcome tree out of it.
            $node = $this->buildSingleNode($jsonTree);

        }
        else if(is_array($jsonTree->dcterms_educationLevel)) {
            if(count(array_filter($jsonTree->dcterms_educationLevel, function($v) use ($level) {
                    return $v->uri ==  $level->getReference(); }
                ))>0 ) {
                $node = $this->buildSingleNode($jsonTree);
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }

        if ($jsonTree->children) {
            foreach ($jsonTree->children as $child) {
                if(($nodes = $this->buildOutputTree($child, $level)) != false) {
                    $node->addChild($nodes);
                };
            }
        }
        return $node;
    }

    /**
     * Creates a single node from the given JSON object.
     *
     * @param  object $json
     * @return Node\Model
     */
    protected function buildSingleNode($json)
    {
        $node = new Node\Model();
        $node->setReference($json->id);

        // Figure out what the name and description should be.
        $statementLabel = '';
        if ($json->asn_statementLabel && $json->asn_statementLabel->literal) {
            $statementLabel = $json->asn_statementLabel->literal;
        }
        switch ($statementLabel) {
            case 'Achievement standard':
                // has no title field, and its text is unsuitable as a name.
                // Let's just call it an achievement standard.
                $node
                    ->setName($statementLabel)
                    ->setDescription($json->text);
                break;
            default:
                // Try basing name off the title, and desc off the text.
                // If that fails, set the name as the text and an empty desc.
                if ($json->dcterms_title && $json->dcterms_title->literal) {
                    $node
                        ->setName($json->dcterms_title->literal)
                        ->setDescription($json->text);
                } else {
                    $node
                        ->setName($json->text)
                        ->setDescription('');
                }
                break;
        }

        // Convert the name from HTML to text (and strip out newlines)
        $this->getHtmlConverter()->setHtml($node->getName());
        $node->setName(preg_replace('/\\s+/', ' ', $this->getHtmlConverter()->getText()));

        // Set the timetable code if there is one
        if ($json->asn_statementNotation && $json->asn_statementNotation->literal) {
            $node->setCode($json->asn_statementNotation->literal);
        }
        if ($json->dcterms_modified && $json->dcterms_modified->literal) {
            $modified = new Alaress_Date_Format(
                new DateTime($json->dcterms_modified->literal)
            );
            $node
                ->setCreatedAt($modified)
                ->setModifiedAt($modified);
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

    /**
     * @return Html2Text
     */
    public function getHtmlConverter()
    {
        return $this->htmlConverter;
    }

    /**
     * @param  Html2Text $htmlConverter
     * @return $this
     */
    public function setHtmlConverter($htmlConverter)
    {
        $this->htmlConverter = $htmlConverter;
        return $this;
    }
}
