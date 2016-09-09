<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseDataModel;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;

/**
 * Translation class between a Unit Specification and its data model.
 */
class Translator
{
    /**
     * Translates the Unit Specification business domain model to a data model.
     *
     * @param UnitSpecification $unitSpecification
     * @param CourseDataModel   $dataModel         The course data model to put the new unit data model in.
     * @return Model
     */
    public function translateToDataModel(UnitSpecification $unitSpecification, CourseDataModel $dataModel)
    {
        $unitDataModel = new Model();
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
     * @param Model $unitDataModel
     * @return UnitSpecification
     */
    public function translateToDomainModel(Model $unitDataModel)
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
