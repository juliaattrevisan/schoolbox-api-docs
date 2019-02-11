import * as React from "react";
import AttachReact from "../../../../www/javascript/attach-react";

// Import page components for the bundle
import Detail from "./templates/Detail";

// Define your array of pages to include in the bundle
const pages = [
  {
    id: "Detail",
    component: Detail
  }
];

// Attach the pages to our html elements, sending through any attributes as props
AttachReact(pages);
