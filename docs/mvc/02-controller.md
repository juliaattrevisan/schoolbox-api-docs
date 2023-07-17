# 2 Responsibilities of Controller
The bulk of business logic occurs in the Controller methods. This accepts the items provided by Module (if applicable) and performs operations or analyses on them, preparing the output for twig to render.

> Controllers are arrangers and organisers of information, like a foreman at a construction site - they don't do the dirty work themselves, instead delegating as much as possible to Supporting Classes.

We deliberately choose which data to provide to twig. Twig has the ability to execute public methods on whatever objects are provided; sometimes these public methods include database calls that weren't intended for rendering purposes. Unbenownst to front-end developers, they might be making expensive database calls - getting a parent of a user is an example. This is a good reason for Models to be quite dumb but we'll get to that shortly.

## 2.1 Shove details elsewhere
Controller methods aren't easily reusable, except in subclasses; even then, having a whole bunch of logic dumped into Controller methods isn't ideal. Controller methods should act as "highways" which give you a rapid, higher-level overview of what is going on; it shouldn't be a place where lower-level details are found. This impedes reusability and heightens the chances of copy-paste bugs occurring, or inconsistent behaviours. It's much harder to unit test Controller methods than independent, high-cohesion classes.

> When possible, abstract away low-level details into other classes which can then be reused in various ways elsewhere. This segregation of details also helps prevent you making inadvertent changes that fly under the radar.

You should be able to read method names and get a general idea of what is going on without having to interpret every line of code to derive possible meaning. Don't be a human compiler!

> One of the primary reasons to look at a Controller method is because we're pursuing a specific behaviour to change. The easier it is to find this, the better our lives become.

## 2.2 Requests and Responses

These represent the incoming HTTP request, and the outgoing HTTP response,
respectively.

### 2.2.1 Reading data from the Request

`$this->psr7Request` is a standard PSR-7 `ServerRequestInterface` object which
represents the incoming HTTP request.

For example, the following request information may be accessed with these
methods:
* `getUploadedFiles` to fetch any files uploaded as part of the request
* `getParsedBody` for fetching parsed JSON payloads
* `getServerParams` for fetching the contents of `$_SERVER` without accessing
  the superglobal
* `getHeader` or `getHeaderLine` for fetching a request header

See the official documentation for
[ServerRequestInterface](https://www.php-fig.org/psr/psr-7/#321-psrhttpmessageserverrequestinterface)
and its parent interfaces
[RequestInterface](https://www.php-fig.org/psr/psr-7/#32-psrhttpmessagerequestinterface)
and
[MessageInterface](https://www.php-fig.org/psr/psr-7/#31-psrhttpmessagemessageinterface)
for further documentation on what can be done with a `ServerRequestInterface`
instance.

#### 2.2.1.1 Helpers for GET and POST vars

Querystring parameters may be fetched from `$this->psr7Request->getQueryParams()`,
but are better obtained using the `$this->requestQuery` helper property.

To access a particular querystring parameter `cat`, you can use:

```php
$this->requestQuery['cat']
```

Likewise: POST parameters can be fetched from `$this->psr7Request->getParsedBody()`,
but are better obtained using the `$this->requestPost` helper property.

```php
$this->requestPost['cat']
```

Both `$this->requestQuery` and `$this->requestPost` data are instances of
`\Alaress\Web\Request\Values`: this class allows array access via its
`ArrayObject` superclass, and also provides helpers for fetching a value with a
given type and alternate fallback value:
```php
$this->requestQuery->get('cat');
$this->requestQuery->get('cat', 'dog');

$this->requestQuery->getBool('checked');
$this->requestQuery->getBool('checked', true);

$this->requestQuery->getInt('id');
$this->requestQuery->getInt('id', 1);

$this->requestQuery->getFloat('value');
$this->requestQuery->getFloat('value', 1.50);
```

### 2.2.2 Setting The Response for View

When a Controller finishes processing, it needs to set up the resultant data for
the View to render. This is done via setting the `psr7Response` property to a
`\Psr\Http\Message\ResponseInterface` instance; this may be done via helper
methods within the controller class.

Since these helper methods all return a standard `ResponseInterface` instance,
the response may be customised further using its standard methods, e.g.:
* `withStatus` to set a HTTP response code
* `withHeader` to set a header value

See the official documentation for
[ResponseInterface](https://www.php-fig.org/psr/psr-7/#33-psrhttpmessageresponseinterface)
and its parent interface
[MessageInterface](https://www.php-fig.org/psr/psr-7/#31-psrhttpmessagemessageinterface)
for further documentation on what can be done with a `ResponseInterface`
instance.

#### 2.2.2.1 Responses generated from a Twig template

 ```php
$this->psr7Response = $this->responseFromTemplate(
    '@test/index.twig',
    [
        'name' => $name
    ],
    $title,
    $tab,
);
```

The example above gives an array of values `['name' => $name]` to twig to render
a template `index.twig` within the Schoolbox site theme (headers, footers, side
menus).

You can pass scalars and objects to twig, though you should be mindful of the
various pitfalls with the latter.

The `title` and `tab` parameters are optional: if provided, they set the
document title (as shown in the browser's title bar) and selected tab (as shown
in the top navigation).

 ```php
$this->psr7Response = $this->responseFromTemplateUnthemed(
    '@test/index.twig',
    [
        'name' => $name
    ]
);
```

The example above is the same as the first example, but does not include the
site theme in the rendering; it renders the template's content as is.

#### 2.2.2.2 JSON Responses

JSON is a common response type for APIs, and for asynchronous requests.

 ```php
$this->psr7Response = $this->responseAsJson($data);
```

The above example simply outputs `json_encode($data)`, and automatically sets
the appropriate `Content-Type` header of `application/json`.

#### 2.2.2.3 Raw Content Responses

Simple creation of output from a string might be appropriate when streaming a
file's content.

```php
$this->psr7Response = $this->responseFromContent($pdfContent)
    ->withHeader('Content-Type', 'application/pdf');
```

The above example simply outputs `$pdfContent`. Note the `withHeader` call
setting the appropriate `Content-Type`.

### 2.2.3 Redirects
Sometimes you need to redirect. You can use one of the `redirect*()` methods to
create a redirect response:

```php
$this->psr7Response = $this->redirectFound('http://www.google.com');
```

...or derive a Route instead:

```php
$this->getRouter()->generate('myModule.myRoute', [
    'id' => 1,
]));
```

> Use `psr7response` to set up twig data and also to process redirects using Routes.

## 2.3 Superglobals
We don't directly access the superglobals `$_SESSION`, `$_GET` and `$_POST` because we can't mock these out with test data and also presumes a lot about where this data came from. Instead, there are accessor methods which provide an abstraction.

> Use the abstractions to access contents of superglobals $_SESSION, $_GET and $_POST and pass those to Supporting Classes instead of accessing them directly.

## 2.3.1 Session Superglobal
Don't reference `$_SESSION` directly. This is very difficult to test and not great when on the command line. Instead, you can use `$this->getSession()->set()` and `$this->getSession()->get()` methods.

For other Superglobals, see [2.2.1 GET and POST vars](#221-get-and-post-vars)

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
