Cards are generally used to display lists of items, with snippets of information and actions the user can take on the item, displayed at the top right of the card.

```jsx
<Card
  title="A news item"
  meta="Published by Zane Smith 2 hours ago"
  image={{ src: "https://via.placeholder.com/64", alt: "Some alt text" }}
/>
```

## Sizes

There are 3 different sizes of cards: standard, compact, and tiny.

### Standard

Standard cards are used when the card requires a title & meta information. This is the most common size used. See [Card](/#/Cards/Card).

### Compact

Compact cards have less padding, and strictly display one line of text only. These are often used when displaying sub items below a standard card.

### Tiny

Tiny cards have no padding at all. These are rarely used in Schoolbox, please use only if you really must.
