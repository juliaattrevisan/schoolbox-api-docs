# Anatomy of Module, Controller, Mapper, Model, Other
If you followed the Quickstart guide, you'll have seen how to make an Aura module and how to do some basic operations in it. We made it *work* in a fairly rudimentary way for the purposes of illustration and to compare the differences between the Handler and Aura paradigms.

Nw we'll examine these pieces of an Aura module - the Module, Controller, Mapper, Model (and other classes) in greater detail. If none of these words hold meaning, go back to the Quickstart and get yourself back up to speed.

# Overview of Terms
* *Module.* A custom concept that we made. It encapsulates Routing, Permissions and Preloads. Respectively: determines which Controller method should execute based on the url provided, whether the Controller method has the permissions to execute, and what objects it needs to execute.
* *Controller.* Deals with business logic, calculating the output data for the View to render (in this case, twig).  Utilises Mappers and Models.
* *Supporting Classes.* Additional high-cohesion classes that contains lower-level details, hiding this from the main Controller methods.
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

# 1 Responsibilities of Module
Modules use the url to determine which Controller method should run, whether there are permissions that might prevent a Controller from starting and what objects a Controller method should operate with.

There are a variety of formats and styles for building a Module. An example of the most recent can be found in `/library/Schoolbox/Learning/Assessment/Module.php` in the form of `$this->register()`. However, this is simply a reorganisation of the original implementation seen here, and also in older locations such as `/library/Schoolbox/ResourceBooking/Booking/Module.php`. The basic principles still apply regardless of the style chosen.

## 1.1 Determining the Route
*Routes* are determined by pattern-matching the url to one of the regular expressions listed in `initRoutes()`. This contains a `$routes` key-value array; the `key` is the pattern and the `value` is the Controller method associated with that pattern. Additionally, the type of request is also used to help determine routes; specifically, whether this was a `GET` (a url you type in the browser) or a `POST` (a form submit) request.

Note that ajax requests can initiate both types by using `$.get` and `$.post` respectively. These types of requests still follow the same rules.

Since it uses pattern-matching to figure out which Controller method runs, it makes a whole lot of sense to choose a url structure that won't be confusing. While there are a number of url structures currently floating around in schoolbox, a generally-preferred approach emerged: 

```php
/namespace/collection/id_of_item_in_collection/collection/id_of_item_in_collection...

e.g. Player 4 in Team 3 in Division 2 in Sport 1 in the Aura module "fixtures"
/fixtures/sports/1/divisions/2/teams/3/player/4
```

As an example:

```php
$routes = [
    'GET' => [
        '/sports/list' => 'getList'
        '/sports/{sportId}/divisions/{divisionId}/teams/{teamId}/player/{playerId}' => 'getDetailForPlayer'
    ],
    'POST' => [
        '/sports/update' => 'postUpdate',
        '/sports/delete' => 'postDelete'
    ]
];
```

...and the Controller method `getDetailForPlayer()` would give you some kind of information about a player in a particular team that was in a particular division for a particular sport. This is a fairly non-conflicting hierarchical structure.

The Controller methods can only appear once. You can't make multiple routes end up in the same Controller method.

> Sometimes you'll need to pass in other parameters to make your Controller method do special things, such as passing it one or more dates to filter a data set, etc. This is left to your discretion though it is advisable to make your routes as utilitarian as possible.

## 1.2 Route Permissions
Controllers are kind of resource-intensive, they cost a bit to start up. To prevent unnecessary Controller startup, we do our permissions checks as early as possible. The earliest place we can do it is in Module.php.

There are a number of predefined classes which can be combined in myriad ways to fine-tune your authorisation check. It is useful to provide a synonymous docblock in plain English to reaffirm what's being seen. This example applies a particular set of requirements in order for the `getDetailForPlayer()` method to execute.

```php
/**
 * These pages may be accessed by:
 *
 * * superusers
 * * staff
 * * the child themselves
 * * the child's parents
 *
 * {@inheritdoc}
 */
public function initRoutePermissions(Authorizer $authorizer)
{
    $ruleRegexp = '(getDetailForPlayer)';
    $this
        ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
        ->addRoutePermission(
            $authorizer,
            $ruleRegexp,
            new Rule\Any(
                new Rule\IsSuperuser(),
                new Rule\HasRole(Role::STAFF),
                new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'playerId')
            )
        );
}
```

> It is recommended to compose a larger rule from a set of smaller discrete non-contextual rules, instead of making a giant monolith that is so specific it can't be reused or easily redefined. You can find some elementary authorisation classes in `/library/Alaress/Authorizer/Rule`.

Tokens in the url are made available in a secret `$params` array that some authorisation classes can utilise. It's not made particularly clear, but if you dig you'll find where it's used. Alternatively you can just accept it works like this, and simply use it.

*Don't do it like the following example.* This is bad news bears.

```php
/**
 * These pages may be accessed by:
 *
 * * superusers
 * * staff
 * * the child themselves
 * * the child's parents
 *
 * {@inheritdoc}
 */
public function initRoutePermissions(Authorizer $authorizer)
{
    $ruleRegexp = '(getDetailForPlayer)';
    $this
        ->addRoutePermission($authorizer, $ruleRegexp, new Rule\IsLoggedIn())
        ->addRoutePermission(
            $authorizer,
            $ruleRegexp,
            new Rule\UsersAccessSportsFixturesPlayerDetailPage('playerId')
        );
}

...

/**
 * authorize method for UsersAccessSportsFixturesPlayerDetailPage
 * {@inheritdoc}
 */
public function authorize(array $params = array(), User $user = null)
{
    // massive amounts of low-level logic specific to this authorisation class
    // that could otherwise have been derived from a combination of pre-existing 
    // or reusable/smaller authorisation classes
}
```

Having said that, it is okay to make macros of common combinations. You can find some in `isStudentOfClass()` and `isTeacherOfClass()` etc.

## 1.3 Preloads
Route Preloads, or simply Preloads, are pre-computed (hydrated) objects that are given to a Controller in an associative array. The Controller accepts these as parameters. 

Computing the preloads in Module allows us to exit earlier without incurring the cost of starting up a Controller. This avoids a scenario where Controllers (expensively) start up, only to find out it didn't have everything it needed, and then consequently error out. A lot of logic is involved in checking all these pieces and it clutters up what the Controller method is really designed to do - that is, to operate on data, not check whether it should have started.

> Module provides objects for Controllers which then perform operations or analyses on them. When all the necessary pieces can't be provided, either through missing data, insufficient permissions, etc. then exit early. Controllers should be able to assume that they have the minimum amount of data to start

## 1.4 Error Handling
Errors are expressed by throwing an Exception that is caught and rendered elsewhere. Error messages correlate to a particular const in the `ErrorMapper` class; to avoid hardcoding integers everywhere you can use the human-readable const instead.

```php
use Schoolbox\Error\Mapper as ErrorMapper;

if ($errorCondition) {
    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
}
```

> To prevent unnecessary logic from executing, we also prefer to error out as early as possible.

Throwing exceptions in this manner causes a grey screen to emerge with some reasonably end-user comprehensible error message, rather than an obnoxious stack trace. If you're in a Mage form, the message appears (usually) in a little red box near the submit button.

# 2 Responsibilities of Controller 
The bulk of business logic occurs in the Controller methods. This accepts the items provided by Module (if applicable) and performs operations or analyses on them, preparing the output for twig to render. 

> Controllers are arrangers and organisers of information, like a foreman at a construction site - they don't do the dirty work themselves.
 
We deliberately choose which data to provide to twig. Twig has the ability to execute public methods on whatever objects are provided; sometimes these public methods include database calls that weren't intended for rendering purposes. Unbenownst to front-end developers, they might be making expensive database calls - getting a parent of a user is an example. This is a good reason for Models to be quite dumb but we'll get to that shortly.  

## 2.1 Shove details elsewhere
Controller methods aren't easily reusable, except in subclasses; even then, having a whole bunch of logic dumped into Controller methods isn't ideal. Controller methods should act as "highways" which give you a rapid, higher-level overview of what is going on; it shouldn't be a place where lower-level details are found. This impedes reusability and heightens the chances of copy-paste bugs occurring, or inconsistent behaviours. It's much harder to unit test Controller methods than independent, high-cohesion classes. 

> When possible, abstract away low-level details into other classes which can then be reused in various ways elsewhere. This segregation of details also helps prevent you making inadvertent changes that fly under the radar. 

You should be able to read method names and get a general idea of what is going on without having to interpret every line of code to derive possible meaning. Don't be a human compiler! 

> One of the primary reasons to look at a Controller method is because we're pursuing a specific behaviour to change. The easier it is to find this, the better our lives become.

## 2.2 Error Handling
Controllers are also primarily responsible for handling of error states that can't be foreseen or computed by Module. There are a couple of ways to do it in the current codebase, but the preferred approach is to throw an exception. See *1.4 Error Handling* for implementation example.

Don't `die()` or `exit()` in Controllers - you should `return` instead (unless you're debugging or exploring)

# 3 Responsibilities of Supporting Classes
# 4 Responsibilities of Mapper
# 5 Responsibilities of Model
# 6 Responsibilities of View

# 7 Further Reading

* *Aura Routing.* 
We developed an adaptation of this for Module.php, but this original documentation might help you with capabilities that aren't immediately obvious such as optional args etc.
http://auraphp.com/framework/2.x/en/router/

* MVC Model-View-Controller
Aura modules follow this pattern.
https://en.wikipedia.org/wiki/Model%E2%80%93view%E2%80%93controller

* Cohesion and Coupling
A brief overview on how to abstract details into Supporting Classes. A precursor to Design Patterns.
https://stackoverflow.com/questions/10830135/what-is-high-cohesion-and-how-to-use-it-make-it