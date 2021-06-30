# Custom Twig templates for Aura modules

> This applies only to templates that exist within of Aura modules
> (in other words, within `library/` sub-folders).
>
> See [Custom templates outside Aura modules](custom-templates-outside-aura-modules.md)
> for a guide to customising templates that exist outside of Aura modules.

## A quick example

> Aura supports only Twig templates; it does not, not will it ever, support
> PHPLib templates.

Switch school to MGGS:

    tools/switch-school.sh mggs

Visit [http://192.168.36.10/adminv2/import/user](http://192.168.36.10/adminv2/import/user)
* the title will be "Import Users"

In your Schoolbox project:

* create file
  `library/Schoolbox/Import/templates/adminImportUser/custom/mggs/upload.twig`
  * this template is within the `@adminImportUser` Twig namespace; we'll need
    to know that when we define our new template to extend another template
* insert the following content:
```twig
{% extends '@adminImportUser:core/upload.twig' %}

{% block title %}
    MGGS Import Users
{% endblock %}
```

Visit [http://192.168.36.10/adminv2/import/user](http://192.168.36.10/adminv2/import/user) again
* The title will be "MGGS Import Users"

Switch school back to demo:

    tools/switch-school.sh demo

Visit [http://192.168.36.10/adminv2/import/user](http://192.168.36.10/adminv2/import/user) again
* The title will be "Import Users" again

Congratulations, you have created your first custom Twig template for an Aura
module! Hopefully you will never have to do this, but occasions do occur when
schools require template customisation.


## Technical details


### Folder layout

School-specific templates are stored in the `custom/${config.site_school_code}`
subfolder of the module's template folder, as configured in the `Module`
class' `initTemplatePaths` method.

Usually, this will be the `templates/` subfolder of the module, as shown below:

    Module/
        Module.php
        Controller.php
        templates/
            base.twig
            subtemplate/
                base.twig
            custom/
                mggs/
                    base.twig     # MGGS override for base.twig
                    subtemplate/
                        base.twig # MGGS override for subtemplate/base.twig


### Extending a base template

> If you're unfamiliar with Twig template extension, further reading
  may be found within Twig's online documentation;
  [https://twig.symfony.com/doc/2.x/tags/extends.html](https://twig.symfony.com/doc/2.x/tags/extends.html).

It's preferable to create custom templates as extensions of the base template,
instead of entirely new templates; this minimises the amount of template code
that needs to be duplicated and maintained. (Of course, whether this is viable
depends on how far the school's desired customisation deviates from the base
template.)

Template extension is enabled by Aura modules defining a `@${namespace}:core`
Twig template namespace in addition to their default `@${namespace}` namespace.

This `@${namespace}:core` namespace is exactly the same as the default
`${namespace}` namespace, except that it does not include the
`custom/${config.site_school_code}` subfolder; this allows custom templates to
extend the core version (which will unambiguously point to the base version).
