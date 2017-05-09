<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\L10n;

use Alaress\Rules\Token\Filter;
use Alaress\Rules\Token\Token;
use Alaress\Rules\Token\Tokenizer as BaseTokenizer;

/**
 * A tokenizer for l10nable strings.
 */
class Tokenizer extends BaseTokenizer
{
    /**
     * Constructor
     *
     * @param RosettaStone $stone
     */
    public function __construct(RosettaStone $stone)
    {
        foreach ($stone->getAllTerms() as $term) {
            $this
                ->addToken(
                    new Token(
                        $term,
                        '',
                        function() use ($term, $stone) {
                            return new Word($term, $stone);
                        }
                    )
                )
            ;
        }
        $this->addFilter(
            new Filter(
                'p',
                'Plural form',
                function ($value) {
                    if ($value instanceof Word) {
                        $value = $value->setPlural(true);
                    }
                    return $value;
                }
            )
        );
        $this->addFilter(
            new Filter(
                'c',
                'Capital form',
                function ($value) {
                    if ($value instanceof Word) {
                        $value = $value->setCapital(true);
                    }
                    return $value;
                }
            )
        );
    }
}
