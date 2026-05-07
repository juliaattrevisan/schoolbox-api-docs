# Schoolbox Docker Operations

This skill is based on:

- `docs/ai/docker-skill.yaml`
- `docs/ai/docker-skill-tools.json`
- `docker/README.md`

## Risk Levels

- `safe`: read-only or expected day-to-day operations
- `caution`: can be slow, noisy, or restart services
- `destructive`: can reprovision data, reload instance state, or remove persisted state

## Primary Commands

| Task | Command | Risk | Notes |
| --- | --- | --- | --- |
| Start environment | `./docker/up` | safe | Builds env vars and starts the stack |
| Stop environment | `./docker/down` | caution | Supports compose args such as `-v`; treat `-v` as destructive |
| View logs | `./docker/logs [service]` | safe | Pass through to compose logs |
| Provision environment | `./docker/provision` | destructive | Loads initial data and applies updates |
| Rebuild and start | `./docker/up --build` | caution | Use after image definition changes |
| Execute command in CLI container | `./docker/exec <command>...` | caution | Safe for read-only commands; risky for write operations |
| Open shell in container | `./docker/ssh [container]` | caution | Defaults to `schoolbox-cli` |
| Show service status | `./docker/compose ps` | safe | Useful before logs or restarts |
| Restart specific services | `./docker/compose restart <service>...` | caution | Prefer targeted restarts |

## Containers And Services

Common service names visible in this repository include:

- `schoolbox-cli`
- `schoolbox-frankenphp`
- `caddy`
- `mariadb`
- `redis-cache`
- `redis-storage`
- `rabbitmq`
- `schoolbox-scheduled`
- `schoolbox-socketd`
- `schoolbox-taskd`

Confirm the actual running services with `./docker/compose ps` before acting on them.

## Usage Patterns

### Environment Boot

```bash
./docker/up
./docker/provision
```

### Inspect A Failing Service

```bash
./docker/compose ps
./docker/logs schoolbox-frankenphp -fn0
```

### Run A One-Off Command In The CLI Container

```bash
./docker/exec php -v
./docker/exec ./vendor/bin/phpstan analyze library/SomeFile.php
./docker/exec npm run typecheck
```

### Open A Shell

```bash
./docker/ssh
./docker/ssh schoolbox-frankenphp
./docker/ssh --user root schoolbox-cli
```

### Restart After Config Changes

```bash
./docker/compose restart schoolbox-cli schoolbox-frankenphp
```

## Guardrails

- Prefer wrapper scripts over raw `docker compose`.
- Treat `docker/provision` as explicit-intent only.
- Treat `docker/down -v` as destructive even though plain `docker/down` is often routine.
- Use read-only inspection before restarts or reprovisioning.
- If you need a compose subcommand that has no wrapper, use `./docker/compose ...` from the repo root.
