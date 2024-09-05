import { createPinia } from "pinia";
import OverviewComponent from "./overview-component.vue";

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
        })
            .use(createPinia())
            .mount(overviewComponent);
    }
    overviewComponent.setAttribute("data-loaded", "true");
});
