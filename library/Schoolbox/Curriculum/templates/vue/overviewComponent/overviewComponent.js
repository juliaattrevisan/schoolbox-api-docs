import OverviewComponent from "./overview-component";

const overviewComponents = document.querySelectorAll("[data-overview-component]");

overviewComponents.forEach((overviewComponent) => {
    /**
     *  Ensure each Vue component is only initiated once
     */
    if (!overviewComponent.hasAttribute("data-loaded")) {
        window.Vue.createApp({
            components: {
                OverviewComponent,
            },
        }).mount(overviewComponent);
    }
    overviewComponent.setAttribute("data-loaded", true);
});
