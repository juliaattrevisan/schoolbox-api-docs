<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationDataModel;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Implementation as UnitImplementation;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Translation class between a Unit Implementation and its data model.
 */
class Translator
{
    /**
     * Translates the Unit Implementation business domain model to a data model.
     *
     * @param UnitImplementation $implementation
     * @return Model
     */
    public function translateToDataModel(UnitImplementation $implementation)
    {
        return new Model();
//        // Is there ever a need to get a data model without persisting?
//        // I don't know?
//        $dataModel = new Model();
//        $dataModel
//            ->setId($implementation->getId())
//            ->setCreatedAt($implementation->getCreatedAt())
//            ->setUpdatedAt($implementation->getUpdatedAt())
//            ->setDeletedAt($implementation->getDeletedAt())
//            ->setUnits(new Collection());
//        ;
//
//        // I am not going to translate the entire course spec. Just set its ID.
//        if ($implementation->getSpecification() instanceof CourseSpecification) {
//            $specModel = new CourseSpecificationDataModel();
//            $specModel->setId($implementation->getSpecification()->getId());
//            $dataModel->setSpecification($specModel);
//        }
//
//        // Allow translate if folder is empty, for now.
//        if ($implementation->getFolder() instanceof FolderModel) {
//            $dataModel->setFolder($implementation->getFolder());
//        }
//
//        // Constituent Unit Implementations
//        foreach ($implementation->getUnits() as $unitImplementation) {
//            $dataModel->getUnits()->add(new UnitImplementation());
//        }
//
//        // If I am going to persist here, I need mappers to have been injected.
//        return $dataModel;
    }

    /**
     * Translates the data model to a domain model.
     *
     * @param Model $dataModel
     * @return UnitImplementation
     */
    public function translateToDomainModel(Model $dataModel)
    {
        $implementation = new UnitImplementation();
        return $implementation;
//        $implementation
//            ->setId($dataModel->getId())
//            ->setCreatedAt($dataModel->getCreatedAt())
//            ->setUpdatedAt($dataModel->getUpdatedAt())
//            ->setDeletedAt($dataModel->getDeletedAt())
//            ->setUnits(new Collection())
//        ;
//
//        // Allow translate if folder is empty, for now.
//        if ($dataModel->getFolder() instanceof FolderModel) {
//            $implementation->setFolder($dataModel->getFolder());
//        }
//
////        // Constitute Unit Specs
////        foreach ($dataModel->getUnits() as $unitDataModel) {
////            $implementation->getUnits()->add($this->unitTranslator->translateToDomainModel($unitDataModel));
////        }
//
//        return $implementation;
    }
}
