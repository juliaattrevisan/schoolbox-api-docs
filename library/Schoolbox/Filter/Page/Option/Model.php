<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

namespace Schoolbox\Filter\Page\Option;

use Schoolbox\GenericCollection;
use Schoolbox\Mapper\Traits\Model\Id;
use Schoolbox\Filter;

/**
 * FilterOptions can represent different things.
 *
 * They may represent a specific term, such as '2017-2' or a abstract term, eg 'current'.
 *
 * It is up to the FilterDefinition to interpret the meaning of the option's abstractKey. (ie it may be a model ID, or some abstract concept)
 */
class Model
{

    use Id;

    /**
     * @var Filter\Page\Model
     */
    private $filterPage;


    /**
     * An abstract key. Eg 'all', 'future', '1', '3', etc which may represent 1 or more concrete keys.
     *
     * It is up to the concrete filter definitions to apply semantics to this value.
     *
     * @var string
     */
    private $key;

    /**
     * Should this option be selectable in the filter?
     *
     * @var bool
     */
    private $available;

    /**
     * The roleType-specific defaults configured by the admin for this option
     *
     * @var null|GenericCollection|Defaults\Model[]
     */
    private $defaults;


    /**
     * @return Filter\Page\Model
     */
    public function getFilterPage()
    {
        return $this->filterPage;
    }


    /**
     * @param Filter\Page\Model $filterPage
     * @return $this
     */
    public function setFilterPage(Filter\Page\Model $filterPage)
    {
        $this->filterPage = $filterPage;
        return $this;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }


    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }


    /**
     * @return bool
     */
    public function getAvailable()
    {
        return $this->available;
    }


    /**
     * @param bool $available
     * @return $this
     */
    public function setAvailable($available)
    {
        $this->available = $available;
        return $this;
    }


    /**
     * @return GenericCollection|Defaults\Model[]
     */
    public function getDefaults(): ?GenericCollection
    {
        return $this->defaults;
    }


    /**
     * @param GenericCollection|Defaults\Model[] $defaults
     * @return $this
     */
    public function setDefaults(GenericCollection $defaults): self
    {
        $defaults->assertCollectionOf(Defaults\Model::class);
        $this->defaults = $defaults;
        return $this;
    }

}
