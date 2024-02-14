<?php

/**
 * @copyright Copyright 2024, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Cli;

use UnexpectedValueException;

/**
 * Exception class used by the restart pending Learnosity import tool;
 * thrown when the destination quiz into which Learnosity is importing quiz
 * questions into:
 * * has a `learnosity_source_assessment_id`, which references an assessment
 *   which does not exist
 * * has no `learnosity_source_assessment_id`, and its source assessment cannot
 *   be inferred, due to the quiz being one of:
 *    * an implementation assessment with no spec assessments
 *    * a spec assessment with no implementations
 *    * a spec assessment with more than one implementation
 * * is determined to be related to an assessment which is not a quiz
 */
class SourceAssociationException extends UnexpectedValueException
{
}
