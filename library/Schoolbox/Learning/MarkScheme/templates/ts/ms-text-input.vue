<script setup lang="ts">
/**
 * Renders a text input where teachers.
 * Combo box data attr picked up by plugin to insert submission-states.
 */

import type { SubmissionErrorStates } from "./markScheme";

interface Props {
    id: string | number;
    name?: string;
    submissionErrorStates: SubmissionErrorStates;
    class?: string;
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
     * Options for alaressValidate
     */
    alaressValidateOptions?: Record<string, unknown>;
}

const props = defineProps<Props>();
const mark = defineModel<string | null>("mark", { required: true });

const markInputEl = window.Vue.useTemplateRef<HTMLInputElement>("markInputRef");

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
    if (markInputEl.value && (!props.alaressValidateOptions || window.jQuery(markInputEl.value).valid())) {
        const newValue = markInputEl.value.value;

        /* ignore if the mark hasn't _meaningfully_ changed */
        if ((newValue ?? "") === (mark.value ?? "")) {
            return;
        }

        updatingFromInput = true;
        mark.value = newValue;

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

window.Vue.onMounted(() => {
    if (!markInputEl.value) {
        throw new Error("Unable to initialise mark input without an input element");
    }

    if (!props.readonly) {
        if (props.submissionErrorStates.length > 0) {
            window.jQuery(markInputEl.value).combobox(props.submissionErrorStates.map((state) => state.name));
        }

        if (props.alaressValidateOptions) {
            if (!markInputEl.value.form) {
                throw new Error("Unable to initialise alaressValidate without a form element");
            }

            // if it doesn't already have a validator, add one
            if (!window.jQuery(markInputEl.value.form).data("validator")) {
                window.jQuery(markInputEl.value.form).alaressValidate();
            }

            window.jQuery(markInputEl.value).rules("add", props.alaressValidateOptions);
            window.jQuery(markInputEl.value).valid(); /* always run upon load */
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
});
</script>

<template>
    <input
        :id="props.id?.toString() ?? undefined"
        ref="markInputRef"
        :value="mark"
        class="no-margin"
        :class="props.class"
        :name="props.name"
        type="text"
        :tabindex="props.tabIndex"
        :placeholder="props.placeholder"
        :data-validation-limit="props.limit"
        :maxlength="props.maxLength"
        :title="props.inputTitle"
        :data-test="`assessment-feedback-mark-input-${props.id}`"
        data-combobox
        :readonly="props.readonly"
        @change="onMarkChanged"
        @keypress.enter.prevent="onClickEnter"
    />
</template>
