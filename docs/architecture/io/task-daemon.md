# Task Daemon

The Schoolbox task daemon (a.k.a. taskd) is a service developed by Schoolbox
which may run tasks on demand asynchronously.

Currently: its main responsibility is to asynchronously send HTTP requests to
a Schoolbox instance, and compile those results into a ZIP file (converting
downloaded HTML pages to PDFs using the [PDF generator](pdf-generator.md)).

The task daemon code base is stored in https://github.com/alaress/taskd.

Communication with the Redis cache is done via the
[Task daemon adapter](../adapters/task-daemon.md).
