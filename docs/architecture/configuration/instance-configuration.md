# Instance Configuration

Each Schoolbox instance has its user-modifiable configuration stored in the
`config` table of the Schoolbox database.

Unlike the instance routing table, this configuration is intended to be able to
be changed by users.

Among other settings: the instance configuration contains configuration settings
for other input/output adapters:
* SIS database
* File storage
* Redis

Once instance configuration is loaded: depending on the source of the request,
it will be routed to either:
* [Web Kernel](../adapters/web-kernel.md)
* [CLI Kernel](../adapters/cli-kernel.md)
