# HTTP Responses

HTTP responses are generally generated within controllers, and set as the
body of the HTTP response.

Responses will be generated differently depending on:
- the type of content
- the type of controller

## Common Types

### HTML

HTML content will be generated differently depending on the controller which
handles the HTTP request:
- Aura controllers will use [Twig](twig.md)
- Sitepage controllers will use [PHPLib](phplib.md)

### JSON

JSON content is generated differently in Aura and Sitepage controllers: the key
difference is that output is set into the `psr7Response` object in Aura, whilst
Sitepage outputs to stdout directly.

#### Aura

```php
class MyController extends \Alaress\Web\Controller\Base
{
    public function myAction(): void
    {
        $this->psr7Response = $this->responseAsJson($data);
    }
}
```

#### Sitepage

```php
require_once SITE_root . '/conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();

header('Content-Type: application/json');
echo json_encode($data);
```

### Binary

Likewise: binary content is set into the `psr7Response` object in Aura, whilst
Sitepage outputs it directly.

#### Aura

```php
class MyController extends \Alaress\Web\Controller\Base
{
    public function myAction(): void
    {
        $this->psr7Response = $this->responseFromContent($content)
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Length', strlen($content))
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
        ;
    }
}
```

#### Sitepage

```php
require_once SITE_root . '/conf/core/require.php';

$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

header('Content-Type: application/pdf');
header('Content-Length: ' . strlen($content));
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $content;
```
