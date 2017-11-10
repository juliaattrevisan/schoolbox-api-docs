# Anatomy of Module, Controller, Supporting Classes, Mapper, Model 
If you followed the Quickstart guide, you'll have seen how to make an Aura module and how to do some basic operations in it. We made it *work* in a fairly rudimentary way for the purposes of illustration and to compare the differences between the Handler and Aura paradigms.

Nw we'll examine these pieces of an Aura module - the Module, Controller, Mapper, Model (and other classes) in greater detail. If none of these words hold meaning, go back to the Quickstart and get yourself back up to speed.

# Overview of Terms
* *Module.* A custom concept that we made. It encapsulates Routing, Permissions and Preloads. Respectively: determines which Controller method should execute based on the url provided, whether the Controller method has the permissions to execute, and what objects it needs to execute.
* *Controller.* Provides details about the web request to business logic contained in Supporting Classes. Controller arranges the output data for the View to render (in this case, twig). 
* *Supporting Classes.* Additional high-cohesion classes that contains lower-level details, hiding this from the main Controller methods. Utilises Mappers and Models.
* *Mapper.* A database abstraction. Gets data out of the database into objects and vice versa. Typically contains other database queries that are commonly used.
* *Model.* A representation of a concept or idea. They don't do persistent work in the database, but you can ask them about their properties and relations and other relevant calculations.
* *View.* Renders the output of the Controller method so the user can see it in their browser.

# A Quick Recap
This is the basic flow of operations from the moment you access a url to seeing output on screen.

1. Module.php performs a regular expression match on the url. The pattern used in the regular expression is a *route* and ultimately determines:
   * whether any route-specific permissions prevent the associated Controller from executing
   * which Controller method should execute
   * which objects are given to the Controller as params 
   
2. Controller method executes, using the objects provided by Module
3. Controller gives data vars to Twig
4. Twig executes, performing rendering duties on the data provided 
5. Twig output renders to browser 