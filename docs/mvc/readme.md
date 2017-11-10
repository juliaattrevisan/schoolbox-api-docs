# Table of Contents
* [Best Practice Statements](best-practice.md)

* [Anatomy of Module, Controller, Mapper, Model, Other](00-intro.md)
* [Overview of Terms](00-intro.md)
* [A Quick Recap](00-intro.md)
* [1 Responsibilities of Module](01-module.md)
    * 1.1 Determining the Route and corresponding Controller method
        * 1.1.1 Tokens are array keys in $params
        * 1.1.2 GET Routes should not mutate data
    * 1.2 Permissions
    * 1.3 Preloads
    * 1.4 Error Handling
    * 1.5 Historical Format
* [2 Responsibilities of Controller](02-controller.md) 
    * 2.1 Shove details elsewhere
    * 2.2 getRequest() and getResponse()
        * 2.2.1 GET and POST vars
        * 2.2.2 Setting The Response for View
        * 2.2.3 Redirects
    * 2.3 Superglobals
        * 2.3.1 Session Superglobal
    * 2.4 Error Handling
    * 2.5 Corresponding Route Docblock
* [3 Responsibilities of Supporting Classes](03-supporting.md)
    * 3.1 Design Patterns
    * 3.2 Unit Tests
    * 3.3 Error Handling
* [4 Responsibilities of Mapper](04-mapper.md)
    * 4.1 Mapper Usage
        * 4.1.1 Fetch minimal data set vs trimming away
        * 4.1.2 Fetching Scalars
        * 4.1.3 Queries in loops
    * 4.2 Special Queries
    * 4.3 Default Hydration
    * 4.4 Caching
* [5 Responsibilities of Model](05-model.md)
* [6 Responsibilities of View](06-view.md)
    * 6.1 Render what was provided
    * 6.2 Twig Syntax
    * 6.3 Reusable Fragments
    * 6.4 The Pantry
    * 6.5 Compiling CSS and Javascript 
* [7 Conclusions](07-summary.md)
    * 7.1 Summary of Module
    * 7.2 Summary of Controller
    * 7.3 Summary of Supporting Classes
    * 7.4 Summary of Mapper
    * 7.5 Summary of Model
    * 7.6 Summary of View
* [8 Further Reading](08-references.md)