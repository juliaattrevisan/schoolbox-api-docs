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

All cursor classes are expected to implement `\Schoolbox\API\Cursor\ICursor`.

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

#### Model To Array

This translator is intended to convert a mapper's model into an array, so that
the array may be output using standard JSON encoding methods.

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
representation of an object into a model, ready to be persisted using the
model's mapper.

Instances of this translator generally extend the
`\Schoolbox\API\Translator\ArrayToModel\Base` abstract class: this class
implements several helper methods for defining a translation. Like the model to
array translator, instances should also implement `__invoke`.
