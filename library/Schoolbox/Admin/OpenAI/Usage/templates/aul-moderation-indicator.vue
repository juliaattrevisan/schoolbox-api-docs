<script setup lang="ts">
import { assertUnreachable } from "@js/util/errorUtils";
import { AiModerationStatus, STATUS_LABELS } from "./moderationEvent";

interface Props {
    /** The session's highest-priority active moderation status, or null when there is no active concern */
    status: AiModerationStatus | null;
}

const props = defineProps<Props>();

const indicator = window.Vue.computed<null | { class: string; status: AiModerationStatus }>(() => {
    if (props.status === null) return null;
    if (props.status === AiModerationStatus.NO_ACTION_REQUIRED) return null;
    if (props.status === AiModerationStatus.FURTHER_ACTION_TAKEN) return null;

    if (props.status === AiModerationStatus.REQUIRES_ATTENTION) {
        return { class: "warning", status: props.status };
    }

    if (props.status === AiModerationStatus.FURTHER_ACTION_REQUIRED) {
        return { class: "alert", status: props.status };
    }

    assertUnreachable(props.status);
});

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    } else {
        /* i've never needed this part before, it seems specific to this area
         * (always mounts at 'interactive' rather than 'complete', and foundation never loads the tooltips...)
         * we *could* just remove the document.readyState check, but that risks it triggering the foundation error
         * which will crash the template... sooo this is a workaround I suppose.
         */
        window.addEventListener("load", () => window.jQuery(document).foundation("tooltip", "reflow"), {
            once: true,
        });
    }
});
</script>

<template>
    <span
        v-if="indicator"
        :class="indicator.class"
        :title="`Moderation: ${STATUS_LABELS[indicator.status]}`"
        data-tooltip
    >
        <sbx-icon name="alert" />
    </span>
</template>

<style scoped></style>
