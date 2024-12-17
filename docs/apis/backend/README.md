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
### Translators
#### Model To Array
#### Array To Model
