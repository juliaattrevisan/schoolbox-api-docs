---
name: schoolbox-docker
description: "Use when Codex needs to interact with the Schoolbox Docker development environment: starting or stopping the stack, provisioning data, viewing service logs, opening a shell in a container, executing commands inside a running service, checking service state, or restarting Docker services after config changes. Triggers include requests mentioning `docker/up`, `docker/down`, `docker/provision`, `docker/logs`, `docker/exec`, `docker/ssh`, `docker/compose`, containers, services, provisioning, or Docker-based local development in this repository."
---

# Schoolbox Docker

Use the repository's `docker/*` helper scripts instead of raw `docker compose` commands unless the task specifically needs a compose subcommand that the wrappers do not expose.

Read [references/operations.md](references/operations.md) when you need the command catalog, risk levels, or examples.

## Core Rules

- Run Docker commands from the repository root.
- Prefer the wrapper scripts in `docker/`; they load the expected project configuration and match the documented workflow.
- Treat `docker/provision` as destructive. It reloads instance state and applies updates; do not run it unless the user asked for provisioning or a fresh dataset.
- Treat `docker/down -v`, `docker/clean`, and arbitrary writes inside containers as destructive.
- Prefer read-only inspection first: `docker/logs`, `docker/compose ps`, `docker/exec ...` with safe commands, then escalate to restarts or provisioning only if needed.
- Use `docker/exec` for non-interactive commands in `schoolbox-cli`.
- Use `docker/ssh` for an interactive shell or when the target container is not `schoolbox-cli`.
- When a service-specific restart is needed after config changes, use `docker/compose restart <service>...`.

## Quick Start

For the standard environment lifecycle:

1. Start the stack with `./docker/up`.
2. Provision it with `./docker/provision` only after the stack is running and only when provisioning is actually required.
3. Run project commands inside the CLI container with `./docker/exec ...` or `./docker/ssh`.
4. Inspect failures with `./docker/logs [service]` or `./docker/compose ps`.
5. Stop the stack with `./docker/down`.

## Common Tasks

### Start Or Rebuild

- Start the environment with `./docker/up`.
- Rebuild images before starting with `./docker/up --build` when image definitions changed.
- If startup fails, inspect `./docker/logs` and `./docker/compose ps` before changing anything else.

### Provision

- Run `./docker/provision` after `./docker/up` when the instance needs initial data or schema/application updates applied.
- Pass `--install-instance-only` only when the task is explicitly limited to that flow.
- Expect provisioning to restart `caddy` and `schoolbox-frankenphp`.

### Inspect Or Debug

- View aggregated logs with `./docker/logs`.
- View one service with `./docker/logs <service>`.
- List services with `./docker/compose ps`.
- Run safe non-interactive commands inside the CLI container with `./docker/exec <command>`.
- Open a shell with `./docker/ssh` or `./docker/ssh <container>`.

### Run Development Commands

- Use `./docker/exec` for commands such as `php -v`, `composer`, `npm`, `./vendor/bin/phpunit`, `./vendor/bin/phpstan`, or `npx eslint ...`.
- If the task requires an interactive shell session, use `./docker/ssh`.
- For containers other than `schoolbox-cli`, prefer `./docker/ssh <container>` over raw `docker exec`.

### Restart Services

- Restart specific services with `./docker/compose restart <service>...`.
- Use targeted restarts instead of `./docker/down` followed by `./docker/up` when only config changes need to be picked up.

## Decision Guide

- If the user wants the environment running: use `./docker/up`.
- If the user wants a fresh or updated instance: use `./docker/provision`.
- If the user wants logs or status: use `./docker/logs` or `./docker/compose ps`.
- If the user wants to run a one-off command in the CLI container: use `./docker/exec`.
- If the user wants an interactive shell or a non-default container: use `./docker/ssh`.
- If the user wants the environment stopped: use `./docker/down`.

## Safety Notes

- Do not assume provisioning is harmless just because the environment already exists.
- Do not remove volumes or wipe generated data unless the user explicitly asked for that outcome.
- If a Docker command fails because Docker permissions, AWS authentication, or registry login are missing, report the blocker clearly and only proceed with the next command once that prerequisite is resolved.
