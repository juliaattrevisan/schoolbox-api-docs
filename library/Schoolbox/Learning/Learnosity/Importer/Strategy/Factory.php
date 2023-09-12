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
        return match ($typeId) {
            Short::QUESTION_TYPE_ID => new Short(),
            Exact::QUESTION_TYPE_ID => new Exact(),
            Radio::QUESTION_TYPE_ID => new Radio(),
            Fillgap::QUESTION_TYPE_ID => new Fillgap(),
            Multi::QUESTION_TYPE_ID => new Multi(),
            MatchingPairs::QUESTION_TYPE_ID => new MatchingPairs(),
            Numeric::QUESTION_TYPE_ID => new Numeric(),
            RichText::QUESTION_TYPE_ID => new RichText(),
            default => null,
        };
    }
}
