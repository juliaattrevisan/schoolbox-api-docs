<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel;

use Schoolbox\Learning\Assessment\IAssessable;
use Schoolbox\Mapper\Exception as MapperException;

/**
 * Proxy mapper for IAssessable objects.
 *
 * It simulates some parts of a Mapper's interface, and acts as a proxy for mappers.
 */
class MapperProxy
{
    /**
     * The mapper for the base assessment table.
     *
     * @var Mapper
     */
    protected $baseMapper;

    /**
     * The translator between domain and data objects.
     *
     * @var Translator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param Mapper     $baseMapper
     * @param Translator $translator
     */
    public function __construct(Mapper $baseMapper, Translator $translator)
    {
        $this->baseMapper = $baseMapper;
        $this->translator = $translator;
    }

    /**
     * Finds an assessable by ID
     *
     * @param int $id
     * @return IAssessable
     * @throws MapperException\Deleted if the assessment was deleted
     */
    public function findById($id)
    {
        try {
            return $this->translator->translateToDomainModel(
                $this->translateDataModelFromMapper(
                    $this->baseMapper->findById($id)
                )
            );
        } catch (MapperException\Deleted $e) {
            $e->setDeletedObject(
                $this->translator->translateToDomainModel(
                    $this->translateDataModelFromMapper($e->getDeletedObject())
                )
            );
            throw $e;
        }
    }

    /**
     * Finds all assessables for a unit specification
     *
     * @param int $unitSpecId
     */
    public function findAllForUnitSpecificationId($unitSpecId)
    {
        // TODO implement
    }

    /**
     * Finds all assessables for a unit implementation
     *
     * @param int $unitImplId
     */
    public function findAllForUnitImplementationId($unitImplId)
    {
        // TODO implement
    }

    /**
     * Finds all assessables for a folder
     *
     * @param int $folderId
     */
    public function findAllForFolderId($folderId)
    {
        // TODO implement
    }

    /**
     * Finds all assessables that are part of the given project
     *
     * @param int $projectId
     */
    public function findAllForProjectId($projectId)
    {
        // TODO implement
    }

    /**
     * Saves an assessable
     *
     * @param IAssessable $assessable
     */
    public function save(IAssessable $assessable)
    {
        // TODO implement
    }

    /**
     * Deletes an assessable
     *
     * @param IAssessable $assessable
     */
    public function delete(IAssessable $assessable)
    {
        // TODO implement
    }

    /**
     * Translates the data model from mapper format to a format usable by
     * this object's translator.
     *
     * TODO try to remove this, and update translator to return Model objects directly
     *
     * @param Model $model
     * @return IAssessableDataModel
     */
    protected function translateDataModelFromMapper(Model $model)
    {
        // Turn the data model inside out
        $subModel = $model->getSubObject();
        $subModel->setAssessmentModel($model);
        return $subModel;
    }

    /**
     * Translates the data model from the format this object's translator returns
     * to a format the mapper can use.
     *
     * @param IAssessableDataModel $subModel
     * @return Model
     */
    protected function translateDataModelToMapper(IAssessableDataModel $subModel)
    {
        // Turn the data model inside out
        $model = $subModel->getAssessmentModel();
        $model->setSubObject($subModel);
        return $model;
    }
}
