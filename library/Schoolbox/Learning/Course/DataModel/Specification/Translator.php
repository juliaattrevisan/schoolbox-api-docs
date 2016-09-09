<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

/**
 * Translation class between a Course Specification and its data model.
 */
class Translator
{
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
            ->setFolder($specification->getFolder())
            ->setCreatedAt($specification->getCreatedAt())
            ->setUpdatedAt($specification->getUpdatedAt())
            ->setDeletedAt($specification->getDeletedAt())
        ;

        // Constituent Unit Specs - return in separate object, or this object?
        foreach ($specification->getUnits() as $unitSpecification) {
            $dataModel->getUnits()->add(
                $this->translateToUnitDataModel($unitSpecification, $dataModel)
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
            ->setFolder($dataModel->getFolder())
            ->setCreatedAt($dataModel->getCreatedAt())
            ->setUpdatedAt($dataModel->getUpdatedAt())
            ->setDeletedAt($dataModel->getDeletedAt())
        ;

        // Constitute Unit Specs
        foreach ($dataModel->getUnits() as $unitDataModel) {
            $specification->getUnits()->add($this->translateToUnitDomainModel($unitDataModel));
        }

        return $specification;
    }

    /**
     * Translates the Unit Specification business domain model to a data model.
     *
     * @param UnitSpecification $unitSpecification
     * @param Model             $dataModel         The course data model to put the new unit data model in
     * @return Unit\Model
     */
    protected function translateToUnitDataModel(UnitSpecification $unitSpecification, Model $dataModel)
    {
        $unitDataModel = new Unit\Model();
        $unitDataModel
            ->setCourse($dataModel)
            ->setId($unitSpecification->getId())
            ->setFolder($unitSpecification->getFolder())
            ->setCreatedAt($unitSpecification->getCreatedAt())
            ->setUpdatedAt($unitSpecification->getUpdatedAt())
            ->setDeletedAt($unitSpecification->getDeletedAt());
        // TODO add translation of assessables
        return $unitDataModel;
    }

    /**
     * Translates the data model to a domain model.
     *
     * @param Unit\Model $unitDataModel
     * @return UnitSpecification
     */
    protected function translateToUnitDomainModel(Unit\Model $unitDataModel)
    {
        $unitSpecification = new UnitSpecification();
        $unitSpecification
            ->setId($unitDataModel->getId())
            ->setFolder($unitDataModel->getFolder())
            ->setCreatedAt($unitDataModel->getCreatedAt())
            ->setUpdatedAt($unitDataModel->getUpdatedAt())
            ->setDeletedAt($unitDataModel->getDeletedAt());
        // TODO add translation of assessables
        return $unitSpecification;
    }
}
