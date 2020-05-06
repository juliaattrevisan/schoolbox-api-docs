# API Specification

Schoolbox API specifications (in OpenAPI specification format) go here.

## End-User documentation

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
