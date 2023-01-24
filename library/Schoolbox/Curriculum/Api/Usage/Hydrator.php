<?php

/**
 * @copyright Copyright 2023, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Curriculum\Api\Usage;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification as CourseSpec;
use Schoolbox\Learning\Course\DataModel\Specification\Unit as UnitSpec;
use Schoolbox\Resource\Folder;
use Schoolbox\Resource\Folder\Translator\ModelToArray\LinkOnly;

class Hydrator
{
    /**
     * Constructor
     *
     * @param LinkOnly                           $folderTranslator Translator for folder objects
     * @param CourseSpec\Translator\ModelToArray $courseTranslator Translator for course objects
     * @param UnitSpec\Translator\ModelToArray   $unitTranslator   Translator for unit objects
     */
    public function __construct(
        private Folder\Mapper $folderMapper,
        private LinkOnly $folderTranslator,
        private CourseSpec\Mapper $courseSpecMapper,
        private CourseSpec\Translator\ModelToArray $courseTranslator,
        private UnitSpec\Mapper $unitSpecMapper,
        private UnitSpec\Translator\ModelToArray $unitTranslator,
    ) {
    }

    /**
     * Hydrates the results from `\Schoolbox\Curriculum\Api\Usage\Cursor::retrieveResults`
     * into a set of actual objects.
     *
     * @param Collection $results
     * @return Collection Each item  will contain:
     *                    * discriminator: identifier of object's type
     *                    * object:        the actual object
     */
    public function hydrateResults(Collection $results): Collection
    {
        // Fetch results from various mappers
        $models    = [];
        $folderIds = $results
            ->filter(fn (array $result) => $result['discriminator'] === Cursor::DISCRIMINATOR_FOLDER)
            ->map(fn (array $result) => $result['id'])
            ->getAll();
        $models[Cursor::DISCRIMINATOR_FOLDER] = $this->folderMapper->findByColumn('id', $folderIds)->indexBy('id');

        $courseIds = $results
            ->filter(fn (array $result) => $result['discriminator'] === Cursor::DISCRIMINATOR_COURSE)
            ->map(fn (array $result) => $result['id'])
            ->getAll();
        $models[Cursor::DISCRIMINATOR_COURSE] = $this->courseSpecMapper->findByColumn('id', $courseIds)->indexBy('id');

        $unitIds = $results
            ->filter(fn (array $result) => $result['discriminator'] === Cursor::DISCRIMINATOR_UNIT)
            ->map(fn (array $result) => $result['id'])
            ->getAll();
        $models[Cursor::DISCRIMINATOR_UNIT] = $this->unitSpecMapper->findByColumn('id', $unitIds)->indexBy('id');

        $newCollection = new Collection();
        foreach ($results as $result) {
            $disc        = $result['discriminator'];
            $id          = $result['id'];
            $model       = $models[$disc][$id] ?? null;
            $translation = null;
            if (isset($model)) {
                $translation = match ($disc) {
                    Cursor::DISCRIMINATOR_FOLDER => call_user_func($this->folderTranslator, $model),
                    Cursor::DISCRIMINATOR_COURSE => call_user_func($this->courseTranslator, $model),
                    Cursor::DISCRIMINATOR_UNIT   => call_user_func($this->unitTranslator, $model),
                    default                      => null,
                };
            }
            $newCollection->add([
                'discriminator' => $disc,
                'object'        => $translation,
            ]);
        }
        return $newCollection;
    }
}
