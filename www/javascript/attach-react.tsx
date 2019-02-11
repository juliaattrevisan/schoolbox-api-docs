import * as React from "react";
import ReactDOM from "react-dom";

interface IPage {
  /** the html ID to attach the page to */
  id: string;

  /** the React component imported above */
  component: React.ComponentClass | React.SFC;
}

/**
 * Takes an array of pages (consisting of an ID and a React component), and attachs the component to the element.
 * It will also take all of the elements attributes and pass them through as props to the component.
 */
const AttachReact = (pages: IPage[]): void => {
  pages.forEach(page => {
    const element: HTMLElement = document.getElementById(page.id);

    if (element) {
      // Loop through element's attributes and store in an object (converted to camelCase)
      // for passing through to the component
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
};

export default AttachReact;
