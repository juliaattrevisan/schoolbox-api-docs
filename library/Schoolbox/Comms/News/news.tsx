import * as React from "react";
import ReactDOM from "react-dom";

// Import page components for the bundle
import Detail from "./templates/Detail";

// Define your array of pages to include in the bundle
//  id: the html ID to attach the page to
//  component: the React component imported above
const pages = [
  {
    id: "Detail",
    component: Detail
  }
];

// TODO: abstract this code out into a function that can be used across modules
pages.forEach(page => {
  const element: HTMLElement = document.getElementById(page.id);

  if (element) {
    // Send element's attributes to the component
    const attributes: any = {};
    Array.prototype.slice.call(element.attributes).forEach((item: any) => {
      const attributeKey = item.name.replace(
        /-([a-z])/g,
        (g: any): string => {
          return g[1].toUpperCase();
        }
      );
      attributes[attributeKey] = item.value;
    });
    const reactElement = React.createElement(page.component, attributes);
    ReactDOM.render(reactElement, element);
  }
});
