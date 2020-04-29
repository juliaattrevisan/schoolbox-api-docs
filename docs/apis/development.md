Best practises:
* Do not bother with server-side HTML: rendering is a responsibility of the frontend



1. write spec in OpenAPI

Maybe use Stoplight as a collaboration tool? I'm preferring it over Swagger these days

Starting point: a standard RESTful interface
* Design the base object that the api deals with
* Document the standard HTTP verbs

Next step is to liase with stakeholders
* Show them the base object that you've developed
* Ask them about the actions that may need to be performed on this object
** It's unlikely stakeholders will think of objects in quite the same way you do as a developer. For example, they may see a user and their group memberships as an atomic object, whereas to a developer a user and their memberships are not the same thing.
** Now that the stakeholders have given their feedback: you'll probably have some amendments to make to the API

This is going to be an iterative and collaborative processs


2. Build a mock interface which conforms to the spec

Not sure how you'd go about this yet. We might be able to use an off-the-shelf
mock endpoint, or perhaps build one?

3. Implement a live version of the API

4. write tests that validate the API as written conforms to the spec
