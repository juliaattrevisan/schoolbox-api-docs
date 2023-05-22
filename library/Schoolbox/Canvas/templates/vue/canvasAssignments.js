import { createApp } from "vue";
import CanvasAssignments from "./canvas-assignments";

window.Vue.createApp({
    components: {
        CanvasAssignments,
    },
}).mount("#canvas-assignments-container");
