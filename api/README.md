# API Specification

Schoolbox API specifications (in OpenAPI specification format) go here.

## End-User documentation

> Note: the method for delivering end-user documentation is currently not
> finalised. This is subject to change; in particular, we are looking for a
> more automated method for generating human-readable API documentation.

End-user readable API specifications are rendered using
[ReDoc](https://redocly.github.io/redoc/).

In order to view the specifications, you may serve this folder via HTTP:

```
# Use NodeJS' HTTP server to serve this folder
npx http-server api/;
```

Once the HTTP server is online, you may view the specifications in any browser:

* http://127.0.0.1:8080 for the API itself
* http://127.0.0.1:8080/example.html for an example of how to write API specs

(The URLs above presume NodeJS' HTTP server's default port.)

### Adding to the online specifications

You may either:
* add references to paths in other documents to the index spec
  [./index.yaml](./index.yaml)
  * note that `/` characters in the path's name escape to `~1`
* create a new HTML document for the new spec
  * Copy [./index.html](./index.html), and replace the `spec-url` value in the
    `<redoc>` tag with a link to the new spec
