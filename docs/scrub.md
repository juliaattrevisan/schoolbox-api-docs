# Data sanitisation, escaping dangerous content
There are various philosophies regarding approach, but the one we use here is: we accept whatever the end user supplies (even if it's dangerous html/javascript content etc) and we "defuse" or colloquially, `scrub`, rendering this content harmless when outputting to the user.

This sounds kind of reckless and counterintuitive if you think of it from a safety-first whitelist perspective where you don't want to keep invalid data, but it does provide some benefits:
* You get to keep a sample of an attempted attack, for further examination.
* You don't need to mutilate end user's content into a "sane" or acceptable format before inserting it into your db. Such mutilations range from bearable to unusable and are often one-way. Acceptable data is sometimes inadvertently mutilated and you need to update your whitelist to accept it.
* Some data is acceptable in one output context, but not another e.g. html vs csv. If you mutilated the data before saving it, you can't always convert between multiple output formats in a meaningful/accurate manner.

You can read a short powwow here: https://security.stackexchange.com/questions/95325/input-sanitization-vs-output-sanitization

We "defuse" client input by using HTMLPurifier. You can have a read about it here: http://htmlpurifier.org/ but don't stress too much about this. We've made a simplified interface for humans to use without having to burden yourself with the nitty-gritty implementation details because those are repugnant and third-world.

You'll see `Scrub` in a lot of old schoolbox controllers revolving around phplib: `$output = Scrub::text($input);` and `$output = Scrub::html($input);`

The first corresponds to "defusing" bad content for what should have been text-only data (csv, input fields, etc). If $input happened to contain html/javascript, etc. they can be rendered harmlessly as a plain text string.

The second corresponds to "defusing" bad content for what should have been html/javascript data. This renders an html fragment in a safe way and filters away malignant js. This is used when outputting ckeditor contents, for example - we don't know the contents of what end users put into their ckeditor fields; while we want to keep the safe html parts, we don't want the unsafe javascript to execute. 

HTMLPurifier has its own logic to determine what's safe and what isn't, but that goes beyond the scope of this rundown. We can customise it a bit to allow/disallow styles, attributes, classes, tags, etc in various ways. For now, don't worry too much about this

This is why you'll see in various phplib-based places, this usage:

```php
<?php
$model = new Model();
$model->setTitle($_POST['title']); // raw POST data
$model->setDescription($_POST['description']); // raw POST data
$modelMapper->save($model);  // shove it into the db
```

```php
<?php
$this->setVar([
    'TITLE' => Scrub::text($model->getTitle());  // render title as text-only
    'DESCRIPTION' => Scrub::html($model->getDescription()); // render description as html-capable
]);
```
Twig-based locations are a bit more advanced - it does some stuff for you. However, use the `| purify` filter to achieve the same as `Scrub::html`. A twig equivalent to the above would be something like this:

```twig
{{ model.title }}
{{ model.description | purify }}
```

## Scrub methods
- `html`: Keeps existing HTML formatting while stripping out potentially harmful Javascript. Useful for content from CKEditor or any source where user-specified HTML formatting is possible.
- `text`: Escapes HTML and produces plain text output, including HTML tags as plain text.
- `html2text`: Produces plain text output with any HTML tags completely removed. Useful for truncated text where HTML formatting would be broken (such as news blurbs generated from the news body).
- `xml`: Sanitises data for output as XML
- `csv`: Sanitises data for output as CSV
- `smartQuotes`: Converts smart quotes to ordinary quotes

## Try it for yourself
You can set up an example specimen to try it out. In www, create a `test.php` file:

```php
<?php
$kernel = require_once(dirname(__FILE__) . '/../library/Bootstrap.php');

$html = "<div style='width:100%;height:100px;border:10px solid #f00;background-color:#00a;text-align:center'><script type='text/javascript'>window.alert('fake news!');</script><span style='color:#fff'>Repeal Obamacare</span></div>";

$this->setFile(TEMPLATE_content, 'test.html');
$this->setVar([
    'CONTENT' => Scrub::text($html),
]);
```

In templates/core, create an html file:

```html
{CONTENT}
```

Now visit http://192.168.36.10/test to see the outcome. Change `Scrub::text` to `Scrub::html` and even try removing it altogether to see what the effects are with respect to the input contents.

## Wrong usage + repair
In some archaic places around schoolbox, Scrub will have been mistakenly used on input instead. As explained previously, this mutilates the input and you thus have to fiddle with it on output in a nonstandard manner. Therefore, to repair this situation you'll need to do a couple of things:

* The input shouldn't be scrubbed.
* The output should be scrubbed.
* There is usually no reason to be dealing with htmlentities, html_special_chars, etc. If you're typing this, stop and think