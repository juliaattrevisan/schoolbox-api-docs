### Basic tabs

```jsx
<Tabs>
  <Tab tab="Tab 1 title" id="1">
    Tab 1 content
  </Tab>
  <Tab tab="Tab 2 title" id="2">
    Tab 2 content
  </Tab>
</Tabs>
```

### Putting HTML into tab title

You can put HTML inside a tab heading like so

```jsx
<Tabs>
  <Tab
    tab={
      <span>
        HTML title 1 <span className="icon-view" />
      </span>
    }
    id="3"
  >
    Tab 1 content
  </Tab>
  <Tab
    tab={
      <span>
        HTML title 2 <span className="icon-progress" />
      </span>
    }
    id="4"
  >
    Tab 2 content
  </Tab>
</Tabs>
```
