# Resolver

The notification system uses a Resolver in its routing sub-system to determine
exactly whom is subscribed to a message topic.


## Glossary

<dl>
  <dt>Resolver</dt>
  <dd>
    A subsystem that is used to determine which users are subscribed to a 
    message topic.
  </dd>

  <dt>Trie</dt>
  <dd>
    A data structure.
  </dd>
</dl>

## Implementation

The resolver is implemented as a [trie](https://en.wikipedia.org/wiki/Trie)
data structure.

Each node in the trie is a single route segment, with wildcards:
* `*` matches a single route segment, regardless of content
* `#` matches the rest of the route, regardless of content

Each value in the trie is a list; each item in that list will be one of either:
* an integer (corresponds to a user ID)
* a string (this key is aliased to another key in the trie)

### Variants

#### Read-only (flattened)

#### Writable (hydrated)

### Rebinding

### Examples
