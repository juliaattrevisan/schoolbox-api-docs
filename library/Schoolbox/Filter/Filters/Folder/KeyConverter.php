<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Collection;
use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;

/**
 * Converts between folder concrete keys (folder.ids) and Folder models
 * @method GenericCollection|Folder\Model[] getSelectedItems(GenericCollection $availableSelections = null)
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All Folders IDs known to the system (regardless of whether they are available)
     * (using IDs instead of Models as too slow for no value)
     *
     * @var Collection|int[]
     */
    private $folderIds;


    /**
     * @param Filter\Model      $filter
     * @param Collection $folderIds
     */
    public function __construct(Filter\Model $filter, Collection $folderIds)
    {
        parent::__construct($filter);
        $this->folderIds = $folderIds->shallowClone();
    }


    /**
     * Given a concrete key (such as returned from a user's filter panel selections), return the corresponding item
     *
     * @param string $key
     * @return mixed|Folder\Model
     * @throws \InvalidArgumentException
     */
    public function getItem($key)
    {
        $this->assertValidKey($key);
        return $this->folderIds[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return Collection|int[]
     */
    public function getAllPossibleItems()
    {
        return $this->folderIds->shallowClone();
    }


    /**
     * @inheritdoc
     */
    public function sanitiseKey($key)
    {
        $this->assertValidKey($key);
        return (string)(int)$key;
    }


    /**
     * @inheritdoc
     */
    protected function assertValidKey($key)
    {
        if (!$this->folderIds->offsetExists($key)) {
            throw new \InvalidArgumentException("No Folder found for key: $key");
        }
    }
}
