# Toolsd Reverse Proxy (dev/testing environment) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Put a plain-HTTP passthrough reverse proxy (`schoolbox-toolsd-proxy`) in front of `schoolbox-toolsd-api` in the local dev/testing docker-compose environments, so that PHP talks to toolsd through a proxy — matching the shape real deployments (which terminate TLS and may load-balance) will have — without adding any TLS/LB logic here.

**Architecture:** A new tiny Caddy-based service (`schoolbox-toolsd-proxy`), built from a new `docker/schoolbox-toolsd/proxy/` directory, sits between PHP and `schoolbox-toolsd-api`. It does a bare `reverse_proxy` passthrough on port 8080, no TLS. `tools/docker-provision.sh` is updated so `toolsd_host` points at the proxy instead of the API container directly.

**Tech Stack:** Docker Compose, Caddy 2.11.2-alpine, Bash (`tools/docker-provision.sh`).

## Global Constraints

- No TLS, authentication, or load-balancing logic in these compose files — TLS/LB belong to real deployment infrastructure and are out of scope (see `docs/superpowers/specs/2026-07-02-toolsd-reverse-proxy-design.md`).
- No changes to `library/Schoolbox/ToolsDaemon/*` (PHP client code) — the client is host-agnostic and needs no changes.
- No changes to `docker/caddy/*` or `docker/up`'s compose templating.
- Reuse `FROM caddy:2.11.2-alpine` (matches `docker/caddy/Dockerfile`'s existing pinned version).
- `schoolbox-toolsd-proxy` must NOT have a `ports:` entry — internal Docker network only, same as `schoolbox-toolsd-api` today.
- Add the service identically to both `docker/schoolbox-toolsd/dev.docker-compose.yaml` and `docker/schoolbox-toolsd/deploy.docker-compose.yaml`.

---

### Task 1: Create the proxy's Dockerfile and Caddyfile, verify the image builds and forwards traffic standalone

**Files:**
- Create: `docker/schoolbox-toolsd/proxy/Dockerfile`
- Create: `docker/schoolbox-toolsd/proxy/config/Caddyfile`

**Interfaces:**
- Produces: a Docker image that, when run with a Caddyfile mounted at `/etc/caddy/Caddyfile`, listens on `:8080` and reverse-proxies to whatever hostname:port is written in that Caddyfile. Task 2/3 mount this image's config via Compose's `configs:` mechanism at `/etc/caddy/` (matching how `docker/caddy`'s Caddyfile is mounted in `docker/base.docker-compose.yaml:30-32`).

- [ ] **Step 1: Create the Dockerfile**

```dockerfile
FROM caddy:2.11.2-alpine

EXPOSE 8080
```

Save as `docker/schoolbox-toolsd/proxy/Dockerfile`.

- [ ] **Step 2: Create the Caddyfile**

```
:8080 {
    reverse_proxy schoolbox-toolsd-api:8080
}
```

Save as `docker/schoolbox-toolsd/proxy/config/Caddyfile`.

Note: the site address is a bare port (`:8080`), not a hostname, so Caddy will
NOT attempt automatic HTTPS certificate provisioning for it — this serves
plain HTTP by default, no extra directives needed.

- [ ] **Step 3: Build the image standalone**

```bash
cd /home/amri/Documents/schoolbox
docker build -t toolsd-proxy-test docker/schoolbox-toolsd/proxy
```

Expected: build succeeds (`Successfully tagged toolsd-proxy-test:latest` or
equivalent buildkit success output).

- [ ] **Step 4: Smoke-test the image forwards HTTP, using a throwaway backend**

Run a temporary network with a fake backend and the proxy, and confirm the
proxy forwards to it:

```bash
docker network create toolsd-proxy-smoke-test
docker run -d --rm --network toolsd-proxy-smoke-test --network-alias schoolbox-toolsd-api \
  --name toolsd-smoke-backend python:3-alpine \
  python3 -m http.server 8080
docker run -d --rm --network toolsd-proxy-smoke-test --name toolsd-smoke-proxy \
  -v "$(pwd)/docker/schoolbox-toolsd/proxy/config/Caddyfile:/etc/caddy/Caddyfile:ro" \
  toolsd-proxy-test
sleep 2
docker run --rm --network toolsd-proxy-smoke-test curlimages/curl:8.11.1 \
  curl -s -o /dev/null -w '%{http_code}\n' http://toolsd-smoke-proxy:8080/
```

Expected: prints `200` (Python's `http.server` returns 200 for `GET /` by
listing the directory), proving the proxy received the request on `:8080`
and forwarded it to the backend aliased as `schoolbox-toolsd-api`.

- [ ] **Step 5: Clean up the smoke test containers/network**

```bash
docker rm -f toolsd-smoke-backend toolsd-smoke-proxy
docker network rm toolsd-proxy-smoke-test
docker rmi toolsd-proxy-test
```

- [ ] **Step 6: Commit**

```bash
git add docker/schoolbox-toolsd/proxy/Dockerfile docker/schoolbox-toolsd/proxy/config/Caddyfile
git commit -m "Add standalone toolsd reverse proxy image [SA-22679]"
```

---

### Task 2: Wire `schoolbox-toolsd-proxy` into `dev.docker-compose.yaml` and verify against the live dev stack

**Files:**
- Modify: `docker/schoolbox-toolsd/dev.docker-compose.yaml`

**Interfaces:**
- Consumes: the image built from `docker/schoolbox-toolsd/proxy/` (Task 1).
- Produces: a running `schoolbox-toolsd-proxy` container, reachable by other
  containers on the same Compose project's network at
  `schoolbox-toolsd-proxy:8080`, forwarding to `schoolbox-toolsd-api:8080`.

- [ ] **Step 1: Add the service and configs entry**

Edit `docker/schoolbox-toolsd/dev.docker-compose.yaml`: add a new service
after the `schoolbox-toolsd-flower` block (currently the last service, ending
at line 126 with `command: [src/main.py, flower]`), and a new top-level
`configs:` section (this file currently has none):

```yaml
  # Reverse proxy in front of the toolsd HTTP API.
  # A real deployment terminates TLS here and may load-balance across
  # multiple schoolbox-toolsd-api containers; in dev this is a plain HTTP
  # passthrough so the same topology is exercised locally.
  schoolbox-toolsd-proxy:
    depends_on:
      - schoolbox-toolsd-api
    build:
      context: ./schoolbox-toolsd/proxy
    configs:
      - source: toolsd-proxy
        target: /etc/caddy/

configs:
  toolsd-proxy:
    file: ./schoolbox-toolsd/proxy/config/
```

(The trailing `configs:` block is a new top-level key in the file, added
after the `services:` block — Compose merges top-level `configs:` sections
across all `-f` files the same way it merges `services:`, as already relied
on for `caddy`/`schoolbox`/`toolsd` config sources defined in
`docker/base.docker-compose.yaml:502-520`.)

- [ ] **Step 2: Validate the merged compose config parses**

```bash
cd /home/amri/Documents/schoolbox
docker/compose config schoolbox-toolsd-proxy
```

Expected: prints the resolved service definition for
`schoolbox-toolsd-proxy` with no errors (confirms YAML is valid and the
`toolsd-proxy` config source resolves).

- [ ] **Step 3: Bring the service up in the running dev stack**

The dev environment is already running (project `docker`, service names like
`docker-schoolbox-toolsd-api-1`). Bring up just the new service:

```bash
docker/compose up -d --build schoolbox-toolsd-proxy
docker/compose ps schoolbox-toolsd-proxy
```

Expected: `schoolbox-toolsd-proxy` container shows as `Up` in the `ps`
output.

- [ ] **Step 4: Verify it actually proxies requests to schoolbox-toolsd-api**

From `schoolbox-cli` (already on the same network), call one of the real
API's job-status endpoints through the proxy and directly, and confirm they
respond identically for a nonexistent job id:

```bash
docker/compose exec -T schoolbox-cli curl -s -o /dev/null -w 'direct=%{http_code}\n' \
  http://schoolbox-toolsd-api:8080/jobs/does-not-exist
docker/compose exec -T schoolbox-cli curl -s -o /dev/null -w 'proxy=%{http_code}\n' \
  http://schoolbox-toolsd-proxy:8080/jobs/does-not-exist
```

Expected: both lines print the same HTTP status code (whatever
`schoolbox-toolsd-api` returns for an unknown job id, e.g. `404` or `500` —
the exact code doesn't matter, only that `direct=` and `proxy=` match).

- [ ] **Step 5: Commit**

```bash
git add docker/schoolbox-toolsd/dev.docker-compose.yaml
git commit -m "Add schoolbox-toolsd-proxy to dev docker-compose [SA-22679]"
```

---

### Task 3: Wire `schoolbox-toolsd-proxy` into `deploy.docker-compose.yaml`

**Files:**
- Modify: `docker/schoolbox-toolsd/deploy.docker-compose.yaml`

**Interfaces:**
- Consumes: same image/config as Task 2.
- Produces: same as Task 2, for the `deploy` (testing-of-deployments)
  variant, which differs only in build context/source for the other
  services, not in how the proxy behaves.

- [ ] **Step 1: Add the identical service and configs entry**

Edit `docker/schoolbox-toolsd/deploy.docker-compose.yaml`: add the same
block after the `schoolbox-toolsd-flower` service (currently the last
service, ending at line 128 with `command: [src/main.py, flower]`):

```yaml
  # Reverse proxy in front of the toolsd HTTP API.
  # A real deployment terminates TLS here and may load-balance across
  # multiple schoolbox-toolsd-api containers; in this testing environment
  # it's a plain HTTP passthrough so the same topology is exercised locally.
  schoolbox-toolsd-proxy:
    depends_on:
      - schoolbox-toolsd-api
    build:
      context: ./schoolbox-toolsd/proxy
    configs:
      - source: toolsd-proxy
        target: /etc/caddy/

configs:
  toolsd-proxy:
    file: ./schoolbox-toolsd/proxy/config/
```

- [ ] **Step 2: Validate the merged compose config parses**

This variant is selected when `TOOLSD_BUILD_FROM_LOCAL` is falsy (see
`docker/compose:41-49`). Force it for validation only:

```bash
cd /home/amri/Documents/schoolbox
TOOLSD_BUILD_FROM_LOCAL=0 docker/compose config schoolbox-toolsd-proxy
```

Expected: prints the resolved service definition for
`schoolbox-toolsd-proxy` with no errors — same shape as Task 2's Step 2
output (this doesn't start containers, so it's safe to run without
disrupting the live dev stack, which is running the local-build variant).

- [ ] **Step 3: Commit**

```bash
git add docker/schoolbox-toolsd/deploy.docker-compose.yaml
git commit -m "Add schoolbox-toolsd-proxy to deploy docker-compose [SA-22679]"
```

---

### Task 4: Point `toolsd_host` at the proxy in provisioning

**Files:**
- Modify: `tools/docker-provision.sh:316`

**Interfaces:**
- Consumes: `schoolbox-toolsd-proxy:8080` (the service added in Tasks 2/3),
  reachable on the Compose network under that name.
- Produces: future `docker/provision` runs configure PHP's `toolsd_host` to
  route through the proxy instead of `schoolbox-toolsd-api` directly.

- [ ] **Step 1: Update the config value**

In `tools/docker-provision.sh`, change line 316 from:

```bash
    --config toolsd_host "'http://schoolbox-toolsd-api:8080'" \
```

to:

```bash
    --config toolsd_host "'http://schoolbox-toolsd-proxy:8080'" \
```

- [ ] **Step 2: Check shell syntax**

```bash
bash -n /home/amri/Documents/schoolbox/tools/docker-provision.sh
```

Expected: no output, exit code 0 (confirms the edit didn't break bash
syntax — a full `docker/provision` run reprovisions the database and is a
destructive operation on the live dev environment, so it is intentionally
not run as part of this task; it will take effect the next time someone
runs `docker/provision` from scratch).

- [ ] **Step 3: Commit**

```bash
git add tools/docker-provision.sh
git commit -m "Point toolsd_host at schoolbox-toolsd-proxy [SA-22679]"
```

---

### Task 5: End-to-end verification that the proxy carries the real toolsd API traffic shapes

**Files:** none (verification only, no code changes)

**Interfaces:**
- Consumes: `schoolbox-toolsd-proxy` (Task 2), running in the live dev
  stack.

This task directly exercises the three request shapes
`Schoolbox\ToolsDaemon\Client` sends (`library/Schoolbox/ToolsDaemon/Client.php:38-115`),
through the proxy, against the real running `schoolbox-toolsd-api`:
`POST /run`, `GET /jobs/{jobId}`, `GET /jobs/{jobId}/output`.

- [ ] **Step 1: Submit a job through the proxy**

```bash
docker/compose exec -T schoolbox-cli curl -s -X POST http://schoolbox-toolsd-proxy:8080/run \
  -H 'Content-Type: application/json' \
  -d '{"tool":"echo","args":["hello"]}'
```

Expected: a JSON response. If it includes a `jobId`, capture it for the next
steps. (If `schoolbox-toolsd-api` rejects this specific payload because
`echo` isn't a configured tool, that's fine — rerun Step 1 with
`GET /jobs/does-not-exist` style checks instead, as in Task 2 Step 4; the
point of this task is confirming the proxy transparently carries POST bodies
and arbitrary paths, not exercising toolsd's job-configuration semantics.)

- [ ] **Step 2: Query job status through the proxy, using whatever id Step 1 returned (or a placeholder id if Step 1 didn't return one)**

```bash
docker/compose exec -T schoolbox-cli curl -s http://schoolbox-toolsd-proxy:8080/jobs/<jobId>
docker/compose exec -T schoolbox-cli curl -s http://schoolbox-toolsd-api:8080/jobs/<jobId>
```

Expected: both commands return byte-identical output — proving the proxy
is a transparent passthrough for this endpoint too.

- [ ] **Step 3: Query job output through the proxy**

```bash
docker/compose exec -T schoolbox-cli curl -s http://schoolbox-toolsd-proxy:8080/jobs/<jobId>/output
docker/compose exec -T schoolbox-cli curl -s http://schoolbox-toolsd-api:8080/jobs/<jobId>/output
```

Expected: both commands return byte-identical output.

No commit for this task — it's verification only.

## Self-Review Notes

- **Spec coverage:** every component in the spec
  (`docker/schoolbox-toolsd/proxy/{Dockerfile,config/Caddyfile}`, the
  `schoolbox-toolsd-proxy` service in both compose files, the
  `tools/docker-provision.sh` config change) has a task. The spec's "no PHP
  changes" and "out of scope" items are reflected in the Global Constraints
  and are not given tasks, matching the spec.
- **Placeholder scan:** no TBD/TODO; Task 5's `<jobId>` is an explicit
  runtime-substitution placeholder for a value captured in the same task's
  Step 1, not an unresolved plan gap.
- **Type/name consistency:** `schoolbox-toolsd-proxy`, port `8080`, and the
  `toolsd-proxy` config source name are used identically across Tasks 1-4.
