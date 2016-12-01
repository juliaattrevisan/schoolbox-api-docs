<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationModel;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Translation class between a Course Specification and its data model.
 */
class Translator
{
    /**
     * The Unit Translator is used to translate the Course Specification's
     * constituent Unit Specifications.
     *
     * @var Unit\Translator
     */
    protected $unitTranslator;

    /**
     * Constructor
     *
     * @param Unit\Translator $unitTranslator
     */
    public function __construct(Unit\Translator $unitTranslator)
    {
        $this->unitTranslator = $unitTranslator;
    }

    /**
     * Translates the Course Specification business domain model to a data model.
     *
     * @param CourseSpecification $specification
     * @return Model
     */
    public function translateToDataModel(CourseSpecification $specification)
    {
        // Is there ever a need to get a data model without persisting?
        // I don't know?
        $dataModel = new Model();
        $dataModel
            ->setId($specification->getId())
            ->setCreatedAt($specification->getCreatedAt())
            ->setUpdatedAt($specification->getUpdatedAt())
            ->setDeletedAt($specification->getDeletedAt())
            ->setUnits(new GenericCollection(UnitSpecificationModel::class));
        ;

        // Allow translate if folder is empty, for now.
        if ($specification->getFolder() instanceof FolderModel) {
            $dataModel->setFolder($specification->getFolder());
        }

        // Constituent Unit Specs - return in separate object, or this object?
        foreach ($specification->getUnits() as $unitSpecification) {
            $dataModel->getUnits()->add(
                $this->unitTranslator->translateToDataModel($unitSpecification, $specification)
            );
        }

        // If I am going to persist here, I need mappers to have been injected.
        return $dataModel;
    }

    /**
     * Translates the data model to a domain model.
     *
     * @param Model $dataModel
     * @return CourseSpecification
     */
    public function translateToDomainModel(Model $dataModel)
    {
        $specification = new CourseSpecification();
        $specification
            ->setId($dataModel->getId())
            ->setCreatedAt($dataModel->getCreatedAt())
            ->setUpdatedAt($dataModel->getUpdatedAt())
            ->setDeletedAt($dataModel->getDeletedAt())
            ->setUnits(new Collection())
        ;

        // Allow translate if folder is empty, for now.
        if ($dataModel->getFolder() instanceof FolderModel) {
            $specification->setFolder($dataModel->getFolder());
        }

        // Constitute Unit Specs
        foreach ($dataModel->getUnits() as $unitDataModel) {
            // Reciprocate relation, so that unit may set a relation back to the course:
            $unitDataModel->setCourse($dataModel);
            $specification->getUnits()->add($this->unitTranslator->translateToDomainModel($unitDataModel));
        }

        return $specification;
    }
}
