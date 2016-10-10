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
     * Returns a translation of a domain object to a data model object if it can, or null if it cannot.
     *
     * @param IAssessable $assessment
     * @return IAssessableDataModel|null
     */
    public function translateToDataModel(IAssessable $assessment);

    /**
     * Returns a translation of a data model object to a domain object if it can, or null if it cannot.
     *
     * @param IAssessableDataModel $model
     * @return IAssessable|null
     */
    public function translateToDomainModel(IAssessableDataModel $model);
}
