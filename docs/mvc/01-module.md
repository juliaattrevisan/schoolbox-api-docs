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

## 1.4 register() format
We previously saw that the Module methods `initRoutes()`, `initRoutePermissions()` and `initRoutePreloads()` comprise the basic Module. The `register()` method is shortcut that abstracts away these details. You can see an example in `library/Schoolbox/Learning/Assessment/Module.php`.
 
 Each `register()` usage takes five arguments:
 
 * *GET or POST.* We saw this in the `initRoutes()` method.
 * *Regex route.* We saw this in the `initRoutes()` method.
 * *Corresponding controller method.* We saw this in the `initRoutes()` method.
 * *Permission objects.* We saw this in the `initRoutePermissions()` method.
 * *Preloads.* We saw this in the `initRoutePreloads()` method.
 
> The `register()` method is cleaner and preferred, though not universal. It builds on the basic `initRoutes()` `initRoutePermissions()` and `initRoutePreloads()` concepts. 

## 1.5 Error Handling
Errors are expressed by throwing an Exception that is caught and rendered elsewhere. Error messages correlate to a particular const in the `ErrorMapper` class; to avoid hardcoding integers everywhere you can use the human-readable const instead.

```php
use Schoolbox\Error\Mapper as ErrorMapper;

if ($errorCondition) {
    throw $this->getErrorMapper()->generateException(ErrorMapper::ERROR_PAGE_NOT_FOUND);
}
```

> To prevent unnecessary logic from executing, we also prefer to error out as early as possible.

Throwing exceptions in this manner causes a grey screen to emerge with some reasonably end-user comprehensible error message, rather than an obnoxious stack trace. If you're in a Mage form, the message appears (usually) in a little red box near the submit button.