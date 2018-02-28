# Best Practice
Basic statements on what to do. For specific reasoning, see links next to each statement.

*General Principles that apply everywhere*
* Always check the jira number in your commit messages.
* Use dependency injection rather than concrete instantiation.
* Throw errors as early as possible.
* Convert developer-centric exceptions into user-friendly ones via ErrorMapper.
* Add unit tests.
* Add docblocks and inline comments to explain intent.
* Reuse before reinvent (don't be wasteful)
* Rebase frequently to minimise merge conflict sufferage.
* Please double check everything you make (don't *compel* others to clean up after you)

## 1 Module
* Use the `register()` method when defining Routes. [(more)](01-module.md#1-responsibilities-of-module)
* Use a RESTful arrangement of Route tokens when deciding your url structure. [(more)](01-module.md#11-determining-the-route-and-corresponding-controller-method)
* Use existing Permissions classes (or compositions thereof) before making a new one. [(more)](01-module.md#12-permissions)
* Be efficient when you make new Permission classes. [(more)](01-module.md#12-permissions)
* Construct Preloads for the Controller. If something went wrong, throw an appropriate exception from ErrorMapper as early as possible. [(more)](01-module.md#13-preloads)
* Use GET requests for retrieving data only. Use POST requests for changing data. [(more)](01-module.md#112-get-routes-should-not-mutate-data)

## 2 Controller
* Controllers organise low-level implementations that produce actual output. [(more)](02-controller.md#2-responsibilities-of-controller)
* Delegate low-level implementations to Supporting Classes. [(more)](02-controller.md#21-shove-details-elsewhere)
* Controllers themselves should do very little other than taking the request and arranging the output to give to the View. [(more)](02-controller.md#2-responsibilities-of-controller)
* Don't access superglobals directly; use abstraction methods `$this->getSession()`, `$this->getRequest()->query` and `$this->getRequest()->post` instead. [(more)](02-controller.md#23-superglobals)
* Use `getResponse()` to set up data for twig. [(more)](02-controller.md#222-setting-the-response-for-view)
* Use `getResponse()` to redirect to specific urls or Routes. [(more)](02-controller.md#223-redirects)
* Catch unfriendly exceptions and convert them into user-friendly ones, otherwise users experience a meaningless stack trace. [(more)](02-controller.md#24-error-handling)
* Use the `@api` docblock to indicate corresponding Routes when documenting Controller methods. [(more)](02-controller.md#25-corresponding-route-docblock)

## 3 Supporting Classes
* Contains low-level business logic and processing, hiding it from the main Controller method in which it's called. [(more)](03-supporting.md#3-responsibilities-of-supporting-classes)
* Inject dependencies into Supporting Classes for testability and reusability purposes. [(more)](03-supporting.md#3-responsibilities-of-supporting-classes)
* Add unit tests. [(more)](03-supporting.md#32-unit-tests)
* Optionally use existing Design Patterns as starting points when architecting additional classes. [(more)](03-supporting.md#31-design-patterns)
* Progressively catch and process exceptions from lower-level dependencies. If a Supporting Class can't/shouldn't manage a particular exception, defer it to the caller. [(more)](03-supporting.md#33-error-handling)
 
## 4 Mapper
* Remember that Mappers convert database records into business models. With large data sets this could be costly process. [(more)](04-mapper.md#4-responsibilities-of-mapper)
* To minimise cost, try to use the least amount of calls to obtain the minimum data size you actually require. [(more)](04-mapper.md#4-mapper-usage)
* Avoid getting data only to throw it away. This is wasteful. [(more)](04-mapper.md#411-fetch-minimal-data-set-vs-trimming-away)
* Especially avoid making database accesses in loops, a costly and inefficient approach. [(more)](04-mapper.md#413-queries-in-loops)
* Use Supporting Classes to store very specific queries that can only be used in rare places. Use Mappers as dependencies so you can execute queries meaningfully. [(more)](04-mapper.md#42-special-queries)
* Specify a default hydration using `getSelectString()`, `getFromString()` and `hydrateSelect()` methods in your Mapper. [(more)](04-mapper.md#43-default-hydration)
* Sometimes you need to turn off caching by passing it a `'cache' => false` option especially when you're mutating instances procedurally. [(more)](04-mapper.md#44-caching)
* These base methods should solve most of your database access needs. You can pass it an additional `$options` array for further configuration such as including soft-deleted objects, caching, ordering and limits. [(more)](04-mapper.md#45-useful-methods)
    * `findOneByColumn()` Find exactly one model to return for a specified column and value; throws exception otherwise.
    * `findOneByColumnList()` Find exactly one model to return for an associative array of column-value pairs; throws exception otherwise.
    * `findIdsByColumn()` Finds only the scalar ID value of records matched, rather than the full objects, for the specified column and value.
    * `findIdsByColumn()` Finds only the scalar ID value of records matched, rather than the full objects, for associative array of column-value pairs.
    * `findByPredicates()` Finds a set of models that match the provided predicates (builds a `WHERE` clause using objects)
    * `findByIdWithFallback()` Finds exactly one model, even if it's been soft-deleted.

## 5 Model
* Models only know about themselves and their own dependencies. [(more)](05-model.md#5-responsibilities-of-model)
* Models don't access the database. [(more)](05-model.md#5-responsibilities-of-model) 

## 6 View
* Render the data that Controller provided; don't try to obtain data. [(more)](06-view.md#61-responsibilities-of-view)
* Twig can access php methods - be mindful when using twig vars that might be making expensive database accesses. [(more)](06-view.md#62-twig-syntax)
* Break large twig templates into smaller ones, just like php methods and php classes. [(more)](06-view.md#63-reusable-fragments)
* Use twig macros to your advantage. [(more)](06-view.md#63-reusable-fragments)
* The Pantry contains samples for user interface elements and basic styling/layout. Use them instead of making up your own, where possible. [(more)](06-view.md#64-the-pantry)
* Sanitise data on rendering to prevent javascript-based attacks. PHPLib and Twig do this differently. [(more)](06-view.md#66-data-sanitisation-escaping-dangerous-content)
 