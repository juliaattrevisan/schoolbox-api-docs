<script setup lang="ts">
import { assertUnreachable } from "@js/util/errorUtils";
import { AiModerationStatus } from "./moderationEvent";

interface Props {
    /** The session's highest-priority active moderation status, or null when there is no active concern */
    status: AiModerationStatus | null;
}

const props = defineProps<Props>();

const indicator = window.Vue.computed<null | { icon: string; class: string; label: string }>(() => {
    if (props.status === null) return null;
    if (props.status === AiModerationStatus.NO_ACTION_REQUIRED) return null;
    if (props.status === AiModerationStatus.FURTHER_ACTION_TAKEN) return null;

    if (props.status === AiModerationStatus.REQUIRES_ATTENTION) {
        return { icon: "alert", class: "warning", label: "Moderation: Requires attention" };
    }

    if (props.status === AiModerationStatus.FURTHER_ACTION_REQUIRED) {
        return { icon: "alert", class: "alert", label: "Moderation: Further action required" };
    }

    assertUnreachable(props.status);
});

window.Vue.onMounted(() => {
    if (document.readyState === "complete") {
        // If the document isn't ready, we can't reflow tooltips yet
        // As vue loads the template after foundation has been initialised, we need to reinitialise the tooltips
        window.jQuery(document).foundation("tooltip", "reflow");
    }
});
</script>

<template>
    <span v-if="indicator" :class="indicator.class" :title="indicator.label" data-tooltip>
        <sbx-icon :name="indicator.icon" />
    </span>
</template>

<style scoped></style>
