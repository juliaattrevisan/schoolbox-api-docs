<?php

/**
 * @copyright   Copyright 2023, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

namespace Schoolbox\Learning\Learnosity\Importer\Strategy;

/**
 * Decide which converter to spawn
 */
class Factory
{

    /**
     * Use the question's type_id field to figure out what kind
     * of question this was in Schoolbox and then spawn the thing
     * which produces its Learnosity equivalent
     *
     * @param int $typeId
     * @return IStrategy|null
     */
    public function produce(int $typeId): ?IStrategy
    {
        switch ($typeId) {
            case Short::QUESTION_TYPE_ID: return new Short();
            case Exact::QUESTION_TYPE_ID: return new Exact();
            case Radio::QUESTION_TYPE_ID: return new Radio();
            case Fillgap::QUESTION_TYPE_ID: return new Fillgap();
            case Multi::QUESTION_TYPE_ID: return new Multi();

            default: return null;
        }
    }
}
