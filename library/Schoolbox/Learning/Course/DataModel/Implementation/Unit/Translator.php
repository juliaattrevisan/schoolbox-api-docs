<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Implementation\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation\Model as CourseImplementationDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseSpecificationDataModel;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model as UnitSpecificationDataModel;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
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
     * @param UnitImplementation $unitImpl
     * @return Model
     */
    public function translateToDataModel(UnitImplementation $unitImpl)
    {
        // Is there ever a need to get a data model without persisting?
        // I don't know?
        $dataModel = new Model();
        $dataModel
            ->setId($unitImpl->getId())
            ->setTitle($unitImpl->getTitle())
            ->setOpenDate($unitImpl->getOpenDate())
            ->setDueDate($unitImpl->getDueDate())
            ->setCreatedAt($unitImpl->getCreatedAt())
            ->setUpdatedAt($unitImpl->getUpdatedAt())
            ->setDeletedAt($unitImpl->getDeletedAt())
            ->setAssessables(new Collection())
        ;

        // Basic course and spec translation
        // TODO figure out if these need proper translation
        if ($unitImpl->getSpecification() instanceof UnitSpecification) {
            $specModel = new UnitSpecificationDataModel();
            $specModel->setId($unitImpl->getSpecification()->getId());
            $dataModel->setSpecification($specModel);
        }

        if ($unitImpl->getCourse() instanceof CourseImplementation) {
            $courseModel = new CourseImplementationDataModel();
            $courseModel->setId($unitImpl->getCourse()->getId());
            $dataModel->setCourse($courseModel);
        }

        // Set assessables directly is okay for now
        // TODO add translation of assessables, or verify whether translation is required
        $dataModel->setAssessables($unitImpl->getAssessables());

        // If I am going to persist here, I need mappers to have been injected.
        return $dataModel;
    }

    /**
     * Translates the data model to a domain model.
     *
     * @param Model $dataModel
     * @return UnitImplementation
     */
    public function translateToDomainModel(Model $dataModel)
    {
        $unitImpl = new UnitImplementation();
        $unitImpl
            ->setId($dataModel->getId())
            ->setTitle($dataModel->getTitle())
            ->setOpenDate($dataModel->getOpenDate())
            ->setDueDate($dataModel->getDueDate())
            ->setCreatedAt($dataModel->getCreatedAt())
            ->setUpdatedAt($dataModel->getUpdatedAt())
            ->setDeletedAt($dataModel->getDeletedAt())
            ->setAssessables(new Collection())
        ;

        // Basic course and spec translation
        if ($dataModel->getSpecification() instanceof UnitSpecificationDataModel) {
            $unitSpec = new UnitSpecification();
            $unitSpec->setId($dataModel->getSpecification()->getId());
            $unitImpl->setSpecification($unitSpec);
        }

        if ($dataModel->getCourse() instanceof CourseImplementationDataModel) {
            $courseImpl = new CourseImplementation();
            $courseImpl->setId($dataModel->getCourse()->getId());
            $unitImpl->setCourse($courseImpl);
        }

        // Set assessables directly is okay for now
        // TODO add translation of assessables, or verify whether translation is required
        $unitImpl->setAssessables($dataModel->getAssessables());

        return $unitImpl;
    }
}
