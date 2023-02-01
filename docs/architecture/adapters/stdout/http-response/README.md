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
difference is that output is set into the `response` object in Aura, whilst
Sitepage outputs to stdout directly.

#### Aura

```php
class MyController extends \Alaress\Web\Controller\Base
{
    public function myAction(): void
    {
        $this->response->content->set($this->buildJsonOutput($data));
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

Likewise: binary content is set into the `response` object in Aura, whilst
Sitepage outputs it directly.

#### Aura

```php
class MyController extends \Alaress\Web\Controller\Base
{
    public function myAction(): void
    {
        $this->response->headers->set('Content-Type', 'application/pdf');
        $this->response->headers->set('Content-Length', strlen($content));
        $this->response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $this->response->content->set($this->buildFileOutputFromString($content));
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
