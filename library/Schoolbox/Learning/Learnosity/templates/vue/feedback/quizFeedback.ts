import { createPinia } from "pinia";
import QuizFeedback from "./quiz-feedback.vue";

window.Vue.createApp({
    components: {
        QuizFeedback,
    },
})
    .use(createPinia())
    .mount("#quiz-feedback-container");
