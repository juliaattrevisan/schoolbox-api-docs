<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course;

use Schoolbox\Learning\Course\DataModel\Specification\Translator;
use Schoolbox\Learning\Course\DataModel\Specification\Mapper;

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
        $this->mapper = $mapper;
    }

    /**
     * Proxy for `Mapper->findById()`.
     *
     * @param int $id
     * @return Specification
     */
    public function findById($id)
    {
        return $this->translator->translateToDomainModel($this->mapper->findById($id));
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
}
