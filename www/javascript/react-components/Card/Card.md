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
