<?php
/**
 * @copyright Copyright 2019, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */


namespace Schoolbox\Filter;


use Schoolbox\Collection;
use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Exception as MapperException;
use Schoolbox\User;
use UnexpectedValueException;

/**
 * Interface for filter codecs.
 */
interface ICodec
{
    /**
     * Convert a HTTP Request to a set of Selection Models, index by filter.id,
     * then selection.key.
     *
     * Able to return empty sets, to indicate all options should be unchecked.
     *
     * Only pageFilters that are enabled for this page will have their key
     * converters injected.
     *
     * @param User    $user
     * @param string  $page
     * @param int[][] $request Indexed by filter.id, then selection.key
     * @return GenericCollection|GenericCollection[]|Selection\Model[][] Indexed by filter.id then selection.key
     * @throws UnexpectedValueException If wrong item type inserted in a generic collection
     * @throws MapperException          If unable to create a mapper's model
     */
    public function convertRequestToSelections(User $user, $page, array $request): GenericCollection;


    /**
     * Converts a list of selections to a query string.
     *
     * @param Collection|Selection\Model[][] $selections Indexed by filter.id, then selection.key
     * @return string
     */
    public function convertSelectionsToQueryString(Collection $selections): string;
}
