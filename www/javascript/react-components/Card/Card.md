### Basic card

```jsx
<Card
  title="Basic card"
  meta="Meta information"
  image="https://via.placeholder.com/64"
/>
```

### Card with content

In order for a card to have content- it needs to be contained in an `.information-list` (this will be made a component soon)

```jsx
<div className="information-list">
  <Card
    title="Card with content"
    meta="Meta information"
    image="https://via.placeholder.com/64"
  >
    <p>Some content</p>
  </Card>
</div>
```

### Linked card

Add an `href` prop to add a link to the title and image on the card.

```jsx
<Card
  title="Linked card"
  href="#"
  meta="Meta information"
  image="https://via.placeholder.com/64"
/>
```

### Card with actions

```jsx
const cardActions = [
  {
    href: "#",
    icon: "modify",
    title: "Modify this"
  },
  {
    onClick: () => alert('JS functionality on an action'),
    icon: "more",
    title: "More actions"
  }
];

<div className="information-list">
  <div className="actions-small-2">
    <Card
      title="Card with actions"
      meta="Meta information"
      image="https://via.placeholder.com/64"
      actions={cardActions}
    />
  </div>
</div>;
```

### Card with multiple lines of meta

If you need multiple meta lines, you can send these in an array like so. Please keep this to a maximum of two lines.

```jsx
<Card
  title="Multiple meta"
  meta={["Meta line one", "Meta line two"]}
  image="https://via.placeholder.com/64"
/>
```

### Loading card

A loading card will show by default when there is no content sent to the card.

```jsx
<Card />
```

This can be disabled by sending `loader={false}` as a prop.

```jsx
<Card loader={false} />
```
