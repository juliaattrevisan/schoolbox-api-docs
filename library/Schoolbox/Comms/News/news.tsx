import * as React from "react";
import * as ReactHabitat from "react-habitat";

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
class Main extends ReactHabitat.Bootstrapper {
  constructor() {
    super();

    // Create a new container builder
    const containerBuilder: ReactHabitat.IContainerBuilder = new ReactHabitat.ContainerBuilder();

    // Register our components that we want to expose to the DOM
    pages.forEach(page => {
      containerBuilder.register(page.component).as(page.id);
    });

    // Set the DOM container
    this.setContainer(containerBuilder.build());
  }
}

// Create a single instance of our app
const instance = new Main();

declare global {
  interface Window {
    updateHabitat?: any;
  }
}
window.updateHabitat = instance.update.bind(instance);

// Export the instance
export default instance;
