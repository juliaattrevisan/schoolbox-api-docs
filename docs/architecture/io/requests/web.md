# Web Request

One point of input for requests into Schoolbox is via a standard HTTP request.

Incoming HTTP requests are handled by a standard HTTP server:
* currently, self-hosted Schoolbox instances serve requests via an
  [NGINX](https://www.nginx.com/) instance
* cloud-hosted Schoolbox instances serve requests via a
  [Caddy](https://caddyserver.com/) instance

In either case:
* if the request points to a static asset (e.g. CSS, JS, image) in the
  `www/static` folder, the server will serve that asset directly: Schoolbox does
  not handle that request.
    * Caddy instances do not serve static assets: cloud-hosted Schoolbox
      instances will always be configured to request static assets from a CDN
* if the request does not point to a static asset, then Schoolbox will handle
  the request.

If Schoolbox handles the request, it will be redirected to `www/index.php`:
there, Schoolbox will perform
[instance routing](../../configuration/instance-routing.md) so that it may
bootstrap the instance's configuration, and then run the web request using the
[web kernel](../../adapters/web-kernel.md).
