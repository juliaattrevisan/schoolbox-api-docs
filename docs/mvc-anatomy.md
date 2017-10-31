# Anatomy of Module, Controller, Mapper, Model, Other
If you followed the Quickstart guide, you'll have seen how to make an Aura module and how to do some basic operations in it. We made it *work* in a fairly rudimentary way for the purposes of illustration and to compare the differences between the Handler and Aura paradigms.

Nw we'll examine these pieces of an Aura module - the Module, Controller, Mapper, Model (and other classes) in greater detail. If none of these words hold meaning, go back to the Quickstart and get yourself back up to speed.

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
Modules use the url to determine which controller method should run, whether there are permissions that might prevent a controller from starting and what objects a controller method should operate with.

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

...and the controller method `getDetailForPlayer()` would give you some kind of information about a player in a particular team that wasin a particular division for a particular sport. This is a fairly non-conflicting hierarchical structure.

The controller methods can only appear once. You can't make multiple routes end up in the same controller method.

> Sometimes you'll need to pass in other parameters to make your controller method do special things, such as passing it one or more dates to filter a data set, etc. This is left to your discretion though it is advisable to make your routes as utilitarian as possible.

## 1.2 Route Permissions
Controllers are kind of resource-intensive, they cost a bit to start up. To prevent unnecessary controller startup, we do our permissions checks as early as possible. The earliest place we can do it is in Module.php.

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
 
 
