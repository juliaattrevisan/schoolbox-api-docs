Empty states are used to show users that something could or will be in a certain space, usually with a link to get the user where they need to go to create something. 

## Write access wording

`[action] which can/to [help text].`

```jsx
<EmptyState icon="news">
    <p><a href="#">Post a news article</a> to share new information on this homepage..</p>
</EmptyState>
```

## Read access wording

`There are no [subject] to [action] at this time.`

```jsx
<EmptyState icon="blog">
    <p>There are no blog posts to view at this time.</p>
</EmptyState>
```