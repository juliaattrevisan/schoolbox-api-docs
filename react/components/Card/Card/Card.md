### Basic card

```jsx
<Card
  id="1"
  title="Basic card"
  meta="Meta information"
  image={{ src: "https://via.placeholder.com/64", alt: "Some alt text" }}
/>
```

### Linked card

Add an `href` prop to add a link to the title and image on the card.

```jsx
<Card
  id="2"
  title="Linked card"
  href="#"
  meta="Meta information"
  image={{ src: "https://via.placeholder.com/64", alt: "Some alt text" }}
/>
```

### Card with multiple lines of meta

If you need multiple meta lines, you can send these in an array like so. Please keep this to a maximum of two lines.

```jsx
<Card
  id="3"
  title="Multiple meta"
  meta={["Meta line one", "Meta line two"]}
  image={{ src: "https://via.placeholder.com/64", alt: "Some alt text" }}
/>
```
