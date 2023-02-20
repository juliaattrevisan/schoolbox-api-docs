# PDF Generator Adapter

Communication with the PDF generator is done via Gotenberg's PHP client:
https://github.com/gotenberg/gotenberg-php.

The PDF generator has these supporting classes:
- `\Schoolbox\Gotenberg\Client`: a class for building requests to Gotenberg,
  augmented with the information which Schoolbox and its Gotenberg instance
  require in order to communicate
- `\Schoolbox\Application\CliTool\PdfGenerator\App`: a base class for CLI apps
  which need to generate PDFs
- `\Schoolbox\Application\CliTool\PdfGenerator\SourceBundle`: a data structure
  for storing the files which should be converted to a PDF

## Instantiation

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Schoolbox\Gotenberg\Client $pdfGenerator */
$pdfGenerator = $di->get('pdfGenerator');
```

## Usage

```php
/** @var \Schoolbox\Gotenberg\Client $pdfGenerator */

// Session ID is required in order to authorize user making the request
$request = $pdfGenerator->buildHtmlToPdfConverter($phpSessionId)
    ->waitDelay(sprintf('%ds', $waitDelay))
    ->url($url);
$response = $pdfGenerator->send($request);
```
