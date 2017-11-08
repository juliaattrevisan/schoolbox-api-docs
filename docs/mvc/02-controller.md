# 2 Responsibilities of Controller 
The bulk of business logic occurs in the Controller methods. This accepts the items provided by Module (if applicable) and performs operations or analyses on them, preparing the output for twig to render. 

> Controllers are arrangers and organisers of information, like a foreman at a construction site - they don't do the dirty work themselves, instead delegating as much as possible to Supporting Classes.
 
We deliberately choose which data to provide to twig. Twig has the ability to execute public methods on whatever objects are provided; sometimes these public methods include database calls that weren't intended for rendering purposes. Unbenownst to front-end developers, they might be making expensive database calls - getting a parent of a user is an example. This is a good reason for Models to be quite dumb but we'll get to that shortly.  

## 2.1 Shove details elsewhere
Controller methods aren't easily reusable, except in subclasses; even then, having a whole bunch of logic dumped into Controller methods isn't ideal. Controller methods should act as "highways" which give you a rapid, higher-level overview of what is going on; it shouldn't be a place where lower-level details are found. This impedes reusability and heightens the chances of copy-paste bugs occurring, or inconsistent behaviours. It's much harder to unit test Controller methods than independent, high-cohesion classes. 

> When possible, abstract away low-level details into other classes which can then be reused in various ways elsewhere. This segregation of details also helps prevent you making inadvertent changes that fly under the radar. 

You should be able to read method names and get a general idea of what is going on without having to interpret every line of code to derive possible meaning. Don't be a human compiler! 

> One of the primary reasons to look at a Controller method is because we're pursuing a specific behaviour to change. The easier it is to find this, the better our lives become.

## 2.2 getRequest() and getResponse()

## 2.3 Superglobals
We don't directly access the superglobals `$_SESSION`, `$_GET` and `$_POST` because we can't mock these out with test data and also presumes a lot about where this data came from. Instead, there are accessor methods which provide an abstraction. 

> Use the abstractions to access contents of superglobals $_SESSION, $_GET and $_POST and pass those to Supporting Classes instead of accessing them directly.

## 2.3.1 Session Superglobal
Don't reference `$_SESSION` directly. This is very difficult to test and not great when on the command line. Instead, you can use `$this->getSession()->set()` and `$this->getSession()->get()` methods.

## 2.3.2 GET Superglobal
Querystring parameters can be obtained using the `$this->getRequest()->query` array instead. To access a particular querystring parameter `cat`, you can use

```php
$this->getRequest()->query['cat']
```

## 2.3.3 POST Superglobal
POST parameters can be obtained using the `$this->getRequest()->post` array instead. To access a particular POST parameter `cat`, you can use

```php
$this->getRequest()->post['cat']
```

## 2.4 Error Handling
Controllers are also primarily responsible for handling of error states that can't be foreseen or computed by Module. There are a couple of ways to do it in the current codebase, but the preferred approach is to throw an exception. See *1.4 Error Handling* for implementation example.

> Controllers should format an unfriendly Exception into a user-friendly one. This provides users with a human-readable (if a little bland) message rather than the alternative, a stack trace.

Controllers are also the last opportunity in which to do this; especially when coordinating a series of Supporting Classes, each capable of throwing their own Exceptions. 

With that in mind, Supporting Classes should manage the exceptions that their dependents throw, and those dependents do the same to their dependents and so on, leaving the Controller to deal with exceptions that none of their dependents could manage. 

This prevents the need for Controllers to know about all the exceptions being thrown everywhere (it shouldn't need to deal with that much detail)

> Supporting Classes should manage the exceptions being thrown by their dependents. Controllers should manage exceptions being thrown by Supporting Classes.

Also, don't `die()` or `exit()` in Controllers - you should `return` instead (unless you're debugging or exploring)

## 2.5 Corresponding Route Docblock
In Module we can see which Controller method is executed from a particular Route, but the converse isn't so obvious. To alleviate the need to manually cross-reference, we can use a docblock `@api` to state which Route is relevant to a particular Controller method.

```php
class Controller extends Base {

    /**
     * @api GET /sports/{sportId}/divisions/{divisionId}/teams/{teamId}/players/{playerId}
     */
     public function getDetailForPlayer( ... ) {
        ...
     }
}
```

> Use the @api docblock in your Controller method so you can see the corresponding Route, saving you from manually looking it up.