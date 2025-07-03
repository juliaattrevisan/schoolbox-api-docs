<script setup lang="ts">
/**
 * Renders a text input where teachers.
 * Combo box data attr picked up by plugin to insert submission-states.
 */

import type { SubmissionErrorStates } from "./markScheme";

interface Props {
    participant: {
        user: {
            id: number;
        };
    };
    submissionErrorStates: SubmissionErrorStates;
    tabIndex?: number;
    /**
     * the placeholder text
     */
    placeholder?: string;
    /**
     * JS validation
     */
    limit?: number;
    /**
     * how big can this input be?
     */
    maxLength?: number;
    /**
     * the tooltip to display on this input
     */
    inputTitle?: string;
    /**
     * Should this form input be rendered as readonly?
     */
    readonly?: boolean;
    /**
     * The target for alaressValidate, if left blank then it will use the input itself
     */
    alaressValidateTarget?: string;
    /**
     * Options for alaressValidate
     */
    alaressValidateOptions?: Record<string, unknown>;
}

const props = defineProps<Props>();
const mark = defineModel<string | null>("mark", { required: true });

const markInputEl = window.Vue.useTemplateRef<HTMLInputElement>("markInputRef");

window.Vue.onMounted(() => {
    if (!markInputEl.value) {
        throw new Error("Unable to initialise mark input without an input element");
    }

    if (!props.readonly) {
        $(markInputEl.value).combobox(props.submissionErrorStates);

        const validateTarget =
            (props.alaressValidateTarget ? document.querySelector(props.alaressValidateTarget) : null) ||
            markInputEl.value.form;

        if (!validateTarget) {
            throw new Error("Unable to initialise alaressValidate without a form element");
        }

        // Remove any existing validator, otherwise this will never re-initialise
        // with the new values... this might be bad if you try to use the element in
        // a form with other inputs... i'm not sure yet.
        $.removeData(validateTarget, "validator");

        $(validateTarget).alaressValidate(props.alaressValidateOptions);
        $(validateTarget).valid(); /* always run upon load */
    }
});

let updatingFromInput = false;

/**
 * When changes are made to the local mark, update the model
 * only if the value is valid
 */
async function onMarkChanged(): Promise<void> {
    if (props.readonly) {
        return;
    }

    /* only persist the mark if it's valid */
    if (markInputEl.value && $(markInputEl.value).valid()) {
        updatingFromInput = true;
        mark.value = markInputEl.value.value;

        await window.Vue.nextTick();
        updatingFromInput = false;
    }
}

/**
 * Blur the input when the enter key is pressed rather than submitting the form
 * This apparently needs to be handled here instead of on the form, since alaressValidate
 * takes over the form submit event too early and we can't stop it
 */
function onClickEnter(): void {
    if (markInputEl.value) {
        markInputEl.value.blur();
    }
}

/**
 * If the mark model is updated elsewhere, update the local mark
 * to ensure the input is always in sync
 */
window.Vue.watch(mark, async (newValue) => {
    const input = markInputEl.value;
    if (!updatingFromInput && input) {
        input.value = newValue ?? "";

        // for alaressValidate to pick up the change
        await window.Vue.nextTick();
        input.dispatchEvent(new Event("change", { bubbles: true }));
    }
});
</script>

<template>
    <input
        :id="`mark-${props.participant.user.id}`"
        ref="markInputRef"
        :value="mark"
        class="no-margin"
        name="mark"
        type="text"
        :tabindex="props.tabIndex"
        :placeholder="props.placeholder"
        :data-validation-limit="props.limit"
        :maxlength="props.maxLength"
        :title="props.inputTitle"
        :data-test="`assessment-feedback-mark-input-${props.participant.user.id}`"
        data-mark-input
        data-combobox
        :readonly="props.readonly"
        @change="onMarkChanged"
        @keypress.enter.prevent="onClickEnter"
    />
</template>
