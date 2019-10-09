<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

namespace Schoolbox\Filter;

use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\User;

/**
 */
class CompositeCodec implements ICodec
{

    /**
     * @var ICodec[]
     */
    private $codecs;


    /**
     * Constructor
     *
     * @param ICodec ...$codecs
     */
    public function __construct(ICodec... $codecs)
    {
        $this->codecs = $codecs;
    }


    /**
     * Join together selections for all composite codecs.
     *
     * @inheritDoc
     */
    public function convertRequestToSelections(User $user, $page, array $request): GenericCollection
    {
        $selections = new GenericCollection(GenericCollection::class);
        foreach ($this->codecs as $codec) {
            $selections = $selections->union($codec->convertRequestToSelections($user, $page, $request));
        }
        return $selections;
    }


    /**
     * Join together query strings for all composite codecs.
     *
     * @inheritDoc
     */
    public function convertSelectionsToQueryString(Collection $selections): string
    {
        $queryStrings = [];
        foreach ($this->codecs as $codec) {
            $queryString = $codec->convertSelectionsToQueryString($selections);
            if ($queryString !== '') {
                $queryStrings[] = $queryString;
            }
        }
        return implode('&', $queryStrings);
    }
}
