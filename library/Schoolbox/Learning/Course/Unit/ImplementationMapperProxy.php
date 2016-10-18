<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Translator;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper;
use Schoolbox\Learning\Course\DataModel\Implementation\Unit\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Learning\Course\Implementation as CourseImplementation;

/**
 * A proxy that lies in front of `Schoolbox\Learning\Course\DataModel\Implementation\Unit\Mapper`.
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
     * Constructor
     *
     * @param Mapper     $mapper
     * @param Translator $translator
     */
    public function __construct(Mapper $mapper, Translator $translator)
    {
        $this->mapper     = $mapper;
        $this->translator = $translator;
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
            return $this->translator->translateToDomainModel($this->mapper->findById($id));
        } catch (Exception\Deleted $e) {
            throw $this->repopulateDeletedException($e);
        }
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
}
