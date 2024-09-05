<script setup>
// Ignore this file for ESLint checks for now (SA-18376)
/* eslint-disable */
/* eslint-enable vue/no-v-html */
/**
 * A sequential modal reveal using Foundations reveal modal: https://get.foundation/sites/docs-v5/bundle/reveal.html
 * Being sequential, when the 1st modal is open and the 2nd is selected, the 1st is closed and then the 2nd is displayed.
 * And when the 2nd is closed, the 1st is displayed again and on it goes.
 * As this is a combination of Vue and Foundation, things are a little more complicated then they ideally should be.
 * This is primarily due to having to track the opening and closing of modals and determining whether we are moving
 * forwards or backwards in sequence or if we are closing the modal entirely.
 * Also note we currently only handle three in the sequence as that's all that's needed for now.
 * But to add more would just be a case of adding more slots in the template.
 */

import { generateUUID } from "@js/util/jsUtils";

const props = defineProps({
    /**
     * Whether the modal is active, used to trigger the first open
     */
    isOpen: {
        type: Boolean,
        required: true,
        default: false,
    },
    /**
     * To move to the next in sequence
     */
    isNextSelected: {
        type: Boolean,
        default: false,
    },
    /**
     * To move to the previous in sequence
     */
    isPreviousSelected: {
        type: Boolean,
        default: false,
    },
    /**
     * The size of the modal to display.
     * This is added as a class to the modal.
     */
    size: {
        type: String,
        default: "small",
        validator: (value) => {
            // Valid sizes from https://get.foundation/sites/docs-v5/bundle/reveal.html
            const validSizes = ["tiny", "small", "medium", "large", "xlarge", "full"];
            return validSizes.includes(value);
        },
    },
    /**
     * Is the modal closed when the background is clicked?
     * This corresponds to the modal's `close_on_background_click`
     * setting.
     *
     * @see https://get.foundation/sites/docs-v5/bundle/reveal.html
     */
    closeOnBackgroundClick: {
        type: Boolean,
        default: true,
    },
    /**
     * For when closing the modal needs to be temporarily disabled,
     * for example when an action is processing.
     */
    isCloseDisabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(["closed", "nextOpened", "previousOpened"]);
const uuid = generateUUID();
const elementId = window.Vue.ref(`reveal-modal-${uuid}`);
const sequenceIndex = window.Vue.ref(1); // to help track the current sequence index independent of the prop
let $modal;

/**
 * to open the first modal in sequence
 */
window.Vue.watch(
    () => props.isOpen,
    (isOpen) => {
        if (isOpen) {
            sequenceIndex.value = 1;
            open();
            addClosedListener();
        } else {
            sequenceIndex.value = 1;
            close();
        }
    },
);

/**
 * to move to the next modal in sequence
 */
window.Vue.watch(
    () => props.isNextSelected,
    (isNextSelected) => {
        if (isNextSelected) {
            close();
        }
    },
);

/**
 * to move to the previous modal in sequence
 */
window.Vue.watch(
    () => props.isPreviousSelected,
    (isPreviousSelected) => {
        if (isPreviousSelected) {
            close();
        }
    },
);

/**
 * open the current modal in sequence
 */
function open() {
    $modal = $(`#${sequenceIndex.value}-${elementId.value}`);
    $modal.foundation("reveal", "open", { close_on_background_click: props.closeOnBackgroundClick });
}

/**
 * manage the closing of the modal, as we are closing a modal and potentially
 * moving forwards or backwards in sequence, things are a little more complicated
 * while we still use foundations modal setup
 */
function addClosedListener() {
    $(document).off("closed.fndtn.reveal");
    $(document).on("closed.fndtn.reveal", "[data-reveal]", (event) => {
        // to respond to the modal closing when requried
        if (!event.target.id.includes(elementId.value)) {
            // can be multiple modals open at once
            return;
        }
        if (props.isNextSelected) {
            // moving to the next in sequence, we emit nextOpened so the props.isNextSelected can be set to false again in the parent
            sequenceIndex.value += 1;
            open();
            emit("nextOpened", sequenceIndex.value);
            return;
        }
        if (sequenceIndex.value > 1) {
            // moving backwards in sequence
            sequenceIndex.value -= 1;
            open();
            emit("previousOpened", sequenceIndex.value);
            return;
        }
        // otherwise we are closing the modal entirely
        emit("closed");
    });
}

/**
 * To prevent the modal being closed when it should be disabled
 *
 * @param { Event } event - the close event
 */
function onCloseSelected(event) {
    if (props.isCloseDisabled) {
        event.stopImmediatePropagation();
    }
}

/**
 * Close the current modal, which will trigger addClosedListener
 * once close is complete
 */
function close() {
    $modal.foundation("reveal", "close");
}
</script>

<template>
    <div :id="`1-${elementId}`" :class="`reveal-modal ${props.size}`" data-reveal aria-hidden="true" role="dialog">
        <!-- @slot Reveal modal content goes into this slot -->
        <slot name="sequence-1">Sequential Reveal 1</slot>
        <a class="close-reveal-modal" aria-label="Close" @click.prevent="onCloseSelected">&#215;</a>
    </div>
    <div
        :id="`2-${elementId}`"
        :class="`reveal-modal ${props.size}`"
        data-reveal
        :data-reveal-reopen="`1-${elementId}`"
        aria-hidden="true"
        role="dialog"
    >
        <!-- @slot Reveal modal content goes into this slot -->
        <slot name="sequence-2">Sequential Reveal 2</slot>
        <a class="close-reveal-modal" aria-label="Close" @click.prevent="onCloseSelected">&#8592;</a>
    </div>
    <div
        :id="`3-${elementId}`"
        :class="`reveal-modal ${props.size}`"
        data-reveal
        :data-reveal-reopen="`2-${elementId}`"
        aria-hidden="true"
        role="dialog"
    >
        <!-- @slot Reveal modal content goes into this slot -->
        <slot name="sequence-3">Sequential Reveal 3</slot>
        <a class="close-reveal-modal" aria-label="Close" @click.prevent="onCloseSelected">&#8592;</a>
    </div>
</template>

<style lang="scss" scoped>
.xlarge {
    max-width: 80rem; //make wider for user-bulk-modify-new-values.vue
}
</style>
