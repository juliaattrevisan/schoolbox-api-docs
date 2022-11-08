# Schoolbox API Development Guide

## Best practises

### Do not bother with presentation

Rendering of information in a useful format is a responsibility of the API's
consumer (whether it be a web frontend, or another backend service).

There is no need for the API developer to consider exactly how the data returned
will be rendered, so long as they have designed and developed the base object in
conjunction with stakeholders.


## Steps

### Design the base resource that this API works with

Generally, an API will interact with a single type of resource.

The resource's fields will need to be defined, as will its relations to other
resources.

> Generally, a resource will be related to a table within Schoolbox's database;
> however, there is no hard rule that it must be.

### Document the base resource, and endpoints for the standard HTTP verbs

We use OpenAPI 3.0 as the standard for defining API specifications. These
currently exist in the https://github.com/alaress/schoolbox-api-docs repository.

#### Standards for documentation

The current https://github.com/alaress/schoolbox-api-docs specification is
intended as a living standard for how to write a OpenAPI specification.


### Discuss design with stakeholders

Now you've built a first iteration of the API, you have something ready to show
to stakeholders.

You may show them the ReDoc view of the design: this should let them get an idea
of what they can do with the API. In order to understand their expectations, you
may also need to ask them about the actions they may expect to perform on this
resource.

> It's unlikely stakeholders will think of resources in quite the same way you
> do as a developer.
>
> For example, they may see a user and their group memberships as a single
> resource, whereas to a developer a user and their memberships are two
> different types of resource.


### Iterate

Now the stakeholders have given their feedback: you'll probably have to make
some amendments to the API. That's okay: building a useful API is going to be an
iterative and collaborative process.

Modify the specification based on feedback, and re-present it to the
stakeholders; repeating as necessary until both you and the stakeholder are
satisfied with the results.


### Build a mock interface which conforms to the specification document

Once the API has been specified to the satisfaction of stakeholders, it's time
to get them actually integrating with it. One way to do this is to build a mock
version of the API, which returns dummy data.

> TODO figure out how to do this. We might be able to use an off-the-shelf mock
> endpoint, or may instead have to build one.

### Implement a live version of the API

> TODO document this once we've done this

### Write tests that validate the API as written conforms to the spec

> TODO document this once we've done this
