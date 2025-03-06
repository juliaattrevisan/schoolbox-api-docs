import { defineStore } from "pinia";
import type { LessonPlanField } from "../lessonPlannerTypes";

export const useLessonPlannerStore = defineStore("lessonPlannerStore", () => {
    // An ordered list of each field of the lesson plan
    const fields = window.Vue.ref<
        (LessonPlanField & {
            selected: boolean; // Whether the field is selected or not
        })[]
    >([]);

    /**
     * Initialise the store
     * @param $fields - An ordered list of each field of the lesson plan
     */
    function init($fields: LessonPlanField[]): void {
        fields.value = $fields.map((field) => ({
            ...field,
            selected: false,
        }));
    }

    const modalStep = window.Vue.ref<null | "generate" | "generating" | "review">(null);

    // Whether the modal is open or not, a ref since <basic-modal> needs it to be a v-model
    const isModalOpen = window.Vue.ref(false);

    window.Vue.watch(isModalOpen, (isOpen) => {
        if (!isOpen) {
            modalStep.value = null;
        }
    });

    window.Vue.watch(modalStep, (step) => {
        if (step !== null) {
            isModalOpen.value = true;
        }
    });

    /**
     * Open the modal to the initial page
     */
    function openModal(): void {
        modalStep.value = "generate";
    }

    return {
        init,
        fields,
        modalStep,
        isModalOpen,
        openModal,
    };
});
