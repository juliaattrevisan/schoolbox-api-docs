import * as React from "react";
import ReactDOM from "react-dom";
import Detail from "./templates/Detail";

// Components
// TODO: set up an array of component names and loop them
// TODO: abstract this out into a function that can be used across modules
const element: HTMLElement = document.getElementById("Detail");
if (element) {
  // Send element's attributes to the component
  const attributes: any = {};
  Array.prototype.slice.call(element.attributes).forEach(function(item: any) {
    const attributeKey = item.name.replace(/-([a-z])/g, function(
      g: any
    ): string {
      return g[1].toUpperCase();
    });
    attributes[attributeKey] = item.value;
  });
  const reactElement = React.createElement(Detail, attributes);
  ReactDOM.render(reactElement, document.getElementById("Detail"));
}
