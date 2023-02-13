<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage;

use Schoolbox\Collection;
use Schoolbox\Learning\Assessment;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;

/**
 * Hydrator for curriculum usage results.
 *
 * Converts the results from `\Schoolbox\Curriculum\Api\Usage\Cursor::retrieveResults`
 * into a set of more meaningful results.
 */
class Hydrator
{
    /**
     * Constructor
     *
     * @param Folder\Mapper                      $folderMapper         Mapper for folder objects
     * @param Folder\Translator\IModelToArray    $folderTranslator     Translator for folder objects
     * @param CourseSpec\Mapper                  $courseSpecMapper     Mapper for course objects
     * @param CourseSpec\Translator\ModelToArray $courseTranslator     Translator for course objects
     * @param UnitSpec\Mapper                    $unitSpecMapper       Mapper for unit objects
     * @param UnitSpec\Translator\ModelToArray   $unitTranslator       Translator for unit objects
     * @param Assessment\DataModel\MapperProxy   $assessmentDataSource Data source for assessment objects
     * @param Translator\Assessment\ModelToArray $assessmentTranslator Translator for assessment objects
     */
    public function __construct(
        private Folder\Mapper $folderMapper,
        private Folder\Translator\IModelToArray $folderTranslator,
        private CourseSpec\Mapper $courseSpecMapper,
        private CourseSpec\Translator\ModelToArray $courseTranslator,
        private UnitSpec\Mapper $unitSpecMapper,
        private UnitSpec\Translator\ModelToArray $unitTranslator,
        private Assessment\DataModel\MapperProxy $assessmentDataSource,
        private Translator\Assessment\ModelToArray $assessmentTranslator,
    ) {
    }

    /**
     * Hydrates `\Schoolbox\Curriculum\Api\Usage\Cursor::retrieveResults` into:
     * * a discriminator value, which specifies the type of object
     * * an object value, which contains the object's data
     *
     * @param Collection<array{id: int, name: string, discriminator: string}> $results
     * @return Collection<array{discriminator: string, object: array|null}>
     */
    public function hydrateResults(Collection $results): Collection
    {
        // Fetch results from various mappers
        $models        = $this->findModelsForResults($results);
        $models        = $this->assignParentFoldersToModels($models);
        $models        = $this->assignUnitSpecsToModels($models);
        $newCollection = new Collection();
        foreach ($results as $result) {
            $disc        = $result['discriminator'];
            $id          = $result['id'];
            $model       = $models[$disc][$id] ?? null;
            $translation = null;
            if (isset($model)) {
                $translation = match ($disc) {
                    Cursor::DISCRIMINATOR_FOLDER     => call_user_func($this->folderTranslator, $model),
                    Cursor::DISCRIMINATOR_COURSE     => call_user_func($this->courseTranslator, $model),
                    Cursor::DISCRIMINATOR_UNIT       => call_user_func($this->unitTranslator, $model),
                    Cursor::DISCRIMINATOR_ASSESSMENT => call_user_func($this->assessmentTranslator, $model),
                    default                          => null,
                };
            }
            $newCollection->add([
                'discriminator' => $disc,
                'object'        => $translation,
            ]);
        }
        return $newCollection;
    }

    /**
     * Finds models for results.
     *
     * @param Collection<array{id: int, name: string, discriminator: string}> $results
     * @return array<string, Collection>
     */
    private function findModelsForResults(Collection $results): array
    {
        $models    = [];
        $mapId     = fn (array $result) => $result['id'];
        $folderIds = $results
            ->filter($this->buildFilter(Cursor::DISCRIMINATOR_FOLDER))
            ->map($mapId)
            ->getAll();
        $models[Cursor::DISCRIMINATOR_FOLDER] = $this->folderMapper->findByColumn('id', $folderIds)->indexBy('id');

        $courseIds = $results
            ->filter($this->buildFilter(Cursor::DISCRIMINATOR_COURSE))
            ->map($mapId)
            ->getAll();
        $models[Cursor::DISCRIMINATOR_COURSE] = $this->courseSpecMapper->findByColumn('id', $courseIds)->indexBy('id');

        $unitIds = $results
            ->filter($this->buildFilter(Cursor::DISCRIMINATOR_UNIT))
            ->map($mapId)
            ->getAll();
        $models[Cursor::DISCRIMINATOR_UNIT] = $this->unitSpecMapper->findByColumn('id', $unitIds)->indexBy('id');

        $assessmentIds = $results
            ->filter($this->buildFilter(Cursor::DISCRIMINATOR_ASSESSMENT))
            ->map($mapId)
            ->getAll();
        $models[Cursor::DISCRIMINATOR_ASSESSMENT] = $this->assessmentDataSource->findAllByIds($assessmentIds)->indexBy('id');
        return $models;
    }

    /**
     * Build a filter function for finding results which match this
     * discriminator.
     *
     * @param string $discriminator
     * @return callable
     */
    private function buildFilter(string $discriminator): callable
    {
        return fn (array $result) => $result['discriminator'] === $discriminator;
    }

    /**
     * Assign parent folders to the various results within `$models`:
     * - Folder results
     * - The folder of course results
     * - The folder and course's folder of unit results
     *
     * @param array<string, Collection> $models
     * @return array<string, Collection> `$models` with parent folders assigned
     */
    private function assignParentFoldersToModels(array $models): array
    {
        // Collate folder IDs
        $folderIds = [];
        foreach ($models[Cursor::DISCRIMINATOR_FOLDER] ?? [] as $folder) {
            /** @var Folder\Model $folder */
            $folderIds[] = $folder->getId();
        }
        foreach ($models[Cursor::DISCRIMINATOR_COURSE] ?? [] as $course) {
            /** @var CourseSpec\Model $course */
            $folderIds[] = $course->getFolder()->getId();
        }
        foreach ($models[Cursor::DISCRIMINATOR_UNIT] ?? [] as $unit) {
            /** @var UnitSpec\Model $unit */
            $folderIds[] = $unit->getFolder()->getId();
            $folderIds[] = $unit->getCourse()->getFolder()->getId();
        }

        // Look up parent folders
        $allFolders         = $this->folderMapper->findByColumn('id', $folderIds);
        $allFolderParentIds = $allFolders->map(fn(Folder\Model $folder) => $folder->getParent()?->getId());
        $allFolderParents   = $this->folderMapper->findByColumn('id', $allFolderParentIds)->indexBy('id');

        // Assign parent folders
        foreach ($models[Cursor::DISCRIMINATOR_FOLDER] ?? [] as $folder) {
            /** @var Folder\Model $folder */
            $parentId = $folder->getParent()?->getId();
            if (isset($parentId, $allFolderParents[$parentId])) {
                $folder->setParent($allFolderParents[$parentId]);
            }
        }
        foreach ($models[Cursor::DISCRIMINATOR_COURSE] ?? [] as $course) {
            /** @var CourseSpec\Model $course */
            $parentId = $course->getFolder()->getParent()?->getId();
            if (isset($parentId, $allFolderParents[$parentId])) {
                $course->getFolder()->setParent($allFolderParents[$parentId]);
            }
        }
        foreach ($models[Cursor::DISCRIMINATOR_UNIT] ?? [] as $unit) {
            /** @var UnitSpec\Model $unit */
            $courseParentId = $unit->getCourse()->getFolder()->getParent()?->getId();
            if (isset($courseParentId, $allFolderParents[$courseParentId])) {
                $unit->getCourse()->getFolder()->setParent($allFolderParents[$courseParentId]);
            }
            $unitParentId = $unit->getFolder()->getParent()?->getId();
            if (isset($unitParentId, $allFolderParents[$unitParentId])) {
                $unit->getFolder()->setParent($allFolderParents[$unitParentId]);
            }
        }
        return $models;
    }

    /**
     * Assign fully hydrated unit specs to the assessments within `$models`:
     *
     * @param array<string, Collection> $models
     * @return array<string, Collection> `$models` with units assigned
     */
    private function assignUnitSpecsToModels(array $models): array
    {
        $unitSpecIds = [];
        foreach ($models[Cursor::DISCRIMINATOR_ASSESSMENT] ?? [] as $assessment) {
            /** @var Assessment\IAssessable $assessment */
            $context = $assessment->getContext();
            if ($context instanceof UnitSpec\Model) {
                $unitSpecIds[] = $context->getId();
//            } elseif ($context instanceof Assessment\Project && $context->getContext() instanceof UnitSpec\Model) {
//                $unitSpecIds[] = $context->getContext()->getId();
            }
        }
        $unitSpecs = $this->unitSpecMapper->findByColumn('id', $unitSpecIds);

        foreach ($models[Cursor::DISCRIMINATOR_ASSESSMENT] ?? [] as $assessment) {
            /** @var Assessment\IAssessable $assessment */
            $context = $assessment->getBaseContext();
            if ($context instanceof UnitSpec\Model) {
                $unitSpec = $unitSpecs[$context->getId()] ?? null;
                if ($unitSpec) {
                    $assessment->setContext($unitSpec);
                }
//            } elseif ($context instanceof Assessment\Project && $context->getContext() instanceof UnitSpec\Model) {
//                $unitSpec = $unitSpecs[$context->getContext()->getId()] ?? null;
//                if ($unitSpec) {
//                    $context->setContext($unitSpec);
//                }
            }
        }

        return $models;
    }
}
