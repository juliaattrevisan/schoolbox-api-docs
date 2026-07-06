# Toolsd Reverse Proxy (dev/testing environment)

**Date:** 2026-07-02
**Branch:** task-develop/SA-22679-toolsd-reverse-proxy
**Ticket:** SA-22679

## Problem

`schoolbox-toolsd-api` (the HTTP API for the tools daemon) is currently called
directly by PHP (`Schoolbox\ToolsDaemon\Client`) at
`http://schoolbox-toolsd-api:8080`, entirely over the internal Docker network.
There is no reverse proxy in front of it.

The motivating concern is real deployments (cloud): those will terminate TLS
in front of `schoolbox-toolsd-api`, and may run more than one `toolsd-api`
container behind that proxy. However, the docker-compose files under
`docker/schoolbox-toolsd/` (`dev.docker-compose.yaml`,
`deploy.docker-compose.yaml`) are **not used for real deployments** — both are
for local development/testing only (per their own header comments). TLS
termination and multi-replica load-balancing for real deployments are handled
entirely by deployment infrastructure outside this repo, and are out of scope
here.

## Goal

Introduce a reverse proxy in front of `schoolbox-toolsd-api` in the dev and
local "deploy" (testing-of-deployments) docker-compose environments, so that
this topology — PHP talking to toolsd through a proxy rather than directly —
exists and is exercised locally, matching the shape real deployments will
have. No TLS, authentication, or load-balancing logic is needed in these
compose files: the proxy just needs to be present and pass requests through.

## Chosen Approach

Add a new `schoolbox-toolsd-proxy` service to both
`docker/schoolbox-toolsd/dev.docker-compose.yaml` and
`docker/schoolbox-toolsd/deploy.docker-compose.yaml`, built from a minimal
Caddy image (mirroring the existing `docker/caddy/Dockerfile` pattern), doing
a bare HTTP passthrough to `schoolbox-toolsd-api:8080`. Point
`toolsd_host` (set in `tools/docker-provision.sh`) at the new proxy instead of
directly at `schoolbox-toolsd-api`.

This was preferred over:
- **Extending the existing root `docker/caddy` container** with an
  internal-only listener for toolsd — this would couple toolsd's proxy
  concern to the public-facing Caddy's config and the `docker/up`/`docker/compose`
  templating scripts, which are owned by the main app bundle.
- **Adding TLS and multi-replica load-balancing now** — deferred, since these
  compose files aren't used for real deployments; adding that machinery here
  (self-signed cert generation, CA distribution to consumer containers,
  `Client.php` verify-option plumbing) would be complexity with no real
  benefit, and would need to be redesigned anyway once real deployment
  infrastructure defines how TLS/LB actually works there.

## Components

### `schoolbox-toolsd-proxy` service

Added identically to both `dev.docker-compose.yaml` and
`deploy.docker-compose.yaml` in `docker/schoolbox-toolsd/`:

- Build context: new `docker/schoolbox-toolsd/proxy/` directory.
- `docker/schoolbox-toolsd/proxy/Dockerfile`:
  ```dockerfile
  FROM caddy:2.11.2-alpine

  EXPOSE 8080
  ```
- Caddy config mounted via the existing `configs:` mechanism (matching how
  `docker/caddy`'s Caddyfile is mounted in `base.docker-compose.yaml`), at
  `docker/schoolbox-toolsd/proxy/config/Caddyfile`:
  ```
  :8080 {
      reverse_proxy schoolbox-toolsd-api:8080
  }
  ```
  No `tls`/`auto_https` directives — since the site address is a bare port
  (not a hostname), Caddy serves this as plain HTTP without attempting
  certificate provisioning.
- `depends_on: schoolbox-toolsd-api` (default `service_started` condition,
  same as how `schoolbox-toolsd-celery` depends on services without a
  healthcheck).
- No `ports:` entry — reachable only from other containers on the Docker
  network, same as `schoolbox-toolsd-api` today.

### `tools/docker-provision.sh`

Change the `toolsd_host` config value (currently
`'http://schoolbox-toolsd-api:8080'`) to `'http://schoolbox-toolsd-proxy:8080'`,
so local dev/testing actually routes through the new proxy rather than
bypassing it.

### PHP (`library/Schoolbox/ToolsDaemon/`)

No changes. `Client.php` and `ClientFactory.php` are host-agnostic — they use
whatever `toolsd_host` resolves to. Since the new URL is still plain HTTP with
the same path shape (`/run`, `/jobs/{id}`, `/jobs/{id}/output`), nothing in
the client needs to change.

## Out of Scope

- `schoolbox-toolsd-celery` — communicates with Redis directly
  (`TOOLSD_BROKER`/`TOOLSD_BACKEND`), not HTTP; unaffected.
- `schoolbox-toolsd-flower` — already separately published
  (`TOOLSD_FLOWER_PUBLISHED_PORT`) for browser-based monitoring; unaffected.
- TLS termination, self-signed/internal CA distribution, and multi-replica
  load-balancing — these belong to real deployment infrastructure, not these
  dev/testing compose files.
- Changes to the public-facing `docker/caddy` container or `docker/up`'s
  compose templating.

## Verification

1. `docker/up` (or `docker/compose up -d --build`) brings up
   `schoolbox-toolsd-proxy` cleanly alongside `schoolbox-toolsd-api`.
2. From within another container on the network (e.g. `schoolbox-cli`),
   `curl http://schoolbox-toolsd-proxy:8080/jobs/<id>` reaches
   `schoolbox-toolsd-api` and returns the same response as calling
   `schoolbox-toolsd-api:8080` directly.
3. A PHP flow that exercises `toolsDaemon.client` (e.g. the
   `clearExternalCache`/`setExternalCacheTtl` admin settings jobs in
   `library/Schoolbox/Admin/Settings/Controller.php`) succeeds end-to-end
   after `toolsd_host` is repointed at the proxy.

## Success Criteria

- `schoolbox-toolsd-proxy` starts and successfully proxies requests to
  `schoolbox-toolsd-api` in both the `dev` and `deploy` (testing) compose
  configurations.
- No behavioural change from the application's perspective — job
  submission/status/output endpoints work exactly as before, just routed
  through the new proxy.
- No PHP application code changes required.
