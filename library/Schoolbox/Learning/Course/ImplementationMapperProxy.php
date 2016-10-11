<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation\Translator;
use Schoolbox\Learning\Course\DataModel\Implementation\Mapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Model;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator as UnitTranslator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper as UnitMapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model as UnitModel;
use Schoolbox\Mapper\Exception;

/**
 * A proxy that lies in front of `Schoolbox\Learning\Course\DataModel\Implementation\Mapper`.
 *
 * It exists so that CRUD actions return and accept domain objects,
 * instead of data model objects.
 */
class ImplementationMapperProxy
{
    /**
     * @var Mapper
     */
    private $mapper;

    /**
     * @var Translator
     */
    private $translator;

    /**
     * @var UnitMapper
     */
    private $unitMapper;

    /**
     * @var UnitTranslator
     */
    private $unitTranslator;

    /**
     * Constructor
     *
     * @param Mapper         $mapper
     * @param Translator     $translator
     * @param UnitMapper     $unitMapper
     * @param UnitTranslator $unitTranslator
     */
    public function __construct(
        Mapper $mapper,
        Translator $translator,
        UnitMapper $unitMapper,
        UnitTranslator $unitTranslator
    ) {
        $this->mapper         = $mapper;
        $this->translator     = $translator;
        $this->unitMapper     = $unitMapper;
        $this->unitTranslator = $unitTranslator;
    }

    /**
     * Proxy for `Mapper->findById()`.
     *
     * @param int $id
     * @return Implementation
     * @throws Exception if object wasn't found
     * @throws Exception\Deleted if object was deleted
     */
    public function findById($id)
    {
        try {
            return $this->loadUnits(
                $this->translator->translateToDomainModel($this->mapper->findById($id))
            );
        } catch (Exception\Deleted $e) {
            throw $this->repopulateDeletedException($e);
        }
    }

    /**
     * Proxy for `Mapper->findByFolderId()`.
     *
     * @param int $folderId
     * @param array $options
     * @return Implementation
     * @throws Exception if object wasn't found
     * @throws Exception\Deleted if object was deleted
     */
    public function findByFolderId($folderId, $options = [])
    {
        try {
            return $this->loadUnits(
                $this->translator->translateToDomainModel($this->mapper->findByFolderId($folderId, $options))
            );
        } catch (Exception\Deleted $e) {
            throw $this->repopulateDeletedException($e);
        }
    }

    /**
     * Proxy for `Mapper->findAll()`.
     *
     * @param array $options
     * @return Collection|Implementation[]
     */
    public function findAll($options = [])
    {
        return $this->loadUnitsForCollection(
            $this->mapper
                ->findAll($options)
                ->map([$this->translator, 'translateToDomainModel'])
        );
    }

    /**
     * Proxy for `Mapper->findAllByName()`.
     *
     * @param string $name
     * @return Collection|Implementation[]
     */
    public function findAllByName($name)
    {
        return $this->loadUnitsForCollection(
            $this->mapper
                ->findAllByName($name, ['order' => ['f.name' => 'ASC' ]])
                ->map([$this->translator, 'translateToDomainModel'])
        );
    }

    /**
     * Proxy for `Mapper->save()`.
     *
     * @param Implementation $implementation
     * @return Implementation
     */
    public function save(Implementation $implementation)
    {
        // Convert to data model, then save
        $dataModel = $this->translator->translateToDataModel($implementation);
        $this->mapper->save($dataModel);
        // Assign ID
        $implementation->setId($dataModel->getId());

        // Save units
        $existingUnits = $this->unitMapper->findAllByCourseId($implementation->getId());
        $savedUnits    = new Collection();
        foreach ($implementation->getUnits() as $unit) {
            $savedUnits->add($this->unitTranslator->translateToDataModel($unit, $implementation));
        }
        $this->unitMapper->save($savedUnits);

        // Any units that exist but aren't in $implementation->getUnits() are deleted!
        $this->unitMapper->delete($existingUnits->diff($savedUnits, function (UnitModel $unit) {
            return $unit->getId();
        }));

        return $implementation;
    }

    /**
     * Proxy for `Mapper->delete()`.
     *
     * @param Implementation $implementation
     * @return Implementation
     */
    public function delete(Implementation $implementation)
    {
        // Convert to data model, then delete
        $dataModel = $this->translator->translateToDataModel($implementation);
        $this->mapper->delete($dataModel);

        // Delete all units that are a part of this unit (non-negotiable)
        $this->unitMapper->delete($this->unitMapper->findAllByCourseId($implementation->getId()));
        return $implementation;
    }

    /**
     * Helper for populating a deleted exception with a course implementation,
     * instead of the data model object.
     *
     * @param Exception\Deleted $e
     * @return Exception\Deleted
     */
    protected function repopulateDeletedException(Exception\Deleted $e)
    {
        $deleted = $e->getDeletedObject();
        if ($deleted instanceof Model) {
            $e->setDeletedObject($this->translator->translateToDomainModel($deleted));
        }
        return $e;
    }

    /**
     * Loads all units implementations into a course implementation.
     *
     * @param Implementation $implementation
     * @return Implementation
     */
    protected function loadUnits(Implementation $implementation)
    {
        $collection = new Collection();
        $collection->add($implementation);
        $this->loadUnitsForCollection($collection);
        return $collection->first();
    }

    /**
     * Loads all units implementations into all the course implementations in a collection.
     *
     * @param Collection|Implementation[] $allSpecs
     * @return Collection|Implementation[]
     */
    protected function loadUnitsForCollection(Collection $allSpecs)
    {
        // Load all the unit specs for all the supplied course specs
        $ids = [];
        foreach ($allSpecs as $spec) {
            $ids[] = $spec->getId();
        }
        $allUnits = $this->unitMapper->findAllByCourseId($ids, ['order' => ['f.name' => 'asc']])
            ->groupByCallback(function ($v) {
                /** @var UnitModel $v */
                return $v->getCourse()->getId();
            });

        // Match unit specs to their course spec
        foreach ($allSpecs as $spec) {
            if (isset($allUnits[$spec->getId()])) {
                /** @var Collection|UnitModel[] $units */
                $units = $allUnits[$spec->getId()];
                $spec->setUnits($units->map([$this->unitTranslator, 'translateToDomainModel']));
            }
        }
        return $allSpecs;
    }
}
