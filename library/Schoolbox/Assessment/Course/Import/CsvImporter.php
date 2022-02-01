<?php

/**
 * @copyright Copyright 2022, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Assessment\Course\Import;

use Alaress\Csv\Reader;
use Alaress_Date_Format;
use Schoolbox\Collection;
use Schoolbox\Learning\Assessment\DataModel\MapperProxy;
use Schoolbox\Learning\Assessment\Project;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\Resource\Folder\Mapper as FolderMapper;
use Schoolbox\Learning\Course\DataModel\Implementation as CourseImplementation;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpecification;
use Schoolbox\UserMapper;

class CsvImporter
{
    private FolderMapper $folderMapper;

    private UserMapper $userMapper;

    private CourseImplementation\Mapper $courseImplementationMapper;

    private UnitSpecification\Mapper $unitSpecificationMapper;

    private ImportFactory $importFactory;

    private MapperProxy $assessmentMapperProxy;

    public function __construct(
        FolderMapper $folderMapper,
        UserMapper $userMapper,
        CourseImplementation\Mapper $courseImplementationMapper,
        UnitSpecification\Mapper $unitSpecificationMapper,
        ImportFactory $importFactory,
        MapperProxy $assessmentMapperProxy
    ) {
        $this->folderMapper               = $folderMapper;
        $this->userMapper                 = $userMapper;
        $this->courseImplementationMapper = $courseImplementationMapper;
        $this->unitSpecificationMapper    = $unitSpecificationMapper;
        $this->importFactory              = $importFactory;
        $this->assessmentMapperProxy      = $assessmentMapperProxy;
    }

    /**
     * @param resource $resourceStream
     * @param string   $defaultDueTime
     * @param bool     $perform
     * @return array
     * @throws MapperException
     * @throws MapperException\Deleted
     */
    public function import($resourceStream, string $defaultDueTime, bool $perform): array
    {
        $this->folderMapper->getDatabase()->beginTransaction();

        $csv = (new Reader($resourceStream))->setLoadFromFirst(true);

        // [unit_spec_id][class_folder_id]['open_date']
        // [unit_spec_id][class_folder_id]['close_date']
        // [unit_spec_id][class_folder_id]['a'][assessment_spec_id]['publish']
        // [unit_spec_id][class_folder_id]['a'][assessment_spec_id]['open']
        // [unit_spec_id][class_folder_id]['a'][assessment_spec_id]['due']
        // [unit_spec_id][class_folder_id]['a'][assessment_spec_id]['close']
        $assessmentData = [];

        $unitSpecs     = $this->unitSpecificationMapper->createCollection();
        $foldersByCode = $this->folderMapper->createCollection();
        $ownerUserIds  = [];

        foreach ($csv as $row) {
            // impl folder to import
            $classCode = $row['Class Code'];
            if (!$foldersByCode->offsetExists($classCode)) {
                try {
                    $implFolder = $this->folderMapper->findByCode($classCode);
                    $foldersByCode->offsetSet($classCode, $implFolder);
                } catch (MapperException $e) {
                    continue;
                }
            }
            $implFolder = $foldersByCode->offsetGet($classCode);
            // get unit spec
            $unitSpecFolderId = $row['Unit Folder ID'];
            if (!$unitSpecs->offsetExists($unitSpecFolderId)) {
                try {
                    $unitSpec    = $this->unitSpecificationMapper->findByFolderId($unitSpecFolderId);
                    $options     = ['order' => ['sequence' => 'ASC']];
                    $assessments = $this->assessmentMapperProxy->findAllByUnitSpecificationId($unitSpec->getId(), $options)->indexBy('id');
                    $unitSpec->setAssessables($assessments);
                    $unitSpecs->offsetSet($unitSpecFolderId, $unitSpec);
                } catch (MapperException $e) {
                    continue;
                }
            }
            $unitSpec = $unitSpecs->offsetGet($unitSpecFolderId);

            $unitOpenDate  = Alaress_Date_Format::newLocalShort($row['Unit Open Date']);
            $unitCloseDate = Alaress_Date_Format::newLocalShort($row['Unit Close Date']);
            $dueDate       = Alaress_Date_Format::newLocalShort($row['Assessment Due Date']);
            $dueDate->setTimeFromString($defaultDueTime);
            $assessmentSpecId = (int)$row['Assessment ID'];

            $assessmentData[$unitSpec->getFolder()->getId()][$implFolder->getId()]['open_date']            = $unitOpenDate;
            $assessmentData[$unitSpec->getFolder()->getId()][$implFolder->getId()]['close_date']           = $unitCloseDate;
            $assessmentData[$unitSpec->getFolder()->getId()][$implFolder->getId()]['a'][$assessmentSpecId] = [
                'publish' => Alaress_Date_Format::newLocalShort($row['Assessment Publish Date']),
                'open'    => Alaress_Date_Format::newLocalShort($row['Assessment Open Date']),
                'due'     => $dueDate,
                'close'   => Alaress_Date_Format::newLocalShort($row['Assessment Late Date']),
                'owner'   => (int)$row['Owner User ID'],
            ];

            if (!in_array((int)$row['Owner User ID'], $ownerUserIds)) {
                $ownerUserIds[] = (int)$row['Owner User ID'];
            }
        }

        $ownerUsers = $this->userMapper->findByColumn('id', $ownerUserIds)->indexBy('id');
        // Store courseImpl so that units in the same course will be added to the same courseImpl
        // indexed as [courseImplId][classFolderId]
        $existingCourseImpls = [];

        $imported = [];
        foreach ($assessmentData as $unitSpecFolderId => $folderAssessments) {
            /** @var UnitSpecification\Model $unitSpec */
            $unitSpec = $unitSpecs->offsetGet($unitSpecFolderId);

            // Calling getCourse() as $courseSpec = $unitSpec->getFolder()->getParent()->getCourseSpecification() does not work
            $courseSpec = $unitSpec->getCourse();
            // Collect folders for unit and course pages - needed for criteria import

            $folderIds    = array_keys($folderAssessments);
            $classFolders = $foldersByCode->filter(
                static function ($folder) use ($folderIds) {
                    return in_array($folder->getId(), $folderIds);
                }
            );

            $courseImpls = [];
            foreach ($classFolders as $classFolder) {
                $existingCourseImpl = $existingCourseImpls[$courseSpec->getId()][$classFolder->getId()] ?? null;
                $courseImpls        = $courseImpls + $this->importFactory->createCourseImpls($courseSpec, [$unitSpec->getId()], new Collection([$classFolder]), $existingCourseImpl);
            }

            // Create any un-imported UnitImpls from corresponding UnitSpec
            // Update any already-imported UnitImpls
            // Create any un-imported AssessmentImpls from corresponding AssessmentSpec
            // Update any already-imported AssessmentImpls

            foreach ($courseImpls as $classFolderId => $courseImpl) {
                if (!isset($existingCourseImpls[$courseSpec->getId()][$classFolderId])) {
                    $existingCourseImpls[$courseSpec->getId()][$classFolderId] = $courseImpl;
                }
                // Even if an import-modify, we need to explicitly save to update timestamps (and possibly the folderId)
                $this->courseImplementationMapper->save($courseImpl);

                // For each UnitImpl in POST data
                $unitData          = $folderAssessments[$classFolderId];
                $unitImplOpenDate  = Alaress_Date_Format::newLocalShort($unitData['open_date']);
                $unitImplCloseDate = Alaress_Date_Format::newLocalShort($unitData['close_date'])->endOfDay();

                $unitImpl = $this->importFactory->importUnit($courseImpl, $unitSpec, $unitImplOpenDate, $unitImplCloseDate);

                // Create/Update AssessmentImpls
                foreach ($unitData['a'] as $assessSpecId => $aData) {
                    $owner = $ownerUsers->getOffset($aData['owner']);
                    if (!$owner) {
                        continue;
                    }
                    if ($assessSpecId === 0) {
                        $specAssessments = $unitSpec->getAssessables();
                    } else {
                        $specAssessment = $unitSpec->getAssessables()->getOffset($assessSpecId);
                        if (!$specAssessment) {
                            continue;
                        }
                        $specAssessments = (new Collection())->add($specAssessment);
                    }
                    foreach ($specAssessments as $specAssessment) {
                        $implAssessment = $this->importFactory->importAssessment(null, $specAssessment, $unitImpl, false, $owner, $aData);
                        if ($implAssessment instanceof Project) {
                            foreach ($specAssessment->getAssessables() as $projectAssessment) {
                                $this->importFactory->importAssessment(null, $projectAssessment, $implAssessment, false, $owner, $aData);
                            }
                        }
                    }
                }

                // Associate the CourseImpl with a CourseOutline Component
                $componentInstance = $this->importFactory->findAvailableCourseOutlineComponent($classFolderId, $courseImpl->getId());
                $this->importFactory->injectImportIntoComponent($courseImpl, $componentInstance);

                $imported[] = $unitImpl;
            }
        }

        if ($perform) {
            $this->folderMapper->getDatabase()->commit();
        } else {
            $this->folderMapper->getDatabase()->rollback();
        }

        return $imported;
    }
}
