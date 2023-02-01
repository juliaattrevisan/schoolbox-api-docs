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
     * into:
     * * a discriminator value, which specifies the type of object
     * * an object value, which contains the object's data
     *
     * @param Collection<array{id: int, name: string, discriminator: string}> $results
     * @return Collection<array{discriminator: string, object: array|null}>
     */
    public function hydrateResults(Collection $results): Collection
    {
        // Fetch results from various mappers
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
}
