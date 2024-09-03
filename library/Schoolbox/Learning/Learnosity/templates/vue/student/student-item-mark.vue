<script setup lang="ts">
/**
 * Contains a students mark linked to an item, (only one item is shown at a time)
 */

import { gsap } from "gsap";
import { getGradient } from "@js/util/cssUtils";
import { StudentItemMarkTypes } from "@schoolboxLearnosity/js/types/StudentItemMarkTypes";
import { getItemScore, getItemMaxScore } from "@schoolboxLearnosity/js/util/markingUtils";
import type { StudentItem } from "@schoolboxLearnosity/js/types/StudentItem";

interface Props {
    item: StudentItem; // the item to display
    container: HTMLElement; // the parent container, to look for the relevant item
    isShown: boolean; // whether to display the item or not
    showTitle: boolean; // whether to show the item title or not
}
const props = defineProps<Props>();
const markEl = window.Vue.ref<HTMLElement | null>(null); // the element to render the report into

/**
 * To display the current item
 * @returns {string} - the display style
 */
const display = window.Vue.computed(() => {
    return props.isShown ? "block" : "none";
});

/**
 * If the item only contains questions that don't have a maxScore, we don't want to show any score
 * @returns {boolean} - the total score
 */
const noScore = window.Vue.computed(() => {
    return props.item.questions.every((question) => question.response?.maxScore === null);
});

/**
 * Get the current total score for the item
 * @returns {number} - the total score
 */
const totalScore = window.Vue.computed(() => {
    return getItemScore(props.item);
});

/**
 * Get the max score for the item
 * @returns {number} - the max score
 */
const maxScore = window.Vue.computed(() => {
    return getItemMaxScore(props.item);
});

/**
 * Get the current status for the item
 * @returns {StudentItemMarkTypes} - the current status
 */
const status = window.Vue.computed(() => {
    if (!props.item.isMarked) {
        return StudentItemMarkTypes.NOT_MARKED;
    }
    // use the 'partial' status for now if the item is marked but has no score
    if (props.item.isMarked && noScore.value) {
        return StudentItemMarkTypes.PARTIAL;
    }
    if (totalScore.value === maxScore.value) {
        return StudentItemMarkTypes.CORRECT;
    }
    if (totalScore.value === 0) {
        return StudentItemMarkTypes.INCORRECT;
    }
    return StudentItemMarkTypes.PARTIAL;
});

/**
 * Get the current tooltip for the item
 * @returns {string} - the current tooltip
 */
const tooltip = window.Vue.computed(() => {
    if (status.value === StudentItemMarkTypes.NOT_MARKED) {
        return "Not marked";
    }
    if (props.item.isMarked && noScore.value) {
        return "Marked";
    }
    if (status.value === StudentItemMarkTypes.CORRECT) {
        return "Correct";
    }
    if (status.value === StudentItemMarkTypes.INCORRECT) {
        return "Incorrect";
    }
    return "Partially correct";
});

/**
 * Update the span element with the current score
 * @param {HTMLDivElement} statusElement - the element to update
 */
function updateSpanElement(statusElement: HTMLDivElement) {
    const spanElement = statusElement?.querySelector("span") as HTMLSpanElement;
    // if the item contains only questions that don't have a max score, we don't want to show any score
    if (noScore.value) {
        spanElement.textContent = "-";
        return;
    }
    spanElement.textContent = `${totalScore.value}/${maxScore.value}`;
}

/**
 * Update the hidden element which is used for accessibility
 * @param {HTMLDivElement} statusElement - the element to update
 */
function updateHiddenElement(statusElement: HTMLDivElement) {
    const hiddenElement = (statusElement?.parentElement as HTMLDivElement).querySelector(
        ".visually-hidden",
    ) as HTMLSpanElement;
    // if the item contains only questions that don't have a max score, we don't want to show any score
    if (noScore.value) {
        hiddenElement.textContent = "Marked";
        return;
    }
    hiddenElement.textContent = ` ${tooltip.value} ${totalScore.value}/${maxScore.value}`;
}

/**
 * Get the current tooltip for the item
 * @returns {string} - the style to add a background colour that reflects the score
 */
function getStatusStyles() {
    if (!props.item.isMarked) {
        return `lrn-circle-${StudentItemMarkTypes.NOT_MARKED}`;
    }
    if (props.item.isMarked && noScore.value) {
        return `lrn-circle-${StudentItemMarkTypes.PARTIAL}`;
    }
    return `${getGradient(totalScore.value, maxScore.value)}-bg`;
}

/**
 * Update the styles when the score changes
 * @param {HTMLDivElement} statusElement - the element to update
 */
function updateStyles(statusElement: HTMLDivElement) {
    const classes = Array.from(statusElement.classList).filter((c) => c.includes("lrn-circle-") || c.includes("-bg")); // find any classes on statusElement that contain 'lrn-circle-*' or '*-bg'
    classes.forEach((c) => statusElement.classList.remove(c)); // remove all classes that contain 'lrn-circle-*'
    statusElement.classList.add(getStatusStyles()); // add the correct class
}

/**
 * Update the score display anytime the score is changed
 */
function updateScoreDisplay() {
    const statusElement = markEl.value?.querySelector("[data-status]") as HTMLDivElement;
    statusElement.setAttribute("data-status", status.value);
    statusElement.setAttribute("data-original-title", tooltip.value);
    updateSpanElement(statusElement);
    updateHiddenElement(statusElement);
    updateStyles(statusElement);
    gsap.from(markEl.value, { duration: 0.3, opacity: 0 });
}

/**
 * Move the item mark element from the entire quiz response into our own container
 */
window.Vue.onMounted(() => {
    const mark = props.container.querySelector(`[data-reference-title="${props.item.reference}"]`) as HTMLDivElement;
    if (mark) {
        if (!props.showTitle) {
            (mark.firstChild as HTMLElement).nodeValue = ""; // clear the title, just leaving the mark design
        }
        markEl.value?.appendChild(mark);
        updateScoreDisplay();
    }
});

/**
 * Need to watch the item questions for any score updates, if any questions score changes we need to total up the new score and display
 * We also need to to check if the item is marked or not again based on if all questions that can be scored are now scored
 */
window.Vue.watch(
    () => props.item.questions,
    () => {
        updateScoreDisplay();
    },
    { deep: true },
);
</script>

<template>
    <!-- these classes are so the correct learnosity styles can be applied -->
    <div ref="markEl" class="lrn learnosity-report session-detail-by-item mark"></div>
</template>

<style scoped>
.mark {
    display: v-bind(display);
}
</style>
