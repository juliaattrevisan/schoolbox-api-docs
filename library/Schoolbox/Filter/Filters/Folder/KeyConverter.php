<?php
/**
 * Author: andrewryantech@gmail.com
 * Created: 2/04/17 3:15 PM
 */

namespace Schoolbox\Filter\Filters\Folder;

use Schoolbox\Filter;
use Schoolbox\Filter\AbstractKeyConverter;
use Schoolbox\GenericCollection;
use Schoolbox\Resource\Folder;

/**
 * Converts between folder concrete keys (folder.ids) and Folder models
 * @method GenericCollection|Folder\Model[] getSelectedItems(GenericCollection $availableSelections)
 */
class KeyConverter extends AbstractKeyConverter
{

    /**
     * All Folders known to the system (regardless of whether they are available)
     *
     * @var GenericCollection|Folder\Model[]
     */
    private $folders;


    /**
     * @param Filter\Model      $filter
     * @param GenericCollection $folders
     */
    public function __construct(Filter\Model $filter, GenericCollection $folders)
    {
        parent::__construct($filter);
        $this->folders = $folders->shallowClone();
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

        return $this->folders[$key];
    }


    /**
     * Return all available concrete items
     *
     * @return GenericCollection|Folder\Model[]
     */
    public function getAllPossibleItems()
    {
        return $this->folders->shallowClone();
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
        if (!$this->folders->offsetExists($key)) {
            throw new \InvalidArgumentException("No Folder found for key: $key");
        }
    }
}
