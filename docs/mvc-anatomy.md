# Anatomy of Module, Controller, Mapper, Model, Other
If you followed the Quickstart guide, you'll have seen how to make an Aura module and how to do some basic operations in it. We made it *work* in a fairly rudimentary way for the purposes of illustration and to compare the differences between the Handler and Aura paradigms.

Nw we'll examine these pieces of an Aura module - the Module, Controller, Mapper, Model (and other classes) in greater detail. If none of these words hold meaning, go back to the Quickstart and get yourself back up to speed.

# A Quick Recap
This is the basic flow of operations from the moment you access a url to seeing output on screen.

1. Module.php performs a regular expression match on the url. The pattern used in the regular expression is a *route* and the route determines:
   
   1.1 whether any route-specific permissions prevent the associated Controller from executing
   
   1.2 which Controller method should execute
   
   1.3 which objects are given to the Controller as params 
   
2. Controller method executes, using the objects provided by Module
3. Controller gives data vars to Twig
4. Twig executes, performing rendering duties on the data provided 
5. Twig output renders to browser 