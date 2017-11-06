# 6 Responsibilities of View
The View's only purpose is to render data that the Controller generated. It doesn't care about how that data as manufactured or from whence it came. It should only operate on the data that was provided. 

## 6.1 Render what was provided
Ironically, twig is so powerful it can actually execute php commands - on numerous occasions costly database calls were made without realising and this caused all sorts of weird performance issues. We can't enforce this very strictly so it's a soft constraint we impose on ourselves that we only render the data array that's provided by the Controller method.
 
> Render only the data that was provided - though twig is powerful, it can be dangerous in an indirect or obscure manner.

## 6.2 Twig Syntax
Despite twig being able to call php methods, we don't actually want twig vars to reference php methods directly. This prevents the twig template from being used in a different context in an unambiguous fashion; twig performs a chain-of-responsibility upon this syntax. See *Implementation* https://twig.symfony.com/doc/2.x/templates.html

Instead of the following which states that a `getUsername()` method executes: 
 
```twig
<strong>user.getUsername()</strong>

class User {
    public function getUsername() {
        return $this->username;
    }
}
```

We prefer to use this equivalent which also lets us substitute the `user` instance for an array:

```twig
<strong>user.username</strong>

$user = [
    'username' => 'drhodes'
]

class User {
    public function getUsername() {
        return $this->username;
    }
}
```

Sometimes we need to do that because we manufacture our own data structure, preventing twig from accidentally executing costly methods, or we've deliberately aggregated data.

> Twig vars should be data-structure agnostic; twig vars should not know whether they're operating on an object or an array.

## 6.3 Reusable Fragments
Twig also has the ability to set up layout inheritances, macros and includes - utilise these liberally where applicable. If you can reuse these pieces, do so. 

> There's a `/templates/sub` folder where you can put globally reusable template pieces. Otherwise, store as many resuable pieces in your module's templates folder.

## 6.4 The Pantry
There's a menagerie of user interface elements in `/pantry` which you can use as a guide. This is a good place to check behaviours when you're building user interface elements - one element's style can have a dramatic effect on any number of other elements.
   
> Check the `/pantry` for user interface elements that you can use, and their interactions with other elements and styles. 

## 6.5 Compiling CSS and Javascript 
We use compilers for css and javascript to manage imports and minify the result. If you're experiencing stylistic oddities or behavioural weirdness, try recompiling your styles and javascript files. This is respectively known as `grunt sass` and `grunt uglify`.

> Note that when you add javascript to the compilation step (look in your `Gruntfile.js`) you're making it globally available at the cost of increasing the minified file's size.
