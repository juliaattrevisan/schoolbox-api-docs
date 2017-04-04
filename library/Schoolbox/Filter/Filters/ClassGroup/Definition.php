<?php
/**
 * @author      Andy Ryan <andy.ryan@alaress.com.au>
 * @copyright   Copyright 2017, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

namespace Schoolbox\Filter\Filters\ClassGroup;

use Schoolbox\Collection;
use Schoolbox\Filter\AbstractFilterDefinition;
use Schoolbox\Filter\Option\Model as FilterOption;
use Schoolbox\GenericCollection;
use Schoolbox\User;
use Schoolbox\Resource\Folder\Model as Folder;

class Definition extends AbstractFilterDefinition
{

    /**
     * @var GenericCollection|Folder[]
     */
    private $classes;

    /**
     * @var GenericCollection|Folder[]
     */
    private $groups;


    /**
     * @param GenericCollection|Folder[] $classes
     * @return static
     */
    public function setClasses(GenericCollection $classes)
    {
        $this->classes = $classes;
        return $this;
    }

    /**
     * @param GenericCollection|Folder[] $groups
     * @return static
     */
    public function setGroups(GenericCollection $groups)
    {
        $this->groups = $groups;
        return $this;
    }

    public function getTemplateFile()
    {
        return 'classGroup.twig';
    }


    /**
     * Returns the twig template to render this filters options
     *
     * Intended to be called in Controller
     *
     * @return string
     */
    public function getOptionsTemplate()
    {
        // TODO: Implement getOptionsTemplate() method.
    }


    public function getDefaultsTemplate()
    {
        // TODO: Implement getRoleDefaultsTemplate() method.
    }


    /**
     * Returns the twig template to render into the filter panel
     *
     * Intended to be called in twig includes
     *
     * @return string
     */
    public function getPanelTemplate()
    {
        // TODO: Implement getPanelTemplate() method.
    }


    /**
     * Returns a set of data to inject into this filter's template
     *
     * Intended to be called in twig includes
     *
     * @param string $page
     * @param User   $user
     * @return mixed[]
     */
    public function toContext($page, User $user)
    {
        // TODO: Implement toContext() method.
    }


    /**
     * @inheritdoc
     */
    public function getOptions()
    {
        // TODO consider all/none ?
        return new Collection();
    }


    /**
     * @inheritdoc
     */
    public function getAvailableOptions()
    {
        // TODO consider all/none ?
        return new Collection();
    }


    /**
     * Return the set of pages where this filter is available
     *
     * @see FilterOption::PAGE_* constants
     * @return string[]
     */
    public function getPages()
    {
        // TODO: Implement getPages() method.
        return [];
    }




    /**
     * @inheritdoc
     */
    public function getAbstractKeys()
    {
        // TODO: Implement getAbstractKeys() method.
        return [];
    }


    /**
     * Returns a set of data to inject into this filter's template
     *
     * Intended to be called in twig includes
     *
     * @param string $page
     * @param User   $user
     * @return mixed[]
     */
    public function toPanelContext($page, User $user)
    {
        // TODO: Implement toPanelContext() method.
    }
}
