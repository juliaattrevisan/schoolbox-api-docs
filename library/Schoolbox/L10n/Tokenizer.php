<?php
/**
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2017, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

namespace Schoolbox\L10n;

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
        // TODO implement these as filters ({:term|p|c)}
        foreach ($stone->getAllTerms() as $term) {
            $this
                ->addToken(
                    new Token(
                        $term,
                        '',
                        function() use ($term, $stone) {
                            return $stone->translate($term, false, false);
                        }
                    )
                )
                ->addToken(
                    new Token(
                        $term.'-p',
                        '',
                        function() use ($term, $stone) {
                            return $stone->translate($term, true, false);
                        }
                    )
                )
                ->addToken(
                    new Token(
                        $term.'-c',
                        '',
                        function() use ($term, $stone) {
                            return $stone->translate($term, false, true);
                        }
                    )
                )
                ->addToken(
                    new Token(
                        $term.'-pc',
                        '',
                        function() use ($term, $stone) {
                            return $stone->translate($term, true, true);
                        }
                    )
                );
        }
    }
}
