# Filters - a quick developer's guide

## tl;dr

In short, filters are for letting the end user display a subset of the entire
record set that would otherwise display on a page. This has the benefits of:
* reducing page load time
* reducing memory usage
* reducing the amount of information displayed to only what is necessary for the
  end user's purpose

At the time of writing, they are being used only for filtering assessments:
however, there is no reason why a filter that filters data other than
assessments could not be implemented.

## Examples

## Concepts

## Interfaces

### Factory Factory (`\Schoolbox\Filter\IFactoryFactory`)

> Yes, this is a real thing!

The Factory Factory is an interface which defines responsibilities for building
a Factory for a filter implementation, as well as the rendering of the filter
panel.

> TODO filter panel and factory building sound like dual responsibilities

It is expected there will only be one Factory Factory per type of object that is
being filtered. (Currently, only assessment filters exist.)

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

### Adding an existing filter to a page which has filters

### Adding a filter page

### Creating a new filter

* Add a Factory class for it
* Add a build case for that class name to the appropriate Factory Factory
  implementation

## Technical discussion

