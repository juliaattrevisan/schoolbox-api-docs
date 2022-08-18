# Filters - a quick developer's guide


## Purpose of this document

This document is intended to give developers a brief overview of the Filters
sub-system of Schoolbox, so that they may use filters in their own work.

> This documentation is a work in progress, and subject to change and expansion.


## Overview

Filters provide a user and developer interface for displaying a subset of the
entire set of [Entities](#entity) that would otherwise display on a
[Page](#page).

This has the benefits of:
* reducing page load time
* reducing memory usage
* reducing the information displayed to only what is necessary for the end
  user's needs


## Concepts

### Filter

A single option for reducing the set of [Entities](#entity) displayed on a
[Page](#page) to a subset.

A Filter may have one or more of its options selected: generally, an
[Entity](#entity) must match at least one of the selected options in order to
pass the Filter.

### Filter Set

The collection of [Filters](#filter) which are included on a single
[Page](#page).

Each [Entity](#entity) that would be displayed on the [Page](#page) must pass
all [Filters](#filter) in the Filter Set in order to be displayed.

### Entity

A single object within Schoolbox, i.e. a single assessment or event.

### Page

A single location within Schoolbox, i.e. a report.


## Interfaces

### Factory Factory

The Factory Factory interface `\Schoolbox\Filter\IFactoryFactory` defines a
responsibility for building a Factory for a particular
[Definition](#definition) via its `buildFilterFactoryForFilter` method.

In addition, it defines responsibilities for:
* Building a set of [Key Converters](#key-converter)
* Converting selections into a 2D array of IDs, for later passing into an
  [ID Finder](#id-finder)
* Building the current state of Filters for a [Page](#page)
* Building a [Panel](#panel) so that a page's [Filter Set](#filter-set) may be
  rendered
* Checking whether any [Filters](#filter) should be set to a default value based
  on the user whose [Page](#page) is being viewed (`checkTeacherFolders`)

> Note that the above additional responsibilities are arguably not part of what
> the Factory Factory should do, and developers will generally not have to worry
> about them anyway: they are mostly handled in the core abstract implementation
> `\Schoolbox\Filter\AbstractFactoryFactory`.

It is expected there will be at most one Factory Factory per [Entity](#entity):
for the standard `AbstractFactoryFactory` implementation, the Entity's Mapper
class is in the `ENTITY` constant.

Developers will generally only need to know about `buildFilterFactoryForFilter`
and its implementations: they will need to add a case to an implementation of
this method in order to create a new filter implementation.

The Factory Factory is expected to be a singleton: it exists in the dependency
injection container, with the key `filters.factoryFactory`.

> The Factory Factory in the DI container is a composite of several predefined
> Factory Factory instances; one for each Entity the Filter system is
> capable of filtering.


### Factory

The Factory interface `\Schoolbox\Filter\IFactory` defines responsibilities for
building the required objects for a particular [Filter](#filter) implementation,
these being:
* [Component](#component)
* [Definition](#definition)
* [Key Converter](#key-converter)

It is created by the [Factory Factory](#factory-factory)'s
`buildFilterFactoryForFilter` method.


### Component

The Component interface `\Schoolbox\Filter\IComponent` defines the
responsibilities required for rendering a [Filter](#filter) within its
[Filter Set](#filter-set).

Components are rendered using Twig, and thus need to define which variables are
passed to the template, as defined in the [Filter](#filter)'s
[Definition](#definition).


### Definition

The Definition interface `\Schoolbox\Filter\IDefinition` defines the
responsibilities for:
* The [Pages](#page) whose [Filter Set](#filter-set) contains the
  [Filter](#filter) (`getPages`)
* Whether a [Filter](#filter) is necessary on a particular [Pages](#page)
  (`isMandatory`)
* Other methods, used mainly by the filter administration page

In practise, developers don't need to define most of this interface's methods,
as most are implemented in the core abstract implementation
`\Schoolbox\Filter\AbstractDefinition`. However, this class itself defines a set
of methods which may be overridden, the most important being:
* its name in the admin section (`NAME` constant)
* addition of options from a database table (`insertDynamicOptions`)
  * maybe based on the page that the filter is on?
* removal of options that cannot be selected (`unsetInvalidOptions`)
* setting of whether an option should be selected by default
  (`getInitialSelectedState`)
* addition of options known at time of writing (`getCustomKeys`)
* addition of options from a database table (`getAbstractKeys`)
* sorting of the filter's options (`sortFilterOptions`)
* where the filter's template lives (`getTemplatesDir`)
  * in practise, these will always be in either the filter's `templates` folder,
    or a `templates` folder of a superclass. It will always be defined as part
    of the `@filterDefinitions` namespace, where `@filterDefinitions` maps to
    `~/library/Schoolbox/Filter/Filters`

> TODO figure out the difference between:
> * `getAbstractKeys`
> * `getCustomKeys`
> * `insertDynamicOptions`


### Key Converter

The Key Converter interface `\Schoolbox\Filter\IKeyConverter` defines the
responsibilities a filter requires for converting a set of available options
into a list of actual selections.

As with the Definition, it has a core abstract implementation
(`\Schoolbox\Filter\AbstractKeyConverter`) which covers most of the interface,
and adds further interface requirements:
* Convert selections to a one-dimensional list of item keys (`getSelectedItems`)
* Get a list of all possible item keys (`getAllPossibleItems`)
* Get the item referenced by a single key (`getItem`)
* Assert whether a provided key is valid (`assertValidKey`)


### Panel

The Panel class `\Schoolbox\Filter\Panel` contains a set of Filter Components
for rendering onto a page, as well as the route to which any AJAX requests to
update the filtered contents will be sent.

In practise: it is built using the Factory Factory's `buildPanel` method, and
passed directly to a Twig template for rendering in a specific sub-template that
the template includes.

> You can see more on how to pass the Panel object around in the
> "Enabling filters on a page" practical guide.


### Filter Consumers

Filter Consumers are able to take a list of selected filters, and do something
with them.

Currently, there are two types of Filter Consumer:
* the ID Finder converts the filter into a list of IDs for passing into a query
* the Matcher acts as a filter, which is applied after

Both types of Filter Consumer must be able to examine a list of filters passed
to them. This implies that they have some knowledge of filter implementations:
arguably this knowledge could be moved to within individual implementations.


#### ID Finder

The ID Finder interface `\Schoolbox\Filter\IdFinder` defines the
responsibilities required for converting a list of selected filters into a list
of IDs of items that match the filters.

An ID Finder operates _before_ results are retrieved: it's intended to add a
filter (in the form of a list of IDs) to a search query, so that the query
returns a smaller data set and runs more quickly.
(In practise, the list of IDs is passed to a mapper's `findByColumn` method, so
that the set of models for these IDs is retrieved.)

ID Finder implementations must implement:
* a method for fetching a static instance, as ID Finders are intended to be
  singleton objects (`getInstance`)
* a method for translating a list of selected filters to a list of IDs
  (`findIdsByFilters`)

> Only one of these exists: it exists for assessment filters. (It exists in the
> DI container, with the key `filters.assessmentIdFinder`.)
>
> The Matcher paradigm was not viable for assessment filtering for performance
> reasons: constructing fetched assessments into usable objects is an expensive
> operation, reducing the counts fetched in the first place is important.


#### Matcher

A Matcher operates on a collection of entities _after_ all results have been
retrieved by some other process.

It is designed to be usable as the callback for `array_filter` or
`\Schoolbox\Collection::filter`. Therefore, it must implement one method which
is usable as a filter callback:
* accepts a filterable entity as its only parameter
* return `true` if the entity is to be preserved in the filtered record set

(The Matcher object itself may be used as a filter, if the magic method
`__invoke` is implemented as the filter method.)

> Only one of these exists: it exists for the event filter.
>
> The ID Finder paradigm was not viable for filtering events, because not all
> events are from Schoolbox: this means they don't all have IDs, and we are not
> able to pass a list of IDs to external sources even if they have IDs.


## Practical guides

These guides are intended for getting a developer to understand the fundamentals
of developing for filters.


### Enabling Filters on a Page

* Add a `\Schoolbox\Filter\Page\Model::PAGE_*` constant for the [Page](#page)
* Add the value of that constant to the `filter_page.page` and
  `filter_selection.page` enums in the Schoolbox database
  ```sql
  ALTER TABLE filter_page
  MODIFY page ENUM(/* all existing values */, 'newPage') NULL;

  ALTER TABLE filter_selection
  MODIFY page ENUM(/* all existing values */, 'newPage') NULL;
  ```
* Add this constant to the `params` parameter for the `page` column mappings in
  `\Schoolbox\Filter\Page\Mapper::mapping` and
  `\Schoolbox\Filter\Selection\Mapper::mapping`
  * The mapping will look something like:
    ```php
    $this->uses(
        'page',
        self::TYPE_STRING,
        self::COLUMN_ENUM,
        'page',
        [
            Model::PAGE_PENDING_ASSESSMENTS,
            Model::PAGE_GRADES,
            Model::PAGE_CLASS_MARK_BOOK,
            Model::PAGE_COURSE_MARK_BOOK,
            Model::PAGE_RUBRIC_MARK_BOOK,
            Model::PAGE_LEARNING_ANALYTICS,
            Model::PAGE_UPCOMING_ASSESSMENTS,
            Model::PAGE_ASSESSMENT_CALENDAR,
            // Add the constant to the end of this array
        ]
    );
    ```
* Update these methods with cases for the new constant:
  * `\Schoolbox\Filter\Controller::buildFilteredPageLabels`
  * `\Schoolbox\Filter\Module::registerRoutes`
  * `\Schoolbox\Filter\Page\AvailabilityChecker::isAvailable`
  * `\Schoolbox\Filter\Page\LabelBuilder::buildLabel`
  * `\Schoolbox\Filter\Page\Model::getPageRoleTypes`
* Update `\Schoolbox\Admin\Navigation\Subnav::generate` so that there's a menu
  option for the new filter:
  ```php
  return [
      ...
      '/adminv2/filters' => [
          ...
          '/adminv2/filters/classPastoral'  => [
              'name'        => $filterLabelBuilder->buildLabel(newConstant),
              'visible'     => $filterAvailabilityChecker->isAvailable(newConstant),
              'children'    => [],
              'description' => 'Configure the default and available filter options on the new page.',
          ],
          ...
      ],
      ...
  ```
* Update the `getPages` of each [Definition](#definition) implementation that
  you intend the [Page](#page) to use
* In your page's module:
  * inject the [Factory Factory](#factory-factory) as a controller parameter:
    ```php
    protected function buildController(): \Alaress\Web\Controller\Base
    {
        return new Controller($this->injector->get('filters.factoryFactory'));
    }
    ```
* In your controller:
  * inject the [Factory Factory](#factory-factory) as a constructor parameter:
    ```php
    public function __construct(private \Schoolbox\Filter\IFactoryFactory $filterFactoryFactory)
    {
    }
    ```
  * generate the filter panel's Twig data in a controller action:
    ```php
    $page        = \Schoolbox\Filter\Page\Model::PAGE_NEW_PAGE;
    $filterPanel = $this->filterFactoryFactory->buildPanel(
        $this->getUser(),
        $page,
        // The alias of the Aura route which loads the filtered content, if any.
        // If null, then the element's `filterContentLoaded` event is expected
        // to handle rendering of the filtered content.
        null,
        $this->getUser(),
        // If an Aura route is provided above: pass these parameters into it.
        [],
        // The query string params for this request:
        // this contains the actually selected filters.
        $this->request->query->getArrayCopy(),
    );
    ```
  * inject the Twig data into the controller action's Twig template
    ```php
    $this->response->content->set(
        $this->buildHtmlOutput(
            '@moduleName/index.twig',
            [
                'filterPanel' => $filterPanel,
            ]
        )
    );
    ```
* In the Twig template:
  * Output the filter
    ```twig
    {% include '@filters/panel.twig' with {
        contentId: 'filterPanel',
        panel:     filterPanel,
        // set to true if this is expected to be a large data set
        // When true, any JavaScript must be applied on the
        // "filterContentLoaded" event of the contentId element.
        quickload: false,
    } only %}
    ```
* > TODO document how to render the filtered content


### Implementing Filtering on an Entity

* Create a [Factory Factory](#factory-factory) in the namespace
  `\Schoolbox\Filter\Filters\{entityName}`, extending
  `\Schoolbox\Filter\AbstractFactoryFactory`
  * define the `ENTITY` constant as the class name of the mapper to filter
  * implement `buildFilterFactoryForFilter`: just have it throw an
    `\InvalidArgumentException` for now
  * implement `checkTeacherFolders`: just have it return the first parameter
  * add it to the `\Schoolbox\Filter\CompositeFactoryFactory` in the DI key
    `filters.factoryFactory` in
    `\Alaress\Application\Kernel::installDependencies`
* create a [Filter Consumer](#filter-consumers) in the namespace
  `\Schoolbox\Filter\Filters\{entityName}`
  * an [ID Finder](#id-finder) will be appropriate if the [Entity](#entity) is
    contained within the Schoolbox database; otherwise a [Matcher](#matcher)
    will be required
    * if creating an [ID Finder](#id-finder):
      * extend `\Schoolbox\Filter\AbstractIdFinder`
      * implement `doFiltersGuaranteeEmptySet`: return `false` for now
      * implement `simplifyFilters`: return the first parameter for now
      * implement `buildQuery`: return an empty array for now
    * if creating a [Matcher](#matcher):
      * implement `__invoke`, with an [Entity](#entity) class as its parameter:
        return true for now


### Implementing a new Filter

* Determine the Entity that the Filter will operate on
  * if no Factory Factory filters the Entity: follow the instructions to
    [implement Filtering on an Entity](#implementing-filtering-on-an-entity),
    and then return here

* Determine a namespace for the new Filter's classes
  * The Filter's namespace will be
    `\Schoolbox\Filter\Filters\{entityName}\{filterName}`

* Create a [Definition](#definition) in the namespace determined above,
  extending `\Schoolbox\Filter\AbstractDefinition`
  * override the `NAME` constant to be a brief description of the Filter
  * implement `getCustomKeys`: return an empty array for now
  * implement `getPages`: return an empty array for now
  * implement `getLabel`: return an empty string for now
  * implement `getTemplatesDir` with:
    ```php
    return '@filterDefinitions/{entityName}/{filterName}/templates';
    ```

* Create a `templates` folder in the folder for the namespace determined above
  * add a `component.twig` file:
  ```twig
  {% extends '@filters/_component.twig' %}

  {% block filterName %}A human-readable name for the filter{% endblock %}
  ```

* Create a [Key Converter](#key-converter) in the namespace determined above,
  extending `\Schoolbox\Filter\AbstractKeyConverter`
  * implement `getItem`: have it `return (int)$key` for now
  * implement `assertValidKey`: have it do nothing for now
  * implement `possibleItemCount`: have it `return 0` for now

* Create a [Factory](#factory) in the namespace determined above, extending
  `\Schoolbox\Filter\AbstractFactory`
  * implement `buildDefinition`:
    ```php
    public function buildDefinition(\Schoolbox\Filter\Model $filter): \Schoolbox\Filter\IDefinition
    {
        return new Definition(
            $filter,
            $this->getOptionMapper(),
            $this->getDefaultsMapper()
        );
    }
    ```
  * implement `buildKeyConverter`:
    ```php
    public function buildKeyConverter(\Schoolbox\Filter\Model $filter): \Schoolbox\Filter\IKeyConverter
    {
        return new KeyConverter($filter);
    }
    ```

* Consider the options which the Filter will display:
  * A common example is that the Filter displays a set of options corresponding
    to a list of entities which are related to the Entity being filtered.
    In this case:
    * in the [Definition](#definition):
      * override the constructor to accept an additional
        `\Schoolbox\GenericCollection` of related entities, and set that
        collection as a private instance variable
      * override `getAbstractKeys` to convert the collection into an array of
        IDs, converted to string values
      * override `getLabel` to lookup the string ID in the collection, and
        return a human-readable label for the corresponding object
      * override `insertDynamicOptions` with an implementation which adds an
        option for every item in the collection:
        ```php
        protected function insertDynamicOptions(\Schoolbox\Filter\Page\Model $pageFilter): \Schoolbox\GenericCollection
        {
            $options = parent::insertDynamicOptions($pageFilter);

            // Ensure it contains all items in the collection
            foreach ($this->collection as $item) {
                $abstractKey = (string)$item->getId();
                if (!$options->offsetExists($abstractKey)) {
                    $option = new \Schoolbox\Filter\Page\Option\Model();
                    $option
                        ->setFilterPage($pageFilter)
                        ->setKey($abstractKey)
                        ->setAvailable($this->getInitialAvailability($abstractKey, $pageFilter))
                        ->setDefaults(
                            new \Schoolbox\GenericCollection(\Schoolbox\Filter\Page\Option\Defaults\Model::class)
                        );
                    $options->setOffset($option->getKey(), $option);
                }
            }
            return $options;
        }
        ```
    * in the [Key Converter](#key-converter):
      * override the constructor to accept an additional array of IDs, and set
        that array as a private instance variable
      * update `possibleItemCount` to return the number if items in the array
      * update `assertValidKey` to verify that `$key` is in the array
    * in the [Factory](#factory):
      * override the constructor to accept the mapper for the related entity
      * update `buildDefinition` to pass a collection of entities
        (likely fetched with `findAll`)
      * update `buildKeyConverter` to pass a collection of IDs
        (likely fetched with `findAllIds`)
  * Another common example is that the Filter will contain meta-options: i.e.
    "None" for matching Entities which match exactly none of a list of entities.
    In this case:
    * in the [Definition](#definition):
      * define a class constant for the key for each meta-option
      * update `getCustomKeys` to return the keys for the meta-options
      * update `getLabel` to return a specific label for each meta-option
    * if there is an [ID Finder](#id-finder) implementation:
      * update `buildQuery` to recognise the meta-options in the filter, and
        build a query appropriately
    * if there is a [Matcher](#matcher) implementation instead:
      * update `__invoke` to recognise the meta-options in the filter, and
        filter appropriately

* Create a [Component](#component) implementation
  (if a custom implementation is required)
  > TODO fill in more about whether a custom component is required

* Find the [Factory Factory](#factory-factory) implementation for the
  [Entity](#entity) being filtered
  * Each Factory Factory has an `ENTITY` class constant: find the one that
    matches the entity's mapper's class name
  * Update that implementation's `buildFilterFactoryForFilter` method, so that
    it may create an instance of the new [Filter](#filter)'s [Factory](#factory)

* Add a case to the [Filter Consumer](#filter-consumers) for this entity
  * [ID Finders](#id-finder) will need their `buildQuery` method updated, so
    long as it extends `\Schoolbox\Filter\AbstractIdFinder`
    ```php
    protected function buildQuery(array $completeFilters, bool $deleted): array
    {
        $ids = $completeFilters[NewDefinition::class] ?? null;

        // Create a query based upon the IDs in this filter.
        // That query may be joined with queries for other filters in a UNION
        $pb          = $this->getMapper()->getPredicateBuilder();
        $hasWhere    = false;
        if (isset($ids) && count($ids) > 0) {
            $hasWhere = true;
            $pb = $pb->in('related_id', $ids);
        }

        $query = 'SELECT id FROM table';
        if ($hasWhere) {
            $query .= ' WHERE ' . $pb->getWhereIntersect();
        }
        $params = $pb->getParams();
        return [$query, $params];
    }
    ```
  * [Matchers](#matchers) are more freeform, but somehow their filter method
    must recognise the new [Filter](#filter), and accept/reject the
    [Entity](#entity)

* Insert a new row into the `filter` database table for the new
  [Filter](#filter) class
  * the `entity` column value needs to be the fully-qualified class name of the
    [Entity](#entity) the [Filter](#filter) will operate on
  * the `class` column is the fully-qualified class name of the new
    [Filter](#filter)'s [Definition](#definition) class
  * leave the leading `\` character off both values
    * note that `\` is escaped by another `\` in MySQL/MariaDB queries:
    ```sql
    INSERT INTO filter (entity, class)
    VALUES (
        'Schoolbox\\Learning\\Assessment\\DataModel\\Mapper',
        'Schoolbox\\Filter\\Filters\\Assessment\\NullableContext\\CourseImportSpec\\Definition'
    );
    ```

* Now you will want to add this filter to a page: to accomplish that, follow the
  instructions in the
  [Adding an existing Filter to a Page's Filter Set](#adding-an-existing-filter-to-a-pages-filter-set)
  section below

### Adding an existing Filter to a Page's Filter Set

* Update the [Filter](#filter)'s [Definition](#definition) implementation's
  `getPages` method to return the `\Schoolbox\Filter\Page\Model::PAGE_*`
  constant


### Frontend development for new filters

The Filter's Twig template is a `templates/component.twig` in the same folder as
its Definition class.

This template must extend `library/Schoolbox/Filter/templates/_component.twig`
(alias: `@filters/_component.twig`).
* The `filterName` block will always be extended, and will contain a label for
the component
* the `userOptions` block may be extended if you wish to render the list of
  available options differently
  * most implementations use the default, which uses
    `library/Schoolbox/Filter/templates/_selectionCheckbox.twig` (alias
    `@filters/_selectionCheckbox.twig`) to render a set of checkboxes

The base Panel's Twig template (including JavaScript) may be found in
`library/Schoolbox/Filter/templates/panel.twig`.
