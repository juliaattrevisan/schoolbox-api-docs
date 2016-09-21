<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Translator;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper;
use Schoolbox\Learning\Course\DataModel\Specification\Model;
use Schoolbox\Mapper\Exception;

/**
 * A proxy that lies in front of `Schoolbox\Learning\Course\DataModel\Specification\Mapper`.
 *
 * It exists so that CRUD actions return and accept domain objects,
 * instead of data model objects.
 */
class SpecificationMapperProxy
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
     * @return Specification
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
     * Proxy for `Mapper->findByFolderId()`.
     *
     * @param int $folderId
     * @param array $options
     * @return Specification
     * @throws Exception if object wasn't found
     * @throws Exception\Deleted if object was deleted
     */
    public function findByFolderId($folderId, $options = [])
    {
        try {
            return $this->translator->translateToDomainModel($this->mapper->findByFolderId($folderId, $options));
        } catch (Exception\Deleted $e) {
            throw $this->repopulateDeletedException($e);
        }
    }

    /**
     * Proxy for `Mapper->findAll()`.
     *
     * @param array $options
     * @return Collection|Specification[]
     */
    public function findAll($options = [])
    {
        return $this->mapper
            ->findAll($options)
            ->map([$this->translator, 'translateToDomainModel']);
    }

    /**
     * Proxy for `Mapper->findAllByName()`.
     *
     * @param string $name
     * @return Collection|Specification[]
     */
    public function findAllByName($name)
    {
        return $this->mapper
            ->findAllByName($name, ['order' => ['f.name' => 'ASC' ]])
            ->map([$this->translator, 'translateToDomainModel']);
    }

    /**
     * Proxy for `Mapper->save()`.
     *
     * @param Specification $specification
     * @return Specification
     */
    public function save(Specification $specification)
    {
        // Convert to data model, then save
        $dataModel = $this->translator->translateToDataModel($specification);
        $this->mapper->save($dataModel);
        // Assign ID
        $specification->setId($dataModel->getId());
        // TODO determine whether units should be saved here
        return $specification;
    }

    /**
     * Proxy for `Mapper->delete()`.
     *
     * @param Specification $specification
     * @return Specification
     */
    public function delete(Specification $specification)
    {
        // Convert to data model, then delete
        $dataModel = $this->translator->translateToDataModel($specification);
        $this->mapper->delete($dataModel);
        return $specification;
    }

    /**
     * Helper for populating a deleted exception with a course specification,
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
