# Getting Started
Assuming that you've got your workstation and accounts all set up, we'll get started on your first foray into the wilderness of schoolbox. You'll find the app an operational and philosophical melting pot - a variety of ideas and experiments that somehow live on.

This guide will touch on two main philosophies that you'll inevitably encounter in the app. By the end of this, you will:
* Have recalled some basic principles of keeping your stuff organised
* Have explored some noteworthy folders in the app
* Have seen the Handler Paradigm of doing things
* Have seen the Aura Paradigm of doing things
* Have tried building your first controller(s)

# A Quick Recap
Let's address a few precepts before we continue. None of these are particularly exciting or exotic; chances are you'll have encountered some of these already. I've given you the informal elevator pitch on each. I've also provided some helpful links that explain more, if you feel like venturing into a world of self-important literature.

## Single point of control
Changing behaviour in one place changes it everywhere it's used, resulting in consistent behaviour across the system. There's nothing worse than seeing multiple different behaviours that should be the same and having to decide between them...except maybe maintaining them. Examples:
* css stylesheets
* php abstract classes, traits and/or includes
* constants (providing you use them...)

http://www.ifsq.org/single-point-of-maintenance.html 

## Don't repeat yourself
Don't copy-paste code if you can avoid it, because you then have to maintain each copy separately and nobody likes doing that. This isn't a single point of control and you'll be doomed to experiencing anomalous behaviour all day. 

Use functions, classes and traits to reduce cloning.

https://en.wikipedia.org/wiki/Don%27t_repeat_yourself

## Application layers
The app is divided into three main lands: presentation, logic and data storage. This basically means keeping this stuff separate from each other:
  * html/css/javascript
  * php (mostly)
  * the thing that interacts with your db

https://en.wikipedia.org/wiki/Multitier_architecture#Three-tier_architecture

## Objects
Later on we'll take a look at using php objects to organise our code, instead of the usual `include()`. We use objects everywhere now.

https://en.wikipedia.org/wiki/Object-oriented_programming

## Cohesion and Coupling
We aim to have high-cohesion, low-coupling classes; that is, to build classes which serve a particular purpose, they do it well without superflous behaviours, and can be freely reused because they aren't wantonly influencing the behaviour of other stuff.
 
https://stackoverflow.com/questions/3085285/cohesion-coupling

# Folder Safary
Let's have a quick look at some high-profile folders. You'll invariably visit these folders more than others. 
* *conf.* Aura Controller configurations live here - more on these later.
* *library.* The guts of schoolbox business logic. Contains both Handler and Aura Paradigms. Everything except the *library/Schoolbox* folder is Handler Paradigm. More on that later.
* *library/Schoolbox.* Contains Aura Paradigm stuff. More on that later.
* *templates.* Contains html templates used by both our templating libraries, though it's mostly associated with Handler Paradigm.
* *www.* Handler Paradigm files that you access via the url.

# Paradigms
As mentioned earlier, schoolbox is a cultural melting pot of ideas. Over time, two paradigms emerged and co-exist today. These represent two different philosophies on how to organise, access and execute php functionality. They both have various pros and cons, but I'll spare you those details for now. 

The oldest of these is Handler Paradigm. We are trying to phase this out entirely in favour of the Aura Paradigm.

## Handler Paradigm
If you were coding in the early 2000s you'll have encountered the genesis of this paradigm. Prior to php having object-oriented capability, `include()`s were one of the only ways to reuse code in a convenient and maintainable manner.

Four major pieces are involved in the Handler Paradigm.

* *Handler Files.* These are low-cohesion classes that encapsulate a variety of mostly-unrelated database interactions. Their only commonality is that they typically relate to one very-high level concept or feature. An example is a *Wiki Handler* class which contains all the database queries for dealing with wiki articles, regardless of intent, purpose and location within the realm of schoolbox. We're trying to eliminate all the Handlers.
* *www.* All of the php files here execute via the url. These basic controller files instantiate Handlers to interact with the database, then they process the results in preparation for rendering to screen.
* *SitePage.* This is a giant, all-encompassing object that that fulfils a potpourri of duties. Its main activity is to `include()` a file from *www*. This file (with the help of other files) generates content to display to the end user.
* *phplib.* The ultimate developer curse-word. We'll see how to use it shortly, but it's a php-based templating engine that takes a controller script's output, an html template, and substitutes placeholder strings for actual content. It does this in a really weird manner that is notoriously confusing.
 
In summary, this is what happens:

When the controller file is `include()`d in `SitePage`, *www* controller files instantiate Handlers which are able to interact with the database. The output of these Handlers are processed into an html template via the *phplib* templating engine. SitePage echoes the processed html output to screen.  

Incidentally, because of this `include()`, nearly every file in *www* has the unorthodox sensation of being able to inexplicably access `$this`.

Even though we're trying to cull the Handler population, it's still in use. You'll inevitably encounter it and have to fix something-or-other in it, so there's value in understanding its fundamentals. Also, when you reach the other Paradigm, you'll appreciate it more for its conveniences!

### The Controller
To start with, make a `www/test.php` file. This is your main controller file where all the action takes place. As previously described, it'll be included into `SitePage` and then its contents will be unceremoniously dumped out. 


```php
<?php

echo 'test';
die();

```

Now visit http://192.168.36.10/test - stuff appears! The `die()` is noteworthy here. Remember that this file is being `include()`d elsewhere, and that *elsewhere* will continue trying to do stuff. In this case, `SitePage` will continue trying to render html. But there's no template for it, so it complains angrily at you. 

Try removing the `die()` command, and refresh the screen. You'll be attacked by `phplib` complaining at you in a most unholy way:
 
 ```
 Warning: loadfile: contentTpl is not a valid handle. in /vagrant/library/HtmlTemplate.php on line 710
 Warning: subst: unable to load contentTpl. in /vagrant/library/HtmlTemplate.php on line 710
 ```
 
It does this sort of stuff when you haven't specified an html file for it to process and output. Don't try to comprehend these warning and error messages. There's no hope. Associate a message with a known solution; it's much easier this way.
 
Let's fix this problem by specifying an html template for it to render.

### The HTML Template
Make a `kingdoms.html` file in `templates` folder. Dump in some html content of your choosing if you like.

```html
<!-- templates/kingdoms.html -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

<h1>This is my kingdom! Begone, fiend!</h1>

</body>
</html>
```

Now we need to tell our Controller which html file it should try to load. Note that we don't need to `die()` anymore, because we're giving the `SitePage` monster some html to eat. 

```php
<?php

$this->setFile(TEMPLATE_content, 'kingdoms.html');
```

Okay, great - we can decorate this html file all we like, but how do we use php to make some dynamic content? Note that phplib basically works on the principle replacing placeholder variables with content; it's a giant string replacer. These placeholder vars are also colloquially known as *template vars*, *phplib vars* and *phplibs*.

Let's do that now by putting some placeholder vars into the html template, which get swapped out with actual values later on. We'll add one for our favourite warcry, because no battle is complete without one.

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

<h1>This is my kingdom! Begone, fiend!</h1>
<h2>{FAVOURITE_WARCRY}</h2>

</body>
</html>
```

Now we need to tell the Controller to put contents into the placeholder vars. Note that `[]` is simply a shorthand syntax for `array()`. 

`setVar()` is a phplib command that identifies which placeholder vars should be replaced with content, and what that content should be, in an associative array.
 
```php
<?php

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setVar([
    'FAVOURITE_WARCRY' => 'For glory!'
]);
```

Update the controller and have a play with the contents of `FAVOURITE_WARCRY`. 

> Time To Experiment!
>
> At this point you can also experiment with the html file itself, moving the placeholder var around and so on. You can add more vars if you want, to augment your existing warcry - you can add to the existing array, or you can execute `$this->setVar()` independently for each. It's nicer to stack them all into an array where possible; it's easier to read.

When you're ready, we'll start moving onto more complex stuff.

### The Handler
Let's summon a known Handler and have it interact with the database and fetch some data for us in an array, and then we can work on pushing out to the screen. Modification of the controller below results in some content being dumped out using `var_dump()`

```php
<?php

$wikiHandler = new Wiki();
$allArticles = $wikiHandler->getAll();

var_dump($allArticles);

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setVar([
    'FAVOURITE_WARCRY' => 'For glory!'
]);
```

Now we need to work out how to show contents of each element in the `$allArticles` array. With only `setVar()` at our disposal, we can't do it in a meaningful and scalable fashion; we'd have to use something like this:

```php
$this->setVar([
    'WIKI_0_ID' => $allArticles[0]['wikiID'],
    'WIKI_1_ID' => $allArticles[1]['wikiID'],
    ...
    ...
]);
```

Clearly that won't do. Thus, we come to `phplib blocks`.

### phplib Blocks
phplib `blocks` are a special kind of placeholder. `blocks` contain placeholder vars and this is essentially used as a "blueprint" - the placeholder vars are substituted and the result is stored in memory in some arcane variable. Upon repeated iterations, the blueprint is reused and its results repeatedly stacked up in this variable, until all the iterations are finished. When all iterations are complete, the arcane var's contents are all concatenated together and returned. 

Kind of like filling out the same paper form year after year, splaying them out on the ground in order of occurrence and gluing them together head to tail.

#### Setting up blocks
Let's make our first phplib `block`. We need to use a special syntax in both the controller:

```php
<?php

$wikiHandler = new Wiki();
$allArticles = $wikiHandler->getAll();

var_dump($allArticles);

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'wiki_article', 'wiki_article_block');
$this->setVar([
    'FAVOURITE_WARCRY' => 'For glory!'
]);
```

...and html file. 
 
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

<h1>This is my kingdom! Begone, fiend!</h1>
<h2>{FAVOURITE_WARCRY}</h2>

<!-- BEGIN wiki_article -->
<p><strong>{WIKI_ID} {WIKI_SLUG}</strong></p>
<!-- END wiki_article -->

</body>
</html>
```

Here, the `block` is denoted with html comments in a special format: `<!-- BEGIN wiki_article -->` and `<!-- END wiki_article -->`. You can rename `wiki_article` to something else, but it has to be alphanumeric - it can't contain spaces or punctuation. Note that the second argument in `setBlock()` is the name of the block and the third argument simply has `_block` appended to the name of the block.

This convention is by design - it's to stop you getting confused in day-to-day affairs. The third arg really represents an arcane var that stores an accumulation of outputs but you don't have to think about this at all if you follow the convention.

The block contains two placeholder vars - `WIKI_ID` and `WIKI_SLUG`. Each wiki article's `id` and `slug` values will be inserted into these placeholders soon.

If you reload your screen now, you'll see this placeholder var: `{wiki_article_block}`. This is because we haven't put any data into it and processed it into the html file - a procedure we conduct using `parse()` and known as *parsing blocks*. This is a very tedious exercise and really strange, but if you follow the conventions you'll be alright.

#### Parsing blocks
To make use of our `block` "blueprint", we need to parse it out. This processes the block by putting in contents, stacking them up in memory, then putting the entire stack into the block's original location - thus substituting it with an accumulation of content. 

We can offer a once-off parse which only remembers the contents of the last "blueprint" that was processed:

```php
$this->parse('wiki_article_block', 'wiki_article');
```

...or do what we were discussing earlier, which is displaying (parsing) an accumulation of `blocks` which contain different content. This accumulation is represented by a third argument, which is a simple boolean:

```php
$this->parse('wiki_article_block', 'wiki_article', true);
```

Let's try both. 
 
#### Once-off parse
The first two arguments in `$this->parse()` are the reverse of the last two arguments in `$this->setBlock()`. We've adopted this convention everywhere so you'll be able to comprehend what's going on.

Remember to follow this convention, as this prevents you from getting confused and assailed by various phplib errors.

```php
<?php

$wikiHandler = new Wiki();
$allArticles = $wikiHandler->getAll();

var_dump($allArticles);

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'wiki_article', 'wiki_article_block');

$this->setVar([
    'FAVOURITE_WARCRY' => 'For glory!'
]);

// Substitute some test data into placeholder vars

$this->setVar([
    'WIKI_ID' => $allArticles[0]['wikiID'],
    'WIKI_SLUG' => $allArticles[0]['slug'],
]);

// Once-off parse

$this->parse('wiki_article_block', 'wiki_article');

```

If you refresh your screen, you'll see the test content render because the block has (finally) been parsed. 

> Time To Experiment!
>
> You can experiment here, repeatedly executing `$this->parse()` etc. to your heart's content to see what happens. 

#### Accumulated parse
To show all the wiki articles, we need to iteratively parse blocks. We simply use a `for` or `foreach` loop, set the placeholder vars to contain something meaningful, then parse them out with the third boolean argument set to `true`.

```php
<?php

$wikiHandler = new Wiki();
$allArticles = $wikiHandler->getAll();

var_dump($allArticles);

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'wiki_article', 'wiki_article_block');

$this->setVar([
    'FAVOURITE_WARCRY' => 'For glory!'
]);

// Substitute some each wiki article's data into placeholder vars

foreach ($allArticles as $currentArticle) {
    $this->setVar([
        'WIKI_ID' => $currentArticle['wikiID'],
        'WIKI_SLUG' => $currentArticle['slug'],
    ]);
    
    // Accumulated parse
    
    $this->parse('wiki_article_block', 'wiki_article', true);
}
```

Try refreshing your screen now. Your wiki articles should show in a very bland list! 

> Time To Experiment!
>
> You can remove the `var_dump()` now and experiment further if you want. Have a further explore in `www` for more-developed controller files. They'll be vastly more complicated and contain things that this guide has omitted; however, if you look around you'll see the same principles being applied.

This represents the basis for all Handler Paradigm construction. Most are variants on this theme in one way or another, and usually with more nuanced contents, but the basic principle is still the same. Continue when your'e ready.

#### Exclude block from rendering
Blocks can be used to include/exclude html chunks from rendering, conditionally presenting information to an end user without having to rely on javascript. If excluded, such chunks will never be in the DOM, so it's not the same as your javascript framework `.show()` or `.hide()` commands.

To prevent a block from rendering, we use a `setVar()` on it and set it to an empty string `''`. We toggle blocks to hide or show buttons or other content - sometimes even other blocks.

Let's set up a new `block` called `toggle`. 

```php
<?php

$wikiHandler = new Wiki();
$allArticles = $wikiHandler->getAll();

var_dump($allArticles);

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'wiki_article', 'wiki_article_block');
$this->setBlock(TEMPLATE_content, 'toggle', 'toggle_block');

$this->setVar([
    'FAVOURITE_WARCRY' => 'For glory!'
]);

// Substitute some each wiki article's data into placeholder vars

foreach ($allArticles as $currentArticle) {
    $this->setVar([
        'WIKI_ID' => $currentArticle['wikiID'],
        'WIKI_SLUG' => $currentArticle['slug'],
    ]);
    
    // Accumulated parse
    
    $this->parse('wiki_article_block', 'wiki_article', true);
}

// Toggle this block on and off
$visible = true;

if ($visible) {
    $this->parse('toggle_block', 'toggle');
}
else {
    $this->setVar([
        'toggle_block' => ''
    ]);
}
```

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

<h1>This is my kingdom! Begone, fiend!</h1>
<h2>{FAVOURITE_WARCRY}</h2>

<!-- BEGIN wiki_article -->
<p><strong>{WIKI_ID} {WIKI_SLUG}</strong></p>
<!-- END wiki_article -->

<!-- BEGIN toggle -->
<h3>Greetings, earthling</h3>
<!-- END toggle -->

</body>
</html>
```

Now you can refresh the screen to see what happens. You'll see the contents of the `toggle` block appear. You can change the `$visible` var to `false` to alter the behaviour of the block. Usually the conditional is more involved than the simple example here, and occasionally requires showing one block and hiding others.

> Time To Experiment!
>
> At this point you should try constructing more blocks and conditionally hiding and showing some of them. It takes some getting used to, but so long as you follow the convention shown in this guide you should be alright. For now, get comfortable with the syntax and the concept. 

If you're okay with this, we'll move on to a more advanced utilisation of `blocks` - nesting them within each other to present more complex data.

#### Advanced Blockery: Nested Blocks
Data typically involves structures within structures and we need a way to render them out. We can do this by adding more blocks and micromanaging their rendering. It's very similar to what we've seen thus far, but requires a couple of extra tricks. You should be comfortable with the concept of blocks, `setVar()` and `parse()` usage.

Let's start afresh with our html and controller files. For this example we won't need to use a Handler. Instead, we'll use some boring data to illustrate countries and the kingdoms within, in a nested html list. We can set up our data and basic html layout as follows; we can also set up our block definitions. If you've followed this guide from the beginning this should be familiar. Note the accumulated parse; we'll be adding more data later.

```php
<?php
// Countries, Kingdoms and their Monarchies
$data = [
    'Australia' => [
        'Camberwell' => 'Geoffrey Rush', 
        'Hawthorn' => 'Robert',
        'CBD' => 'Karl'
    ],
];

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'country', 'country_block');

foreach ($data as $country => $kingdoms) {
    $this->setVar([
        'COUNTRY_NAME' => $country
    ]);

    $this->parse('country_block', 'country', true);
}
```

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>

<ul>
    <!-- BEGIN country -->
    <li>
        <strong>{COUNTRY_NAME}</strong>
    </li>
    <!-- END country -->
</ul>

</body>
</html>
```

To set up a nested block, we change the first argument in `setBlock()` to be the name of the parent (containing) block. In this case, kingdoms exist in a country. Let's add a `kingdom` block and start parsing out some kingdom names.
 
```php
<?php
// Countries, Kingdoms and their Monarchies
$data = [
    'Australia' => [
        'Camberwell' => 'Geoffrey Rush', 
        'Hawthorn' => 'Robert',
        'CBD' => 'Karl'
    ],
];

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'country', 'country_block');
$this->setBlock('country', 'kingdom', 'kingdom_block');

foreach ($data as $country => $kingdoms) {
    $this->setVar([
        'COUNTRY_NAME' => $country
    ]);

    foreach ($kingdoms as $kingdom => $ruler) {
        $this->setVar([
            'KINGDOM_NAME' => $kingdom,
            'KINGDOM_RULER' => $ruler
        ]);
        
        $this->parse('kingdom_block', 'kingdom', true);
    }

    $this->parse('country_block', 'country', true);
}
```
 
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Title</title>
</head>
<body>
 
<ul>
    <!-- BEGIN country -->
    <li>
        <strong>{COUNTRY_NAME}</strong>
        <ul>
            <!-- BEGIN kingdom -->
            <li>{KINGDOM_RULER} of {KINGDOM_NAME}</li>
            <!-- END kingdom -->
        </ul>
    </li>
    <!-- END country -->
</ul>
 
</body>
</html>
```
 
If you refresh your screen now you'll see the country of Australia and a nested list of kingdoms and assorted rulers. But we're missing a crucial step that often leads to misbehaviours. Let's add another country and see what happens.

```php
<?php
// Countries, Kingdoms and their Monarchies
$data = [
    'Australia' => [
        'Camberwell' => 'Geoffrey Rush', 
        'Hawthorn' => 'Robert',
        'CBD' => 'Karl'
    ],
    'New Zealand' => [
        'Dougland' => 'Douglas',
        'Sloane Town' => 'dddaaammmooo'
    ]
];

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'country', 'country_block');
$this->setBlock('country', 'kingdom', 'kingdom_block');

foreach ($data as $country => $kingdoms) {
    $this->setVar([
        'COUNTRY_NAME' => $country
    ]);

    foreach ($kingdoms as $kingdom => $ruler) {
        $this->setVar([
            'KINGDOM_NAME' => $kingdom,
            'KINGDOM_RULER' => $ruler
        ]);
        
        $this->parse('kingdom_block', 'kingdom', true);
    }

    $this->parse('country_block', 'country', true);
}
```

When you reload you'll see something strange going on - the kingdoms of Australia also appear in the New Zealand list. This is because the accumulated parse kept accumulating, remembering everything it had ever seen. Every time it parsed, it parsed an ever-growing list of kingdoms. But if we remove the third arg from each `parse()`, it won't work in the way we intended; it'll just give you the last country in the list.

We need to clear the block after its containing block has been parsed, resetting it to an empty string. You'll see various comments along the lines of "Clear children after parsing out the parent" as a constant reminder to ourselves. The clearing of blocks is often accidentally omitted, leading to strange phplib-based behaviuor; content showing up when it shouldn't, too much content, not enough, etc.

To clear a block, we simply use a `setVar()` once its container block has been parsed.

```php
<?php
// Countries, Kingdoms and their Monarchies
$data = [
    'Australia' => [
        'Camberwell' => 'Geoffrey Rush', 
        'Hawthorn' => 'Robert',
        'CBD' => 'Karl'
    ],
    'New Zealand' => [
        'Dougland' => 'Douglas',
        'Sloane Town' => 'dddaaammmooo'
    ]
];

$this->setFile(TEMPLATE_content, 'kingdoms.html');
$this->setBlock(TEMPLATE_content, 'country', 'country_block');
$this->setBlock('country', 'kingdom', 'kingdom_block');

foreach ($data as $country => $kingdoms) {
    $this->setVar([
        'COUNTRY_NAME' => $country
    ]);

    foreach ($kingdoms as $kingdom => $ruler) {
        $this->setVar([
            'KINGDOM_NAME' => $kingdom,
            'KINGDOM_RULER' => $ruler
        ]);
        
        $this->parse('kingdom_block', 'kingdom', true);
    }
    
    $this->parse('country_block', 'country', true);
    
    // Clear children after parsing out the parent

    $this->setVar([
        'kingdom_block' => ''
    ]);
}
```

> Time To Experiment!
>
> Try adding extra content to our `$data` array - perhaps add some heirs to the throne and so on, so that you can explore nested blocks some more. 

If you made it this far - good work! You've survived the fundamentals of phplib sorcery and can go on to greater (and more convenient) things.