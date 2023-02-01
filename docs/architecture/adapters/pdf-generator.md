# PDF Generator Adapter

Communication with the PDF generator is done via Gotenberg's PHP client:
https://github.com/thecodingmachine/gotenberg-php-client.

> Note that this is the client for Schoolbox's currently used Gotenberg version
> 6: https://github.com/gotenberg/gotenberg-php is the newer version of the
> client.

The PDF generator has these supporting classes:
- `\Schoolbox\Gotenberg\RequestFactory`: a class for building requests to
  Gotenberg, augmented with the information which Schoolbox and its Gotenberg
  instance require in order to communicate
- `\Schoolbox\Application\CliTool\PdfGenerator\App`: a base class for CLI apps
  which need to generate PDFs
- `\Schoolbox\Application\CliTool\PdfGenerator\SourceBundle`: a data structure
  for storing the files which should be converted to a PDF

## Instantiation

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \TheCodingMachine\Gotenberg\Client $pdfGenerator */
$pdfGenerator = $di->get('pdfGenerator');
```

## Usage

```php
/** @var \TheCodingMachine\Gotenberg\Client $pdfGenerator */

// Schoolbox's Gotenberg instance requires an API key to access:
// this is stored in config setting `gotenberg_api_key`
$requestFactory = new \Schoolbox\Gotenberg\RequestFactory($gotenbergApiKey);
$request        = $requestFactory->buildUrlRequest(
    $url,
    $waitDelay,
    $waitTimeout,
    // Session ID is required in order to authorize user making the request
    $phpSessionId,
);
$pdfGenerator->store($request, $tempFilename);
```
