```jsx
const actions = [ // Note this example is missing typing (IAction[]) (no support for tsx)
    {
        id: 1,
        icon: "modify",
        title: "Modify",
        href: "#"
    },
    {
        id: 2,
        icon: "more",
        title: "More",
        href: "#"
    }
];

<CardList>
    <Card id="1" title="Card with actions" meta="Card with actions" actions={actions} />
    <Card id="2" title="Card with no actions" meta="Card with no actions" />
</CardList>
```