# Custom templates outside Aura modules

> This applies only to templates that exist outside of Aura modules
> (in other words, within `templates/`).
>
> See [Custom templates for Aura modules](custom-templates-for-aura-modules.md)
> for a guide to customising templates that exist within Aura modules.

## A quick example

> An extremely common customisation is to change the layout or buttons on the 
> student profile screen, and the related search results;
> that is the example we'll use here.
>
> This example involves updating both Twig and PHPLib templates.

Switch school to MGGS, and rebuild CSS:

    tools/switch-school.sh mggs
    tools/passthru.sh grunt -vvv build.sass

Visit [http://192.168.36.10/search/user/15](http://192.168.36.10/search/user/15)
* the title will be "Import Users"

Visit [http://192.168.36.10/search?keyword=scott#all,1](http://192.168.36.10/search?keyword=scott#all,1)
* there will be a "Due Work" item inside the dropdown menu for Scott Berryhill

In your Schoolbox project:

* copy file `templates/core/user/student.twig` to `templates/mggs/user/student.twig`
* replace the new file's contents with:
```twig
{% extends '@core/user/student.twig' %}

{% block buttons_dueWork %}
{% endblock buttons_dueWork %}
```
* copy file `templates/core/user/search/results.html` to `templates/mggs/user/search/results.html`
* remove any HTML between the lines `<!-- BEGIN due_work -->` and `<!-- END due_work -->`

Visit [http://192.168.36.10/search/user/15](http://192.168.36.10/search/user/15) again
* There will be no "Due Work" button underneath Scott's profile image

Visit [http://192.168.36.10/search?keyword=scott#all,1](http://192.168.36.10/search?keyword=scott#all,1)
* there will be no "Due Work" item inside the dropdown menu for Scott Berryhill

Switch school back to demo:

    tools/switch-school.sh demo

Visit [http://192.168.36.10/search/user/15](http://192.168.36.10/search/user/15) again
* The "Due Work" button underneath Scott's profile image will display again

Visit [http://192.168.36.10/search?keyword=scott#all,1](http://192.168.36.10/search?keyword=scott#all,1)
* there will again be a "Due Work" item inside the dropdown menu for Scott Berryhill

## Technical details


### Folder layout

All templates outside of Aura modules live within `templates/`.

The default version of the template lives within `templates/core/`; if a
version lives within `templates/${config.site_location}`, it will be used
instead.


### Extending a base template

> If you're unfamiliar with Twig template extension, further reading 
  may be found within Twig's online documentation;
  [https://twig.symfony.com/doc/2.x/tags/extends.html](https://twig.symfony.com/doc/2.x/tags/extends.html).

It's preferable to create custom templates as extensions of the base template,
instead of entirely new templates; this minimises the amount of template code
that needs to be duplicated and maintained. (Of course, whether this is viable
depends on how far the school's desired customisation deviates from the base
template.)

> PHPLib templates may not be extended; they may only be overwritten completely.

Template extension outside of Aura is enabled by there being a `@core`
Twig template namespace in addition to their default empty namespace.

This `@core` namespace is exactly the same as the default
empty namespace, except that it does not include the
`templates/${config.site_location}` subfolder; this allows custom templates to
extend the core version (which will unambiguously point to the base version).
