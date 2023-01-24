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
        private LinkOnly $folderTranslator,
        private CourseSpec\Translator\ModelToArray $courseTranslator,
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
        // Fetch results
        $models    = [];
        $folderIds = $results
            ->filter(fn (array $result) => $result['discriminator'] === Cursor::DISCRIMINATOR_FOLDER)
            ->map(fn (array $result) => $result['id'])
            ->getAll();
        $models[Cursor::DISCRIMINATOR_FOLDER] = Folder\Mapper::getInstance()->findByColumn('id', $folderIds)->indexBy('id');

        $courseIds = $results
            ->filter(fn (array $result) => $result['discriminator'] === Cursor::DISCRIMINATOR_COURSE)
            ->map(fn (array $result) => $result['id'])
            ->getAll();
        $models[Cursor::DISCRIMINATOR_COURSE] = CourseSpec\Mapper::getInstance()->findByColumn('id', $courseIds)->indexBy('id');

        $unitIds = $results
            ->filter(fn (array $result) => $result['discriminator'] === Cursor::DISCRIMINATOR_UNIT)
            ->map(fn (array $result) => $result['id'])
            ->getAll();
        $models[Cursor::DISCRIMINATOR_UNIT] = UnitSpec\Mapper::getInstance()->findByColumn('id', $unitIds)->indexBy('id');

        // Hydrator for results:
        // folder: use ModelToArray
        // course: do something else
        // unit: do a different thing
        $translators = [
            Cursor::DISCRIMINATOR_FOLDER => $this->folderTranslator,
            Cursor::DISCRIMINATOR_COURSE => $this->courseTranslator,
            Cursor::DISCRIMINATOR_UNIT   => $this->unitTranslator,
        ];

        $newCollection = new Collection();
        foreach ($results as $result) {
            $disc = $result['discriminator'];
            $id   = $result['id'];
            $newCollection->add([
                'discriminator' => $disc,
                'object'        => call_user_func($translators[$disc], $models[$disc][$id]),
            ]);
        }
        return $newCollection;
    }
}
