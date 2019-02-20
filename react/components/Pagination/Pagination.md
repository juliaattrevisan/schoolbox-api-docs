## Basic pagination

```jsx
<Pagination pageCount={10} />
```

## Indexing

<b>Note:</b> The react-paginate library has pages 0-indexed- but our wrapper has adjusted that to be indexed at 1. This makes more sense for pagination as you typically refer to the first page as page 1 rather than page 0. Also when using window history- it makes more sense for page 2 to load page 2, instead of page 3.

## Setting an initial page

Use the `activePage` prop to set an initial page

```jsx
const page = 5;

<Pagination pageCount={10} activePage={page} />;
```

## Managing page with state

You can use the `activePage` and `onPageChange` props to manage the selected page with state.

```jsx
let page = 5; // Note this would most likely be a state property in React.

const onPageChange = selectedItem => {
  // Note this is missing a type defintion
  page = selectedItem.selected;
};

<Pagination pageCount={10} activePage={page} onPageChange={onPageChange} />;
```
