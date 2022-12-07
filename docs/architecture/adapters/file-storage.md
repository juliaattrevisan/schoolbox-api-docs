# File Storage Adapter

Regardless of the file storage variant which Schoolbox makes use of:
communication with the underlying file system is performed via an adapter, which
abstracts the differences between filesystems.

This adapter uses the [Flysystem](https://flysystem.thephpleague.com/) library
as its abstraction layer.

The adapter's code is stored within the `\Schoolbox\Resource\Storage` namespace:
its primary classes are:
- `\Schoolbox\Resource\Storage\Mapper`: a [Mapper](mappers.md) for the table
  which stores file storage records
- `\Schoolbox\Resource\Storage\Model`: an object representation of a single file
  storage record
- `\Schoolbox\Resource\Storage\Receiver`: for upload of a file stream or
  contents to storage
- `\Schoolbox\Resource\Storage\FilesystemProxy`: a wrapper around Flysystem
  which works with `\Schoolbox\Resource\Storage\Model` objects

The adapter also makes use of these classes for generating transforms of
files in the filesystem:
- `\Schoolbox\Video\QueueMapper`: a mapper for references to transformed audio
  and video
- `\Schoolbox\Video\Queue`: an object representation of an audio/video transform
  and its status; also contains methods for generating the transforms
- `\Schoolbox\Resource\Storage\Transform\Mapper`: a mapper for references to
  transformed images
- `\Schoolbox\Resource\Storage\Transform\Model`: an object representation of an
  image transform
- `\Alaress\Image\ITransform`: an interface for performing transforms on images
