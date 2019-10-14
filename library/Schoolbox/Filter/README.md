# Filters - a quick developer's guide


## Purpose of this document

This document is intended to give developers a brief overview of the Filters
sub-system of Schoolbox, so that they may use filters in their own work.

> This documentation is a work in progress, and subject to change and expansion.
>

## Overview

In brief, filters are for letting the end user display a subset of the entire
record set that would otherwise display on a page. This has the benefits of:
* reducing page load time
* reducing memory usage
* reducing the amount of information displayed to only what is necessary for the
  end user's purpose


## Examples


## Concepts


## Interfaces

### Factory Factory (`\Schoolbox\Filter\IFactoryFactory`)

The Factory Factory interface defines a responsibility for building a Factory
for a particular Definition (`buildFilterFactoryForFilter`).

In addition, it defines responsibilities for:
* Building Codecs
* Building a set of Key Converters
* Converting selections into a 2D array of IDs, for later passing into an
  ID Finder
* Building the current state of Filters for a page
* Building a Panel so that a page's Filters may be rendered
* Checking whether any filters should be set to a default value based on the
  user whose page is being viewed (`checkTeacherFolders`)

> Note that the above additional responsibilities are arguably not part of what
> the Factory Factory should do, and developers will generally not have to worry
> about them anyway: they are mostly handled in the the core abstract
> implementation `\Schoolbox\Filter\AbstractFactoryFactory`.

It is expected there will only be one Factory Factory per type of entity that is
being filtered.


### Factory (`\Schoolbox\Filter\IFactory`)

The Factory interface defines responsibities for building the required objects
for a particular filter implementation, these being:
* Component
* Definition
* Key Converter


### Component (`\Schoolbox\Filter\IComponent`)

The Component interface defines the responsibilities required for rendering a
filter in its panel.

Components are rendered using Twig, and thus need to define:
* how to find the template for rendering them
* which variables need to be passed to the template

### Definition (`\Schoolbox\Filter\IDefinition`)

The Definition interface defines the responsibilities for a bunch of stuff:
* The pages on which the filter may live
* ...

> TODO figure out what else this defines, and whether it's too much

### Key Converter (`\Schoolbox\Filter\IKeyConverter`)

The Key Converter interface defines the responsibilities a filter requires for
converting a set of available options into a list of actual selections.

> TODO flesh this out once I understand more

### ID Finder (`\Schoolbox\Filter\IdFinder`)

The ID Finder interface defines the responsibilities required for converting a
list of selected filters into a list of IDs of items that match the filters.

This list of IDs can then be passed directly to a mapper's `findByColumn` method
for performant retrieval of matching items.

## Practical guides

### Enabling filters on a page

* Add a `\Schoolbox\Filter\Page\Model::PAGE_*` constant for the new page
* Alter the required database columns to recognize the new filters
  * the `filter_page.page` and `filter_selection.page` enums need to have an
    option added, which matches the value of the constant added above
* Add this constant to the `params` parameter for the `page` column mappings in
  `\Schoolbox\Filter\Page\Mapper::mapping` and
  `\Schoolbox\Filter\Selection\Mapper::mapping`
* Update these methods with cases for the new constant:
  * `\Schoolbox\Filter\Controller::buildFilteredPageLabels`
  * `\Schoolbox\Filter\Module::registerRoutes`
  * `\Schoolbox\Filter\Page\Model::getPageRoleTypes`
* Update the `getPages` of each Definition implementation that the page uses
* In your controller:
  * inject a Factory Factory as a constructor param
  * generate the filter panel's Twig data:
    ```php
    $page        = \Schoolbox\Filter\Page\Model::PAGE_NEW_FILTER;
    $filterPanel = $this->filterFactoryFactory->buildPanel(
        $this->getUser(),
        $page,
        null,
        $this->getUser(),
        []
    );
    ```
  * inject the Twig data into the controller action's Twig template
* In the Twig template:
  * ```twig
    {% include '@filters/panel.twig' with {
        contentId: reportContentId,
        panel:     filterPanel,
        quickload: true
    } only %}
    ```
* In the controller's module:
  * inject the Factory Factory as a constructor param:
    ```php
    protected function preInitControllerDependencies(ContainerInterface $injector): void
    {
        /** @var Container $injector */
        $injector->params[Controller::class] = [
            'filterFactoryFactory'  => $injector->lazy(function () use ($injector) {
                /** @var RosettaStone $rosettaStone */
                $rosettaStone = $injector->get('l10n');
                return new \Schoolbox\Filter\Filters\Assessment\FactoryFactory(
                    $this->getMapperFactory(),
                    $rosettaStone
                );
            }),
        ];
    }
    ```
* > TODO write about how to develop filters on the front end


### Creating a new filter implementation

* Determine the entity that the filter will actually operate on
  * As mentioned under Factory Factory, all existing filters operate on the
    Assessment object `\Schoolbox\Learning\Assessment\DataModel\Mapper`
* Create a Factory implementation for the new filter
  * Depending on how closely related the new filter is to existing ones, its
    `buildComponent` and `buildKeyConverter` might be able to create instances
    of already existing classes. `buildDefinition` will have to create a new
    class though
* Create a Definition implementation for the new filter
  * The `getPages` method will determine the filter pages on which this filter
    will show: see "Adding an existing filter" below
  * > TODO document the process of creating new classes
* Insert a new row into the `filter` database table for the new filter
  * the `entity` column value needs to be the fully-qualified class name of the
    entity the filter will operate on
  * the `class` column is the fully-qualified class name of the new filter's
    Definition class
  * leave the leading `\` character off both values
    * note that `\` is escaped by another `\` in MySQL/MariaDB queries
* Find the Factory Factory implementation which creates filters for the entity
  that is being filtered
  * Each Factory Factory has an `ENTITY` class constant: find the one that
    matches the entity's class name, or create a new Factory Factory if none
    exists
  * Update that implementation's `buildFilterFactoryForFilter` method to be able
    to create an instance of the new filter
* Add a case to the ID finder for this class


### Adding an existing filter to a page which already supports filters

* Update the Definition implementation's `getPages` to return the page's
  `\Schoolbox\Filter\Page\Model::PAGE_*` constant

## Technical discussion

> TODO isn't technical discussion covered by everything above?
