# 5 Responsibilities of Model
Models are domain representations of database records. While a Model's properties frequently mirror that of database columns for that Model's table, this isn't always the case: more complex Models may consist of other Models as well. 

> Regardless of their complexity, Models are narcissistic in that they only care about their own properties, behaviours and "possessions" (dependencies). You can expect to interrogate Models to ask them questions about themselves and their dependencies. 

They don't care about where they came from or where they are going; instantiation and persistence of Models is the purpose of a Mapper or other service class.

> Models are not responsible for performing any persisting work; these duties should be delegated to another class, such as a Mapper or a service class that utilises a Mapper.  