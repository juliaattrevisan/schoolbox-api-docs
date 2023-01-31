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
  - file storage records are uniquely identified by a hash of their contents
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
- `\Alaress\Image\ITransform`: an interface for defining rules for transforming
  images
- `\Alaress\Image\Processor`: a class for creating and saving image transforms
- `\Alaress\Image\ProcessorFactory`: a factory for creating processors

## Instantiation

```php
/** @var \Psr\Container\ContainerInterface $di */

// Creation of the file receiver
/** @var \Schoolbox\Resource\Storage\Receiver $filesystemReceiver */
$filesystemReceiver = $di->get('filesystem.receiver');

// Creation of the file system storage proxy
/** @var \Schoolbox\Resource\Storage\FilesystemProxy $filesystemStorageProxy */
$filesystemStorageProxy = $di->get('filesystem.storageProxy');

// Creation of the image transform processor factory
/** @var \Alaress\Image\ProcessorFactory $filesystemImageProcessorFactory */
$filesystemImageProcessorFactory = $di->get('filesystem.imageProcessorFactory');

// Creation of the Flysystem instance for the file storage system.
// This usually won't be accessed directly at all, as direct access to the
// storage filesystem is only necessary when manipulating file content directly.
// `filesystem.storageProxy` will usually be used instead.
/** @var \League\Flysystem\Filesystem $filesystemStorage */
$filesystemStorage = $di->get('filesystem.storage');

// Creation of the Flysystem instance for working with temp files.
// This usually won't be accessed directly at all, as only `filesystem.receiver`
// needs to work directly with the temp filesystem.
/** @var \League\Flysystem\Filesystem $filesystemTemp */
$filesystemTemp = $di->get('filesystem.temp');
```

## Usage

```php
/** @var \Schoolbox\Resource\Storage\Receiver $filesystemReceiver */

// Copy a file from a temp location to storage
$storage = $filesystemReceiver->accept($filename, $tempLocation);

// Copy a file from a string or file pointer to storage
$storage = $filesystemReceiver->acceptStream($filename, $fileContents);

// Copy a file from $_FILES['file'] to storage
$storage = $filesystemReceiver->acceptStream($_FILES['file']['name'], $_FILES['file']['tmp_name']);
```

```php
/** @var \Schoolbox\Resource\Storage\FilesystemProxy $filesystemStorageProxy */

// Read a storage file's pointer
$filePointer = $filesystemStorageProxy->readStream($storage);

// Write a file pointer's contents to a storage file, updating its hash
$filesystemStorageProxy->writeStream($storage, $filePointer);
```

```php
/** @var \Alaress\Image\ProcessorFactory $filesystemImageProcessorFactory */

// Build an image transform processor, then stream it to output
$processor = $filesystemImageProcessorFactory->buildForStorage(
    $storage,
    $key,
    $transform,
);
$processor->load()->stream();
```
