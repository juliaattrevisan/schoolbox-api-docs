<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

/**
 * Translation class between a Unit Specification and its data model.
 */
class Translator
{
    /**
     * Translates the Unit Specification business domain model to a data model.
     *
     * @param UnitSpecification $specification
     * @return Model
     */
    public function translateToDataModel(CourseSpecification $specification, UnitSpecification)
    {
            $unitDataModel = new Model();
            $unitDataModel
                ->setCourse($dataModel)
                ->setId($unitSpecification->getId())
                ->setFolder($unitSpecification->getFolder())
                ->setCreatedAt($specification->getCreatedAt())
                ->setUpdatedAt($specification->getUpdatedAt())
                ->setDeletedAt($specification->getDeletedAt())
            ;
            // TODO add translation of assessables
            $dataModel->getUnits()->add($unitDataModel);

        // If I am going to persist here, I need mappers to have been injected.
        return $dataModel;
    }

    /**
     * Translates the data model to a domain model.
     *
     * @param Model $dataModel
     * @return UnitSpecification
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
            // TODO move to a translator for unit specs
            $unitSpecification = new UnitSpecification();
            $unitSpecification
                ->setId($unitDataModel->getId())
                ->setFolder($unitDataModel->getFolder())
                ->setCreatedAt($unitDataModel->getCreatedAt())
                ->setUpdatedAt($unitDataModel->getUpdatedAt())
                ->setDeletedAt($unitDataModel->getDeletedAt())
            ;
            // TODO add translation of assessables
            $specification->getUnits()->add($unitSpecification);
        }

        return $specification;
    }
}
