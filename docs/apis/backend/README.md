# Anatomy of an API backend

> If you haven't yet followed the [MVC guide](../../mvc/readme.md), you should
> follow that before starting here: that guide will describe the process of
> building modules, controllers, mappers and models.

API backends are implemented in the same framework as standard MVC backends: the
key difference is in how responses are rendered, and in how request data is
parsed.

Basically: API backends will replace the view component (written in Twig
templates) with a JSON representation of a model.

As a developer you can expect the following specifics with an API backend.

## Module

Modules will be much the same as the standard MVC module, with a few differences
to note:
- there's a greater diversity of HTTP methods in use for routes: instead of
  using only `GET` and `POST`, `PUT`/`PATCH`/`DELETE` are also used
- there's no `initTemplatePaths` override, because there are no Twig templates

## Controller

Controllers will also be much the same as they are in the standard MVC module.
The key difference is that since there's no templates to render, content is
output as JSON instead of as HTML.

```php
$this->response = $this->responseAsJson($data);
```

In addition, if the request body is supplied then it is accessed with the
`getParsedBody` method of the request:
```php
$requestBody = $this->request->getParsedBody();
```

> Note that in order for `getParsedBody` to be parsed as an array, the
> `Content-Type: application/json` header must be set in the request if passing
> a JSON-encoded request body.
>
> This method should also work if the data is sent as a form submission
> (e.g. with the `application/x-www-form-urlencoded` or `multipart/form-data`
> `Content-Type` headers), as `getParsedBody` will contain the contents of the
> `$_POST` superglobal.

By convention, controller method names are derived from:
- the HTTP method
- `list` if the method works with a collection, `item` if the method works on a
  single item

## Supporting Classes

The key difference between API and MVC backends is in the supporting classes:
API backends require a different set of supporting classes.

### Cursors

The purpose of Cursor classes is to paginate a list of results. (Pagination is
necessary for efficiency: attempting to return an entire large result set will
cause unnecessary compute and network load.)

The cursor value represents a pointer to the next page of results; in Schoolbox
implementations, it contains an encoded reference to the last item in the result
set, so that the cursor knows how to formulate a query which will only retrieve
items after it in the result set.

All cursor classes are expected to implement `\Schoolbox\API\Cursor\ICursor`:
there also exists a standardised `\Schoolbox\API\Cursor\MapperCursor` which
cursors queries to a mapper.

#### Alternative: limit/offset pagination

As an alternative to the complexity involved in implementing cursors, the
developer may choose to implement traditional limit/offset based pagination.
https://medium.com/@oshiryaeva/offset-vs-cursor-based-pagination-which-is-the-right-choice-for-your-project-e46f65db062f
provides a discussion on the pros and cons of both approaches; in summary:
- cursor pagination is more efficient with large data sets
- cursor pagination avoids returning duplicate results when new data is added
- limit/offset pagination is simpler to implement and understand

### Translators

The purpose of Translator classes is to convert object representations from one
format to another.

Both the model to array and array to model translators are expected to have
minimal external dependencies, and should thus be able to be covered by unit
tests.

#### Model To Array

This translator is intended to convert a mapper's model into an array, so that
the array may be output using standard JSON encoding methods in the HTTP
response body.

There's no standard interface for these classes, but by convention they
implement the magic `__invoke` method, thus making them callable and thus usable
in `array_map` and `\Schoolbox\Collection::map`.

> Why not use the standard `JsonSerializable` interface?
>
> Separating the translation into a separate class keeps view level logic out of
> the model itself.
>
> Also, in some contexts different array representations of the same object are
> required: for example, a user object retrieved directly from its API could be
> expected to include all fields, whilst a user retrieved as the author of an
> article via the news API only requires a subset of those fields.
>
> Also, many APIs return a set of relevant URLs as part of the metadata of the
> objects they return, so that API consumers may act upon the object via those
> URLS (see https://en.wikipedia.org/wiki/HATEOAS): these URLs require knowledge
> of the routes which have been set up in Schoolbox, which does not need to be
> known by models.

#### Array To Model

This translator converts in the opposite direction: it converts an array
representation of an object (as retrieved from the HTTP request body) into a
model, ready to be persisted using the model's mapper.

Instances of this translator generally extend the
`\Schoolbox\API\Translator\ArrayToModel\Base` abstract class: this class
implements several helper methods for defining a translation. Like the model to
array translator, instances should also implement `__invoke`.

### Validation

There's not yet a defined process for validating input data.

It's possible to validate a model at save time using its mapper's pre-save
callback: if the value to be saved is invalid, it should raise a
`RuntimeException` instance. It's also possible to write custom validation
classes, which raise `RuntimeException` instances if their validation fails.

In either case: if a `RuntimeException` is uncaught, the response will be an API
Problem compliant JSON document:
see https://datatracker.ietf.org/doc/html/rfc7807.

If you wish to further control the response, you can use `ApiProblem` directly:
```php
// Create an ApiProblem object
$problem = (new \Crell\ApiProblem\ApiProblem())
    ->setTitle('An error occurred')
    ->setStatus(400);

// For example, you may wish to set custom fields
$problem['custom'] = 'Custom field';

// And then create a JSON response
$this->response = $this->responseAsJson($problem->asArray())
                       ->withStatus($problem->getStatus());
```

## Running the example

### Setup

First, copy this example and the one in `../../mvc/example` to the base
Schoolbox folder:

```shell
# in the base Schoolbox folder:
cp -R docs/apis/backend/example/* .
cp -R docs/mvc/example/* .
```

And then refresh the module cache:
```shell
vagrant ssh
cd /usr/share/schoolbox
tools/module/refresh-cache.php
```

And create a table for the mapper:

```sql
create table example
(
    id         int auto_increment primary key,
    user_id    int unsigned not null,
    created_at datetime     not null,
    updated_at datetime     not null,
    deleted_at datetime     null,
    constraint example_user_id_fk
        foreign key (user_id) references user (id)
);
```

### Viewing the example

Visit https://vagrant.dev.schoolbox.cloud as a logged-in user.

Then, you may use the following API calls in the JS console, and view their
results:

```js
// Default headers
headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
}

// Create an item
post = await fetch(
    '/example/api',
    {
        method: 'POST',
        body: JSON.stringify({
            "user": {
                "id": 1
            }
        }),
        headers: headers,
    },
);

// Update an item:
// * copy its update link
// * copy the response from the create request, and change its user's ID.
newItem = await post.json();
newItem.user.id = 2;
fetch(
    newItem._links.update,
    {
        method: 'PUT',
        body: JSON.stringify(newItem),
        headers: headers,
    },
);

// Delete the item:
fetch(
    newItem._links.delete,
    {
        method: 'DELETE',
        headers: headers,
    },
);

// Create a whole bunch of items, so that the cursor may be tested:
for (i = 0; i < 20; i++) {
    fetch(
        '/example/api',
        {
            method: 'POST',
            body: JSON.stringify({
                "user": {
                    "id": 1
                }
            }),
            headers: headers,
        },
    );
}

// Get items
get = await fetch(
    '/example/api',
    {
        method: 'GET',
        headers: headers,
    },
);
response = await get.json();

// Check the IDs in the response

get = await fetch(
    '/example/api?cursor=' + response.metadata.cursor.next,
    {
        method: 'GET',
        headers: headers,
    },
);
response = await get.json();

// Check the IDs in the response: they ought to be the next page of results
```

### Running tests

Run the tests in `library/Schoolbox/Example` either in your IDE, or via the CLI:
command line:
```shell
# in Vagrant
vendor/bin/phpunit library/Schoolbox/Example
```

### Teardown

Once done, clean up:
```shell
rm conf/modules/Example.php
rm conf/modules/ExampleApi.php
rm -rf library/Schoolbox/Example/
```

```sql
drop table example;
```
