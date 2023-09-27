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
     * Constructor.
     *
     * $domain is used to replace relative urls with absolute urls
     *
     * @param string $domain
     */
    public function __construct(private string $domain)
    {
    }


    /**
     * Use the question's type_id field to figure out what kind
     * of question this was in Schoolbox and then spawn the thing
     * which produces its Learnosity equivalent
     *
     * @param int $typeId
     * @return IStrategy|null
     */
    public function produce(int $typeId): ?IStrategy
    {;
        return match ($typeId) {
            Short::QUESTION_TYPE_ID => new Short($this->domain),
            Exact::QUESTION_TYPE_ID => new Exact($this->domain),
            Radio::QUESTION_TYPE_ID => new Radio($this->domain),
            Fillgap::QUESTION_TYPE_ID => new Fillgap($this->domain),
            Multi::QUESTION_TYPE_ID => new Multi($this->domain),
            MatchingPairs::QUESTION_TYPE_ID => new MatchingPairs($this->domain),
            Numeric::QUESTION_TYPE_ID => new Numeric($this->domain),
            RichText::QUESTION_TYPE_ID => new RichText($this->domain),
            default => null,
        };
    }
}
