<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2016, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\Learning\Assessment\DataModel\Translator;

use Schoolbox\Learning\Assessment\DataModel\IAssessableDataModel;
use Schoolbox\Learning\Assessment\IAssessable;

/**
 * Interface for all classes which may translate between IAssessable and IAssessableDataModel objects.
 */
interface IAssessmentTypeTranslator
{

    /**
     * @return IAssessableDataModel
     */
    public function createDataModel(): IAssessableDataModel;


    /**
     * @return IAssessable
     */
    public function createDomainObject(): IAssessable;


    /**
     * Does this sub-translator recognise this assessable?
     *
     * @param IAssessable $domainObject
     * @return bool
     */
    public function recognisesDomainObject(IAssessable $domainObject): bool;


    /**
     * Does this sub-translator recognise this assessment?
     *
     * @param IAssessableDataModel $dataModel
     * @return bool
     */
    public function recognisesDataModel(IAssessableDataModel $dataModel): bool;


    /**
     * Copies all attributes from the domainObject to dataModel
     *
     * @param IAssessable          $domainObject
     * @param IAssessableDataModel $dataModel
     * @param int                  $depth
     * @param bool                 $translateProjectContext
     */
    public function translateToDataModel(
        IAssessable $domainObject,
        IAssessableDataModel $dataModel,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void;


    /**
     * Copies all attributes from the dataModel to domainObject
     *
     * @param IAssessableDataModel $dataModel
     * @param IAssessable          $domainObject ,
     * @param int                  $depth
     * @param bool                 $translateProjectContext
     */
    public function translateToDomainModel(
        IAssessableDataModel $dataModel,
        IAssessable $domainObject,
        int $depth = 0,
        bool $translateProjectContext = true
    ): void;
}
