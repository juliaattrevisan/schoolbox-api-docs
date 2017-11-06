# 3 Responsibilities of Supporting Classes
Supporting classes are containers of detailed, low-level logic. Typically contents of these classes originate in a Controller method that's grown far too large and intricate for its own good. Alternatively, supporting classes can manifest purely by intent.

> As per Controller methods, supporting classes should utilise dependency injection of interfaces (where possible) instead of concrete classes. This promotes reusability and testing by allowing us to substitute actual instances with stubs or mocked data.

In the example below, we cannot provide a mock Event or mock User instance upon which to test various scenarios to ensure behaviour is correct. We're stuck with using a user record that currently exists in the database and changing its values there to test. It's not easy to test a user without a role, or with an unknown role, for example.

```php
class Cat
{
    /**
     * Cat constructor.
     *
     * Very hard to test, depends on database contents existing
     *
     * @param int $eventId
     * @param int $userId
     */
    public function __construct($eventId, $userId)
    {
        $this->setEvent(EventMapper::getInstance()->findById($eventId));
        $this->setUser(UserMapper::getInstance()->findById($userId));
    }
    
    /**
     * Do something
     */
    public function doSomething() {
        if ($user->getRole()->isStaff()) {
            if ($this->getEvent()->getAuthor() == $this->getUser()->getId()) {
                // return something
            }
            else {
                // return something
            }
        }
        else {
            // return something
        }
        
        // return something
    }
}
```

By supplying instances ("injecting the dependencies") to this class we can abstract the problem away:
 
```php
class Cat
{
    /**
     * Cat constructor.
     *
     * Easier to test, can throw any kind of data in to witness outcomes
     *
     * @param Event\Model $event
     * @param User\Model $user
     */
    public function __construct(Event\Model $event, User\Model $user)
    {
        $this->setEvent($event);
        $this->setUser($user);
    }
    
    /**
     * Do something
     */
    public function doSomething() {
        // same as before
    }
    
    ...
    ...
}
``` 

...and now we can supply various instances of `$event` and `$user` - they can be malformed/invalid instances or edge cases, etc. - and see the results accordingly. We can just make up these instances and drop them in ("mocking") to see what happens in `doSomething()`.

> In unit tests we can declare the outcomes such that we can automatically detect behavioural changes.

## 3.1 Design Patterns
Supporting classes are independent classes; they aren't bound to any particular paradigm. To this end, you can invent your own architecture to solve whatever problem is at hand.

Design Patterns aren't rigid laws that absolutely must be obeyed and used at all times. They are simplified solutions that have consistently emerged. It is up to the author to determine whether a Design Pattern should be used, and if so, which one. A cron job or once-off script, for example, may not require the architectural forethought of a Design Pattern; a cruder solution may be sufficient. On the other hand, Design Patterns form the basis of a common language which can be used to succinctly communicate a larger system design.