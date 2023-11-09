import { createPinia } from "pinia";
import QuizFeedback from "./quiz-feedback.vue";

window.Vue.createApp({
    components: {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        QuizFeedback,
    },
})
    .use(createPinia())
    .mount("#quiz-feedback-container");
