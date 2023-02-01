# PDF Generator

Schoolbox is able to make use of a PDF generator, in order to convert HTML pages
to downloadable PDFs. (Schoolbox uses [Gotenberg](https://gotenberg.dev/)
as its PDF generator.)

> Currently, Schoolbox uses Gotenberg version 6, which is no longer actively
> maintaned: see https://gotenberg.dev/docs/6.x/introduction for documentation.

Usually, the generator will be accessed via the [task daemon](task-daemon.md):
however, it is possible for Schoolbox to access the generator directly via its
[adapter](../adapters/pdf-generator.md).
