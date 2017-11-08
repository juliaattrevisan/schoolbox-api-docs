# 7 Conclusions
This is a collection of takeaways from each of the sections, distilled from their explanations and reasoning into more bite-sized chunks for easier reference

## 7.1 Summary of Module

* The `register()` method is cleaner and preferred, though not universal.

* Note that ajax requests can initiate both types by using `$.get` and `$.post` respectively. These types of requests still follow the same rules.

* Sometimes you'll need to pass in other parameters to make your Controller method do special things, such as passing it one or more dates to filter a data set, etc. This is left to your discretion though it is advisable to make your routes as utilitarian as possible.

* It is recommended to compose a larger authorisation rule from a set of smaller discrete non-contextual rules, instead of making a giant monolith that is so specific it can't be reused or easily redefined. You can find some elementary authorisation classes in `/library/Alaress/Authorizer/Rule`.

* Module provides objects for Controllers which then perform operations or analyses on them. When all the necessary pieces can't be provided, either through missing data, insufficient permissions, etc. then exit early. Controllers should be able to assume that they have the minimum amount of data to start

* To prevent unnecessary logic from executing, we also prefer to error out as early as possible.

## 7.2 Summary of Controller
* Controllers are arrangers and organisers of information, like a foreman at a construction site - they don't do the dirty work themselves, instead delegating as much as possible to Supporting Classes.

* When possible, abstract away low-level details into other classes which can then be reused in various ways elsewhere. This segregation of details also helps prevent you making inadvertent changes that fly under the radar.
 
* One of the primary reasons to look at a Controller method is because we're pursuing a specific behaviour to change. The easier it is to find this, the better our lives become.

* Use the abstractions to access contents of superglobals $_SESSION, $_GET and $_POST and pass those to Supporting Classes instead of accessing them directly.

* Use the @api docblock in your Controller method so you can see the corresponding Route, saving you from manually looking it up.

## 7.3 Summary of Supporting Classes
* As per Controller methods, supporting classes should utilise dependency injection of interfaces (where possible) instead of concrete classes. This promotes reusability and testing by allowing us to substitute actual instances with stubs or mocked data.

* In unit tests we can declare the outcomes such that we can automatically detect behavioural changes.

* Design Patterns aren't rigid laws that absolutely must be obeyed and used at all times. They are (generally) simplified empirical solutions to particular *kinds* of problems that have consistently emerged. It is up to the author to determine whether a Design Pattern should be used, and if so, which one. 

## 7.4 Summary of Mapper
* Mappers contain methods which alter persistent storage; putting data in and getting data out of that storage into a format that's compatible with Controller methods in the domain (business logic) layer.

* Break away specific responsibilities into other classes instead. Note that Mappers expose a method that executes raw sql queries: `$mapper->getDatabase()->prepare()`

* Use `getSelectString()`, `getFromString()` and `hydrateSelect()` to specify a default hydration for each of your basic `findBy*` queries. This ensures a uniform behaviour in hydration and assembly.

* If you encounter strange mapper/instance behaviour, try turning off the mapper cache by passing the `cache => false` option.

## 7.5 Summary of Model
* Regardless of their complexity, Models are narcissistic in that they only care about their own properties, behaviours and "possessions" (dependencies). You can expect to interrogate Models to ask them questions about themselves and their dependencies. 

* Models are not responsible for performing any persisting work; these duties should be delegated to another class, such as a Mapper or a service class that utilises a Mapper.  

## 7.6 Summary of View
* Render only the data that was provided - though twig is powerful, it can be dangerous in an indirect or obscure manner.

* Twig vars should be data-structure agnostic; twig vars should not know whether they're operating on an object or an array.

* There's a `/templates/sub` folder where you can put globally reusable template pieces. Otherwise, store as many resuable pieces in your module's templates folder.

* Check the `/pantry` for user interface elements that you can use, and their interactions with other elements and styles.

* Note that when you add javascript to the compilation step (look in your `Gruntfile.js`) you're making it globally available at the cost of increasing the minified file's size.