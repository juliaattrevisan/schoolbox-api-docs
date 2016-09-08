<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification;

use Schoolbox\Learning\Course\Specification as CourseSpecification;

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
            // TODO move to a translator for unit specs
            $unitDataModel = new Unit\Model();
            $unitDataModel
                ->setCourse($dataModel)
                ->setId($unitSpecification->getId())
                ->setFolder($unitSpecification->getFolder())
                ->setCreatedAt($specification->getCreatedAt())
                ->setUpdatedAt($specification->getUpdatedAt())
                ->setDeletedAt($specification->getDeletedAt())
            ;
            $dataModel->getUnits()->add($unitDataModel);
        }

        // If I am going to persist here, I need mappers to have been injected.
        return $dataModel;
    }
}
