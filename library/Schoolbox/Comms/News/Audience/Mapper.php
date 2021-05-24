<?php

/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Comms\News\Audience;

use DomainException;
use PDOException;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception\InsufficientHydrationException;
use UnexpectedValueException;

class Mapper
{
    // Need to retain this as it now exists only in domain level
    public const FIELD_PARENT_ID = 'parent_id';

    /** @var Simple\Mapper */
    private $simpleMapper;

    /** @var Complex\Mapper */
    private $complexMapper;

    /** @var self */
    private static $instance;


    /**
     * Implemented for backwards compatibility for when this used to be a real mapper.
     * @return static
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self(Simple\Mapper::getInstance(), Complex\Mapper::getInstance());
        }
        return self::$instance;
    }


    /**
     * Private to enforce singleton
     *
     * @param Simple\Mapper  $simpleMapper
     * @param Complex\Mapper $complexMapper
     */
    private function __construct(Simple\Mapper $simpleMapper, Complex\Mapper $complexMapper)
    {
        $this->simpleMapper  = $simpleMapper;
        $this->complexMapper = $complexMapper;
    }


    /**
     * Efficiently returns structured audience segments structure for attaching to a set of articles
     *
     * @param int[] $articleIds
     * @param array $options
     * @return GenericCollection|Model[]
     * @throws DomainException
     */
    public function findForArticles(array $articleIds, array $options = []): GenericCollection
    {
        $collection = Model::createCollection();
        foreach ($this->simpleMapper->findByColumn($this->simpleMapper::FIELD_COMMS_NEWS_ARTICLE_ID, $articleIds, $options) as $simple) {
            $collection->add($this->translateSimpleDataToDomain($simple));
        }
        foreach ($this->complexMapper->findByColumn($this->complexMapper::FIELD_COMMS_NEWS_ARTICLE_ID, $articleIds, $options) as $complex) {
            $collection->add($this->translateComplexDataToDomain($complex));
        }
        return $collection;
    }


    /**
     * Deletes a segment and its children using leaf-first strategy
     *
     * Returns whether any records were deleted.
     *
     * @param Model $segment
     * @return bool
     * @throws \Schoolbox\Mapper\Exception
     * @throws InsufficientHydrationException
     */
    public function deleteSegment(Model $segment): bool
    {
        if ($segment->isSimple()) {
            return $this->simpleMapper->delete($this->translateSimpleDomainToData($segment));
        }
        return $this->complexMapper->delete($this->translateComplexDomainToData($segment));
    }


    /**
     * Saves a segment and its children using root-first strategy
     *
     * Returns whether any records were saved.
     *
     * @param Model $segment
     * @return bool|int[]
     * @throws InsufficientHydrationException
     * @throws PDOException
     * @throws \Schoolbox\Mapper\Exception
     * @throws UnexpectedValueException
     */
    public function saveSegment(Model $segment)
    {
        if ($segment->isSimple()) {
            return $this->simpleMapper->save($this->translateSimpleDomainToData($segment));
        }
        return $this->complexMapper->save($this->translateComplexDomainToData($segment));
    }


    /**
     * Translates a simple audience from domain to data representation
     *
     * @param Model $domainModel
     * @return Simple\Model
     */
    private function translateSimpleDomainToData(Model $domainModel): Simple\Model
    {
        return (new Simple\Model())
            ->setCommsNewsArticle($domainModel->getCommsNewsArticle())
            ->setEntityType($domainModel->getEntityType())
            ->setEntityValue($domainModel->getEntityValue())
            ->setRelationship($domainModel->getRelationship());
    }


    /**
     * Translates a simple audience from data to domain representation
     * @param Simple\Model $dataModel
     * @return Model
     */
    private function translateSimpleDataToDomain(Simple\Model $dataModel): Model
    {
        return (new Model())
            ->setCommsNewsArticle($dataModel->getCommsNewsArticle())
            ->setEntityType($dataModel->getEntityType())
            ->setEntityValue($dataModel->getEntityValue())
            ->setRelationship($dataModel->getRelationship());
    }


    /**
     * Translates a complex domain representation to data representation
     *
     * @param Model $domainModel
     * @return Complex\Model
     */
    private function translateComplexDomainToData(Model $domainModel): Complex\Model
    {
        /**
         * @var Simple\Model $child1
         * @var Simple\Model $child2
         */
        [$child1, $child2] = array_values($domainModel->getChildren()->getAll());
        return (new Complex\Model())
            ->setCommsNewsArticle($domainModel->getCommsNewsArticle())
            ->setEntityType1($child1->getEntityType())
            ->setEntityValue1($child1->getEntityValue())
            ->setEntityType2($child2->getEntityType())
            ->setEntityValue2($child2->getEntityValue());
    }


    /**
     * Translates a complex data representation to domain representation
     *
     * @param Complex\Model $dataModel
     * @return Model
     * @throws DomainException
     */
    private function translateComplexDataToDomain(Complex\Model $dataModel): Model
    {
        $rootSegment = (new Model())
            ->setCommsNewsArticle($dataModel->getCommsNewsArticle())
            ->setEntityType(Segment\Strategy\Composite::getEntityType())
            ->setRelationship(Model::RELATIONSHIP_SELF);

        $child1 = (new Model())
            ->setParent($rootSegment)
            ->setEntityType($dataModel->getEntityType1())
            ->setEntityValue($dataModel->getEntityValue1())
            ->setRelationship(Model::RELATIONSHIP_SELF);

        $child2 = (new Model())
            ->setParent($rootSegment)
            ->setEntityType($dataModel->getEntityType2())
            ->setEntityValue($dataModel->getEntityValue2())
            ->setRelationship(Model::RELATIONSHIP_SELF);

        return $rootSegment->setChildren(Model::createCollection()->add($child1)->add($child2));
    }
}
