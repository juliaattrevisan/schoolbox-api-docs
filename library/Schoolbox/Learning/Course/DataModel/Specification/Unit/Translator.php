<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\DataModel\Specification\Unit;

use Schoolbox\Learning\Course\DataModel\Specification\Model as CourseDataModel;
use Schoolbox\Learning\Course\Specification as CourseSpecification;
use Schoolbox\Learning\Course\Unit\Specification as UnitSpecification;
use Schoolbox\Resource\Folder\Model as FolderModel;

/**
 * Translation class between a Unit Specification and its data model.
 */
class Translator
{
    /**
     * Translates the Unit Specification business domain model to a data model.
     *
     * @param UnitSpecification   $unitSpecification
     * @param CourseSpecification $courseSpecification The course data model to put the new unit data model in.
     * @return Model
     */
    public function translateToDataModel(UnitSpecification $unitSpecification, CourseSpecification $courseSpecification)
    {
        // Don't perform a full translation here?
        $courseDataModel = new CourseDataModel();
        $courseDataModel->setId($courseSpecification->getId());

        $unitDataModel = new Model();
        $unitDataModel
            ->setCourse($courseDataModel)
            ->setId($unitSpecification->getId())
            ->setCreatedAt($unitSpecification->getCreatedAt())
            ->setUpdatedAt($unitSpecification->getUpdatedAt())
            ->setDeletedAt($unitSpecification->getDeletedAt());

        // Allow translate if folder is empty, for now.
        if ($unitSpecification->getFolder() instanceof FolderModel) {
            $unitDataModel->setFolder($unitSpecification->getFolder());
        }

        // Set assessables directly is okay for now
        // TODO add translation of assessables, or verify whether translation is required
        $unitDataModel->setAssessables($unitSpecification->getAssessables());
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
            ->setCreatedAt($unitDataModel->getCreatedAt())
            ->setUpdatedAt($unitDataModel->getUpdatedAt())
            ->setDeletedAt($unitDataModel->getDeletedAt());

        // Allow translate if folder is empty, for now.
        if ($unitDataModel->getFolder() instanceof FolderModel) {
            $unitSpecification->setFolder($unitDataModel->getFolder());
        }

        // Basic translation of course spec
        $unitSpecification->setCourse(
            (new CourseSpecification())
                ->setId($unitDataModel->getCourse()->getId())
                ->setCreatedAt($unitDataModel->getCourse()->getCreatedAt())
                ->setUpdatedAt($unitDataModel->getCourse()->getUpdatedAt())
                ->setDeletedAt($unitDataModel->getCourse()->getDeletedAt())
        );

        // Allow translate if course folder is empty
        if ($unitDataModel->getCourse()->getFolder() instanceof FolderModel) {
            $unitSpecification->getCourse()->setFolder($unitDataModel->getCourse()->getFolder());
        }

        // Set assessables directly is okay for now
        // TODO add translation of assessables, or verify whether translation is required
        $unitSpecification->setAssessables($unitDataModel->getAssessables());
        return $unitSpecification;
    }
}
