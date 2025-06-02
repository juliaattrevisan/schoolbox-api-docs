# PDF Generator

Schoolbox is able to make use of a PDF generator, in order to convert HTML pages
to downloadable PDFs. (Schoolbox uses [Gotenberg](https://gotenberg.dev/)
as its PDF generator.)

Usually, the generator will be accessed via the [task daemon](task-daemon.md):
however, it is possible for Schoolbox to access the generator directly via its
[adapter](../adapters/pdf-generator.md).
