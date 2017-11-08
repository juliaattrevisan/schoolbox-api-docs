# 1 Responsibilities of Module
Modules use the url to determine which Controller method should run, whether there are permissions that might prevent a Controller from starting and what objects a Controller method should operate with.

A single method call takes five arguments to determine which Controller method executes and what is provided to these methods.
 
 * *GET or POST.* An http request type.
 * *Regex route.* A regular expression that matches a given url structure to a Controller method. 
 * *Corresponding Controller method.* This is the name of the Controller method that should actually run. 
 * *Permission objects.* A set of objects containing booleans which must pass.
 * *Preloads.* A set of dependencies that are provided to the Controller.
 
> The `register()` method is cleaner and preferred, though not universal.

## 1.1 Determining the Route and corresponding Controller method
*Routes* are determined by pattern-matching the url to one of the regular expressions listed in `register()` statements and the type of web request it was (GET or POST). This combination determines which Controller method eventually executes. 

> Note that ajax requests can initiate both types by using `$.get` and `$.post` respectively. These types of requests still follow the same rules.

Since it uses pattern-matching to figure out which Controller method runs, it makes a whole lot of sense to choose a url structure that won't be confusing. While there are a number of url structures currently floating around in schoolbox, a generally-preferred approach emerged that was synonymous with a RESTful interface.

```php
/namespace/collection/id_of_item_in_collection/collection/id_of_item_in_collection...

e.g. Player 4 in Team 3 in Division 2 in Sport 1 in the Aura module "fixtures"
/fixtures/sports/1/divisions/2/teams/3/player/4
```

We also provide the Controller method associated with this url format. As an example:

```
$playerRoute = '/sports/{sportId}/divisions/{divisionId}/teams/{teamId}/players/{playerId}';

$this->register('GET', $playerRoute, 'getDetailForPlayer', ... , ...);
```

> Sometimes you'll need to pass in other parameters to make your Controller method do special things, such as passing it one or more dates to filter a data set, etc. This is left to your discretion though it is advisable to make your routes as utilitarian as possible.

### 1.1.1 Tokens are array keys in $params
The tokens in route regexes - the stuff in curly braces `{  }` will be made available to you as keys in the `$params` array. This array gets used to calculate Permissions and Preloads.

## 1.2 Permissions
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
 */
$perms = new Rule\Any(
    new Rule\IsSuperuser(),
    new Rule\HasRole(Role::STAFF),
    new Rule\IsUserOrParentOfUser($this->getExternalUser(), $this->getUserMapper(), 'playerId')
);
```

Then you can drop it into the `register()` statement in the appropriate place:

```php
$this->register('GET', $playerRoute, 'getDetailForPlayer', $perms , ...);
```

> It is recommended to compose a larger authorisation rule from a set of smaller discrete non-contextual rules, instead of making a giant monolith that is so specific it can't be reused or easily redefined. You can find some elementary authorisation classes in `/library/Alaress/Authorizer/Rule`.

What you want to *avoid* doing is making up an authorisation rule that is so complex and unique that it only applies to one place:
 
```php
class MyPermission implements IAuthorizationRule {

    public function authorize(array $params = array(), User $user = null) {
    
        // massive amounts of low-level logic specific to this authorisation class
        // that could otherwise have been derived from a combination of pre-existing 
        // or reusable/smaller authorisation classes
        //
        // or
        // 
        // this could have been broken down into reusable parts
        
    }
}
```

## 1.3 Preloads
Route Preloads, or simply Preloads, are pre-computed (hydrated) objects that are given to a Controller in an associative array. The Controller accepts these as parameters. 

Computing the preloads in Module allows us to exit earlier without incurring the cost of starting up a Controller. This avoids a scenario where Controllers (expensively) start up, only to find out it didn't have everything it needed, and then consequently error out. A lot of logic is involved in checking all these pieces and it clutters up what the Controller method is really designed to do - that is, to operate on data, not check whether it should have started.

> Module provides objects for Controllers which then perform operations or analyses on them. When all the necessary pieces can't be provided, either through missing data, insufficient permissions, etc. then exit early. Controllers should be able to assume that they have the minimum amount of data to start

As an example:

```php
$preloadUser = function ($params) { return $this->preloadPlayer($params); };

$this->register('GET', $playerRoute, 'getDetailForPlayer', $perms, $preloadUser);

/**
 * Provide a User instance in the $player param of Controller method
 */
private function preloadPlayer(array $params) {

    // $params is an array of tokens that matched the url in the route regex
    // Each token appears as a key in the $params array
    
    $player = $this->getUserMapper()->findById($params['playerId']);
    
    // Check errors etc.
    
    return [
        'player' => $Player 
    ];
}
```

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

## 1.5 Historical Format
In older parts of schoolbox you'll encounter the Module methods `initRoutes()`, `initRoutePermissions()` and `initRoutePreloads()`. 

The `register()` format wraps around these three methods to provide a friendlier interface. Respectively, these three methods calculate route regex and corresponding Controller methods; whether any Permissions checks pass; and what pre-computed objects should be given to the Controller.

