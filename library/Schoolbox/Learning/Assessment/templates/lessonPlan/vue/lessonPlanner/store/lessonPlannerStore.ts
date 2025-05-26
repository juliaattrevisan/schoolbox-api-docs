import { defineStore } from "pinia";
import { useSelectedNodesStore } from "@schoolboxCurriculum/vue/curriculumSelector/store/csSelectedNodesStore";
import { generateLessonPlan, translateFieldForPayload } from "./lessonPlannerUtils";
import { LessonPlannerModalStep } from "../lessonPlannerTypes";
import type { LessonPlanField, LessonPlannerStoreField } from "../lessonPlannerTypes";

const SELECTOR_LESSON_PLANNER_FORM = "#assessmentForm";
const FORM_KEY_LESSON_TITLE = "title"; // [name="title"]
const FORM_KEY_TEMPLATE_TITLE = "templateTitle"; // [name="templateTitle"]
const FORM_KEY_DURATION = "duration"; // [name="duration"]

export const useLessonPlannerStore = defineStore("lessonPlannerStore", () => {
    // The selected curriculum nodes store, used to get the curriculum node IDs
    const selectedNodesStore = useSelectedNodesStore();

    // An ordered list of each field of the lesson plan
    const fields = window.Vue.ref<LessonPlannerStoreField[]>([]);

    // The route to generate the lesson plan with AI, if available
    const generatePlanRoute = window.Vue.ref<string | null>(null);

    // The duration of the lesson plan, only available in the AI menu
    const lessonPlanDuration = window.Vue.ref<string>("");

    // The course name/unit name/folder name/etc that this is being created for, to assist the AI
    const contextName = window.Vue.ref<string>("");

    // Additional context (if any) to be included in the prompt for the AI generation
    const additionalContext = window.Vue.ref<string>("");

    /**
     * Initialise the store
     * @param $fields - An ordered list of each field of the lesson plan
     * @param $contextName - The course name/unit name/folder name/etc that this is being created for, to assist the AI
     * @param $lessonPlanDuration - The duration of the lesson plan, only available in the AI menu
     * @param $generatePlanRoute - The route to generate the lesson plan with AI, if available
     */
    function init(
        $fields: LessonPlanField[],
        $contextName: string,
        $lessonPlanDuration: string,
        $generatePlanRoute: string | null,
    ): void {
        fields.value = $fields.map((field) => ({
            ...field,
            selected: false,
            editorId: null,
            initialBody: field.lessonPlanTemplateField === undefined ? field.body : "",
        }));
        contextName.value = $contextName;
        lessonPlanDuration.value = $lessonPlanDuration;
        generatePlanRoute.value = $generatePlanRoute;
    }

    /**
     * Do any of the fields have actual content in them that differs from the initial body?
     * I.e. should we show "Generate Lesson Plan" or "Regenerate Lesson Plan"?
     */
    const hasLessonContent = window.Vue.computed(() => {
        return fields.value.some(
            (field) => field.body.trim() !== field.initialBody.trim() && field.body.trim().length > 0,
        );
    });

    // If there was an error generating the lesson plan
    const generateError = window.Vue.ref<string | null>(null);

    // The current step of what the modal is showing
    const modalStep = window.Vue.ref<LessonPlannerModalStep | null>(null);

    // When generating/regenerating just one specific field
    const generateForField = window.Vue.ref<LessonPlannerStoreField | null>(null);

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
     * @param fieldId - A specific field to generate for, prevents other fields from being selected
     */
    function openModal(fieldId?: LessonPlannerStoreField["id"]): void {
        // is the form valid?
        const form = document.querySelector(SELECTOR_LESSON_PLANNER_FORM);
        if (!(form instanceof HTMLFormElement)) {
            throw new Error(`Unable to find form for selector ${SELECTOR_LESSON_PLANNER_FORM}`);
        }

        if (!$(form).valid()) {
            form.querySelector("input.error")?.scrollIntoView({ block: "center" });
            return; // form is invalid, don't open the modal
        }

        // intentionally keep additionalContext so it can be reused on the same page session
        // select all fields by default, or if a fieldId is provided, only select that field
        generateForField.value = null;
        fields.value.forEach((field) => {
            field.selected = fieldId === undefined ? true : fieldId === field.id;
            generateForField.value = fieldId === field.id ? field : generateForField.value;
        });

        modalStep.value = LessonPlannerModalStep.GENERATE;
    }

    /**
     * Trigger the AI generation to occur based on the selected fields
     */
    async function generate(): Promise<void> {
        generateError.value = null;
        modalStep.value = LessonPlannerModalStep.GENERATING;

        if (generatePlanRoute.value === null) {
            throw new Error("No route provided to generate the lesson plan");
        }

        // The lesson title input is outside of Vue, but has the name of "title"
        const form = document.querySelector(SELECTOR_LESSON_PLANNER_FORM);
        if (!(form instanceof HTMLFormElement)) {
            throw new Error(`Unable to find form for selector ${SELECTOR_LESSON_PLANNER_FORM}`);
        }
        const formData = new FormData(form);

        const lessonPlanTitle = formData.get(FORM_KEY_LESSON_TITLE);
        if (typeof lessonPlanTitle !== "string") {
            throw new Error("Unexpected type for lesson title form value");
        }

        const templateTitle = formData.get(FORM_KEY_TEMPLATE_TITLE);
        if (typeof templateTitle !== "string") {
            throw new Error("Unexpected type for lesson plan template title form value");
        }

        const duration = formData.get(FORM_KEY_DURATION);
        if (typeof duration !== "string") {
            throw new Error("Unexpected type for lesson plan template duration form value");
        }

        const response = await generateLessonPlan(generatePlanRoute.value, {
            templateTitle,
            lessonPlanTitle,
            context: contextName.value,
            duration,
            curriculumNodeIds: selectedNodesStore.nodesIds,
            additionalContext: additionalContext.value,
            generateForField: generateForField.value?.id ?? null,
            fields: fields.value.map((f) => translateFieldForPayload(f, f.selected, generateForField.value)),
        });

        if (response.success === false) {
            generateError.value = response.message;
            modalStep.value = LessonPlannerModalStep.GENERATE;
            return;
        }

        fields.value.forEach((field) => {
            if (!field.selected) return; // Field not selected, skip it
            field.suggestedBody = response.fields[field.id];
        });

        modalStep.value = LessonPlannerModalStep.REVIEW;
    }

    /**
     * Apply the suggestions from the AI to the actual fields of the lesson plan
     */
    function applySuggestions(): void {
        fields.value.forEach((field) => {
            if (!field.selected) return; // Field not selected, skip it
            if (!field.suggestedBody) return; // No suggestion for this field, skip it

            const editor = field.editorId ? window.sbProps.editors[field.editorId] : undefined;
            if (editor === undefined) {
                throw new Error("Unable to apply suggestion to lp-field without an editor");
            }

            editor.setData(field.suggestedBody);
        });

        // now close the modal
        isModalOpen.value = false;
        generateForField.value = null;
    }

    return {
        init,
        hasLessonContent,
        fields,
        lessonPlanDuration,
        additionalContext,
        modalStep,
        generateForField,
        isModalOpen,
        openModal,
        generate,
        generateError,
        applySuggestions,
    };
});
