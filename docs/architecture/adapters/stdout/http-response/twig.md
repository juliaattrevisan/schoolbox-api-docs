# Twig

Aura controllers will use the [Twig](https://twig.symfony.com/) template engine
to generate HTML content.

The [MVC View](../../../../mvc/06-view.md) docs are a good place to learn more
detailed information.

## Instantiation

Usually, developers will not need access to the Twig environment directly: it is
available within all Aura controllers.

It does however exist in the DI container:

```php
/** @var \Psr\Container\ContainerInterface $di */
/** @var \Twig\Environment $templateEngine */
$templateEngine = $di->get('templateEngine');
```

## Usage

The Twig template engine is accessible within controllers.

See [Module definitions](../../modules.md#definition) for the recommended folder
layout.

### Controller Action

```php
class MyController extends \Alaress\Web\Controller\Base
{
    public function myAction(): void
    {
        $this->response = $this->responseFromTemplate(
            '@myModule/template.twig',
            $data,
            $title,
            $tab,
        );
    }
}
```

### Twig Template

In addition to Twig's standard capabilities, templates in Schoolbox allow access
to these globals:

#### Filters
- `purify`: sends output through HTMLPurifier
- `html2text`: strips tags from HTML content
- `linkify`: converts links in text to clickable `<a>` tags
- `markdown`: converts Markdown text to HTML
- `cutSentence`: truncates text, attempting to preserve words where possible
- `bytes`: converts a number of bytes to a human-readable size
- `column`: extracts a [column](https://www.php.net/array_column) from a
  collection
- `dayjsFormat`: converts a
  [date format](https://www.php.net/manual/en/datetime.format.php) to a format
  which [Day.js](https://day.js.org/) understands
- `ireplace`: case-insensitive string replacement

#### Globals
- `config`: the instance's
  [configuration](../../../configuration/instance-configuration.md)
- `locale`: localisations of common terms
- `dateFormats`: the instance's date formats (see `\Alaress_Date_Locale_Config`)
- `NOW`: the current time
- `USER`: the currently authenticated user
- `CDN_PREFIX`: the host and path via which requested
  [static assets](../../../io/requests/web.md) are served
- `RETURN_URL`: reference to the encoded last visited and current URL:
  - `RETURN_URL.name`: the common query string key in which the encoded URL is
    passed
  - `RETURN_URL.current`: the current URL, encoded
  - `RETURN_URL.previous.decoded`: the previous URL in the query string, decoded
  - `RETURN_URL.previous.encoded`: the previous URL in the query string, encoded

#### Functions
- `routeLookup`: convert a route name to a path
- `isRouteAuthorized`: is the current user authorized to access a path?
- `routeLookupIfAuthorized`: if authorized, return path: otherwise empty

See [Twig's documentation](https://twig.symfony.com/doc/3.x/) for further
information on Twig's syntax and capabilities.
