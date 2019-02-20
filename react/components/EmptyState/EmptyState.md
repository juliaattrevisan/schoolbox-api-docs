Empty states are used to show users that something could or will be in a certain space, usually with a link to get the user where they need to go to create something.

## Basic empty state

```jsx
<EmptyState icon="homepagefeed">
  <p>There is no activity to view at this time.</p>
</EmptyState>
```

## No margin

By default- empty states have a margin-bottom. This covers most cases where the empty state is its own island, however sometimes you will need to remove this so that it doesn't create a blank white space inside an island.

Add a `noMargin` prop to remove this.

```jsx
<EmptyState icon="calendar" noMargin>
  <p>There are no upcoming events to display at this time.</p>
</EmptyState>
```

## Wording

We have a standard for wording inside empty states, split into when a user has the ability to action something (write access) and when a user does not have the ability to action something (read access).

### Write access

`[action] to [help text].`

```jsx
<EmptyState icon="news" noMargin>
  <p>
    <a href="#">Post a news article</a> to share new information on this
    homepage..
  </p>
</EmptyState>
```

### Read access

`There are no [subject] to [action] at this time.`

```jsx
<EmptyState icon="blog" noMargin>
  <p>There are no blog posts to view at this time.</p>
</EmptyState>
```
