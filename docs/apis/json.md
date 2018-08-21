# Making Your Controller JSON-API Compatible

The objective of this documentation is to explain how to use a few utilities within the codebase
to easily turn Controller methods and Model structures into JSON representations for easy API
consumption by 3rd parties. 

Our most notable partner who this affects is Digistorm, who&mdash;in a lot of cases&mdash;scrape
our HTML output for the data they want, hoping we don't change it on them.

We can do better.

## Overview

The relevant classes are:
* `Alaress\Web\Controller\Base`
* `Alaress\Web\Request\IsJson`
* `Alaress\Json\SerializeTrait`
* `JsonSerializable`

## Making Models JSON-able

The `SerializeTrait` takes most of the pain out of implementing all of this, and you can use it in
most cases unless your Model needs to serialize in a _very_ specific way.

To implement, declare your model class as follows:

```php
use Alaress\Json\Serializable;
use Alaress\Json\SerializeTrait;
use Alaress\Json\Linkable;

class MyModel implements Serializable, Linkable
{
    use SerializableTrait;
    
    public function getDefaultJsonMap(): array
    {
        return [
            'propertyA',
            'propertyB'
        ];
    }

    public function getJsonApiLinks(): array
    {
        return [
            'self' => [ 'example.getEntity', [ 'id' => $this->getId() ] ],
        ];
    }

    public function getId() { /**/ }
    public function getPropertyA() { /**/ }
    public function getPropertyB() { /**/ }
}
```

Note: ensure that whatever is declared in the JSON map array has corresponding `public` getters.

You can now serialize this object in 2 ways

```php
// option 1: use the default map

json_encode($myModel);

/* output:
[
    'propertyA' => 'valueA',
    'propertyB' => 'valueB'
]
*/
```
```php
// option 2: specify custom decorators

json_encode($myModel->jsonSerialize(
    new \Alaress\Json\Decorator\PropertyDecorator([ 'propertyA' ]),
    new LinkDecorator($di->get('router'), $di->get('authorizer'))
));

/* output:
[
    'propertyA' => 'valueA',
    '_links' => [
        'self' => [
            'href' => '/example/models/12',
            'auth' => false
        ]
    ]
]
*/
```

By passing a custom map, you can define, for your context, how you want the nested objects to be rendered, structured
like this:

```php
$myCustomMap = [
    'propertyA',        // scalar value, rendered as-is
    'subModelB',        // is an object, but no custom map supplied; its own default map will be used
    'subModelC' => [    // is an object, but use this map rather than the default
        'propertyD',
        'propertyE' => [
            'justShowThisPropertyF'
        ],
        'propertyG'
    ]
];
```

Yes, this is both a dictionary + list ("dictlist") which is a bit of an anti-pattern, but it makes for the greatest
readability.

_Note that if a property is an `array` rather than a `Collection`, mapping won't work and it will be serialized in the
default manner._

Then, in your `Controller`, you can either leave let the defaults do their work, or you can customise the mapping
for the specific use-case.

## Making Your Controllers Use JSON

As our example, we'll set up our controller like this:

```php
use Alaress\Web\Controller\Base;
use Alaress\Web\Request\IsJson;

class MyController extends Base
{
    /**
     * @api GET /example/models
     */
    public function getList()
    {
        $data = [
            'models' => MyModelMapper::getInstance()->findAll()
        ];

        // the magic happens in here...
        $output = $this->buildContent($data);

        if ($output instanceof ThemedHtml) {
            // a little post-processing for the richest case
            $output
                ->setTitle('Models I can see')
                ->setTab('resources')
            ;
        }

        $this->response->content->set($output);
    }
}
```

Because of the way `Controller\Base::buildContent()` works, it will detect the `Accept` header and configure for
a JSON response if needed. In the trivial case, this is all you need to do; the model knows how to serialize itself,
as does the `Collection`, so there's nothing else you need to do!

But what if you want to customise the output in this case? Let's say that we actually _only_ want `MyModel::$propertyA`
coming through when we're in the context of `getList()`...

```php
use Alaress\Web\Controller\Base;
use Alaress\Web\Output\ThemedHtml;
use Alaress\Web\Request\IsJson;

class MyController extends Base
{
    /**
     * @api GET /example/models
     */
    public function getList()
    {
        $models = MyModelMapper::getInstance()->findAll();

        // manually do the check for JSON and pre-compile the JSON output
        if ((new IsJson())->isSatisfiedBy($this->request)) {
            $data['models'] = $models->jsonSerialize(
                new \Alaress\Json\Decorator\PropertyDecorator([ 'propertyA' ]),
                new LinkDecorator($this->router, $this->authorizer)
            );
        } else {
            $data['models'] = $models;
        }

        // leave this request detection in place, no need to remove it
        $output = $this->buildContent($data);

        if ($output instanceof ThemedHtml) {
            // a little post-processing for the richest case
            $output
                ->setTitle('Models I can see')
                ->setTab('resources')
            ;
        }

        $this->response->content->set($output);
    }
}
```

And voila! The only thing we needed to do was manually pre-compile the JSON array before passing that in place of the
objects directly.

## Summary

I would recommend steering away from explicit use of
* `Controller\Base::buildJsonOutput()`
* `Controller\Base::buildHtmlOutput()`
* `Controller\Base::buildUnthemedHtmlOutput()`

in your controllers unless you **absolutely** need to. Use of `Controller\Base::buildContent()` covers the vast majority
of needs.

