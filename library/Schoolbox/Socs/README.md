# SOCS

SOCS is a third-party service for managing sports fixtures, teams and results.

This integration mirrors the Clipboard importer pattern:

- A CLI tool (`Schoolbox\Socs\Cli\ImporterTool`) that acquires the lock and reads config
- A factory (`Schoolbox\Socs\Factory`) that wires mappers, HTTP API and strategies
- Small strategy classes (`IStrategy::import()`) for fixtures, teams and results
- Optional prepare/cleanup strategies that run before/after the main import steps

The SOCS HTTP client tracks per-URL usage and logs a summary via a final strategy.
