# File Storage

Schoolbox allows for the upload of files within various contexts; for example:
- a teacher may upload a photo to their class page for their students
- a student may upload a file as part of their due work
- a superuser may upload a list of users as a CSV file

Whatever the context, all files will be stored within Schoolbox's file storage
system.

Communication with the file storage system is done via the
[file storage adapter](../../adapters/file-storage.md).

## Operation

Upon upload, Schoolbox takes a hash of the file's content; it then uses that to
determine an exact location for the file to be:
- stored
- re-fetched for either:
  - direct download via a HTTP request
  - further processing

Schoolbox may also generate transformations of some files, so that they may be
re-downloaded with a smaller file size; e.g.:
- streamable files (audio, video)
- images

These transformations are stored alongside the original file.

## Variants

The following variants of file storage exist:

- [Local](local.md)
- [AWS S3](s3.md)
