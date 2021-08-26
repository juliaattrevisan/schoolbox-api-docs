# 1. Vue Container

Vue needs an `entry point` which is where the Vue framework loads. To do it in schoolbox, we'll need an Aura Module. You can set one up yourself or you can invade an existing one.

In this guide we'll use an existing one to prove that we can just *do vue* whenever we want.

Have a look in `library/Schoolbox/Greenhouse`. There's a controller and module here. It's a place where you can test twig templates in isolation, for a Greenhouse is where twigs are born.

## 1.1 Set up a Vue Container in twig town

The first thing we need to do is set up a route to our `Vue Container`. There's nothing special about this, it's just another regular old controller method and module regex.

Delete the existing route and replace it with this.

```php
$this->register('GET', '', 'getContainer', $isAdministrator);
```

This will call a controller's `getContainer` method; as usual, add that to the corresponding Controller. That controller method needs to produce data which gets forwarded to a twig template.

```php
/**
 * Load vue container for our new Vue app
 *
 * @throws \Aura\Di\Exception\ServiceNotFound
 */
public function getContainer(): void
{
    $this->getResponse()->content->set($this->buildHtmlOutput('@greenhouse/container.twig', [
        'test' => 'Successful container!'
    ]));
}
```

And in the Aura module's `templates` folder, add a twig file `container.twig`.

```html
<h1>Container for Vue initialisation</h1>
<p>Our vue component gets initialised here</p>
```

Then you can visit `https://vagrant.dev.schoolbox.cloud/greenhouse` to see it in action.

[Setup Entry](02-setup-entry.md)