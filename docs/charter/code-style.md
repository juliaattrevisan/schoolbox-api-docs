# PHP Style Guide

## General

We utilise the [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style guide and that should be used as the initial starting point for all coding style. This style should automatically be applied through PhpStorm using the inbuilt [project setting file](../../.editorconfig). Any changes to the style should be implemented through that file.

## Example

```php
<?php

declare(strict_types=1);

namespace Vendor\Package;

use Vendor\Package\{ClassA as A, ClassB, ClassC as C};
use Vendor\Package\SomeNamespace\ClassD as D;

use function Vendor\Package\{functionA, functionB, functionC};

use const Vendor\Package\{ConstantA, ConstantB, ConstantC};

class Foo extends Bar implements FooInterface
{
    public function sampleFunction(int $a, int $b = null): array
    {
        if ($a === $b) {
            bar();
        } elseif ($a > $b) {
            $foo->bar($arg1);
        } else {
            BazClass::bar($arg2, $arg3);
        }
    }

    final public static function bar()
    {
        // method body
    }
}
```

## Naming Conventions

We use the following naming structure for our standard objects. Of course due to PSR-1 the namespace and folder structures and filenames should match the namespace and class names.

```
/Module/Controller
/Module/Entity/Model
/Module/Entity/Mapper
/Module/Entity/Notifier
/Module/Test
/Module/templates
/Module/SubModule/...
```

### Modules

Modules should name the primary area of the system that this feature belongs with.  Examples include Learning, Admin and Calendar. Within each module you may have 'sub-modules'.  These will represent the main feature of the module, for example; Calendar -> Event, Calendar -> Export.  The top level generally will have a controller, but submodules can repeat the structure and have a controller and appropriate models.

### Classes

#### Model
Should be named 'model', but placed in namespace of the 'entity' name, this should generally match the DB table name, except it should use CamelCase rather than snake_case.  An example would be Calendar/Event/Model.php.  When importing this it should be aliased to the entity name.
#### Mapper
Similar to the 'model', the 'mapper' should be placed in the folder alongside the model it will hydrate.  It will be named 'Mapper', but when imported into other areas it will be aliased to 'EntityMapper'.
#### Controller
The controller should always be called Controller and be located in the root of the Module or Sub-Module.
#### Helper
There is no hard and fast rules on Helper objects.  These should be named to clearly indicate their purpose.  If a helper works on a specific entity it should be located in the entity folder.  Helper classes may also be more generic or support the controller, in which case they can live in the module folder.

### Namespaces and Use

When importing namespaces and classes into your file, it is important that they maintain their specificity.  This means what are generic named classes inside a module, should become specific name classes when included outside that module.

For example;
```php
use Blog\Model as Blog;
use Block\Mapper as BlogMapper;
use Mapper\Exception as MapperException;
```


### Interfaces, Traits & Abstract Classes

They must also end with the word "Abstract", "Trait" or "Interface" respectively.  Common interfaces may also be identified by a captial 'I', for example 'ISoftDeleted'.

Traits should be used sparingly and only if other options have first been explored.


### Filenames

For all other files, only alphanumeric characters, underscores, and the dash character ("-") are permitted. Spaces are strictly prohibited.

Any file that contains PHP code should end with the extension ".php", with the notable exception of view scripts.

### Functions and Methods

Verbosity is generally encouraged. Function names should be as verbose as is practical to fully describe their purpose and behaviour.

These are examples of acceptable names for functions:

    filterInput()

    getElementById()

    widgetFactory()

For object-oriented programming, accessors for instance or static variables should always be prefixed with "get" or "set". In implementing design patterns, such as the singleton or factory patterns, the name of the method should contain the pattern name where practical to more thoroughly describe behaviour.


### Variables

Verbosity is generally encouraged. Variables should always be as verbose as practical to describe the data that the developer intends to store in them. Terse variable names such as "$i" and "$n" are discouraged for all but the smallest loop contexts. If a loop contains more than 20 lines of code, the index variables should have more descriptive names.

### Constants

Constants may contain both alphanumeric characters and underscores. Numbers are permitted in constant names.

All letters used in a constant name must be capitalised, while all words in a constant name must be separated by underscore characters.

For example, EMBED_SUPPRESS_EMBED_EXCEPTION is permitted but EMBED_SUPPRESSEMBEDEXCEPTION is not.

Constants must be defined as class members with the "const" modifier. Defining constants in the global scope with the "define" function is permitted but strongly discouraged.

### Record (Database) Columns

All record columns must be in lowercase and usage of underscores(_) are encouraged for columns that consist of more than one word.

Foreign key fields must be in format [table_name]_[column]. Table names follow the same convention as columns.

## Coding Style


### Strings

When a string is literal (contains no variable substitutions), the apostrophe or "single quote" must always used to demarcate the string:

### String Containing Apostrophes

    $sql = "SELECT id, name FROM people WHERE name = 'Fred' OR name = 'Susan'";

### Variable Substitution

Variable substitution is permitted using the following form:

    // variable substitution
    $greeting = "Hello $name, welcome back!";

However please be sure you have practised [safe sanitisation](../scrub.md).

### String Concatenation

Strings may be concatenated using the "." operator. A space must always be added before and after the "." operator to improve readability.

## Inline Documentation

All documentation blocks ("docblocks") must be compatible with the phpDocumentor format. Describing the phpDocumentor format is beyond the scope of this document. For more information, visit: https://docs.phpdoc.org/guide/guides/docblocks.html


Every method, must have a docblock that contains at a minimum:

* A description of the function
* All of the arguments
* All of the possible return values

If a function/method throws an exception, use @throws to communicate. If an exception has been thrown by a function below, and you do not intend to catch it, you do not need to document it in your docblock.

Controller functions that map to a route should also contain an @api tag with the route. For example: '@api   GET /module/entity/{entityID}'


```php
    /**
     * Demonstrate docblock function
     *
     * @param int $bookCount Number of books
     * @return bool If book count is valid
     * @throws NotFound
     */
    public function test(int $bookCount) {
        if($bookCount > 1 ) {
            return true;
        }

        try {
            return $this->findBookcount() > 1;
        }
        catch(ConnectionException $e) {
                throw new NotFound('Unable to connect');
        }
    }
```
