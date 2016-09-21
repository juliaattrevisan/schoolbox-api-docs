<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Course\Unit;

use Schoolbox\Collection;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Translator;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper;
use Schoolbox\Learning\Course\DataModel\Specification\Unit\Model;
use Schoolbox\Mapper\Exception;
use Schoolbox\Learning\Course\Specification as CourseSpecification;

/**
 * A proxy that lies in front of `Schoolbox\Learning\Course\DataModel\Specification\Unit\Mapper`.
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
     * Proxy for `Mapper->findAllByCourseId()`.
     *
     * @param int|array $courseId One or more course IDs
     * @param array     $options
     * @return Specification[]|Collection
     */
    public function findAllByCourseId($courseId, $options = [])
    {
        return $this->mapper
            ->findAllByCourseId($courseId, $options)
            ->map([$this->translator, 'translateToDomainModel']);
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
     * Proxy for `Mapper->findAllByCourseFolderId()`.
     *
     * @param int|array $courseFolderId One or more folder IDs
     * @param array     $options
     * @return Specification[]|Collection
     */
    public function findAllByCourseFolderId($courseFolderId, $options = [])
    {
        return $this->mapper
            ->findAllByCourseFolderId($courseFolderId, $options)
            ->map([$this->translator, 'translateToDomainModel']);
    }

    /**
     * Proxy for `Mapper->save()`.
     *
     * @param Specification       $specification
     * @param CourseSpecification $courseSpecification The course spec which contains $specification
     * @return Specification
     */
    public function save(Specification $specification, CourseSpecification $courseSpecification)
    {
        // Convert to data model, then save
        $dataModel = $this->translator->translateToDataModel($specification, $courseSpecification);
        $this->mapper->save($dataModel);
        // Assign ID
        $specification->setId($dataModel->getId());
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
        // Don't need an intricate course spec
        $dataModel = $this->translator->translateToDataModel($specification, new CourseSpecification());
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
