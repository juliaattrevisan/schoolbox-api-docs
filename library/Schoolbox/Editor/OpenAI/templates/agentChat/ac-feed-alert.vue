<script lang="ts" setup>
import type { AgentChatAlert } from "./agentChatTypes";

interface Props {
    /**
     * The message to display
     */
    message: AgentChatAlert;
}

const props = defineProps<Props>();
</script>

<template>
    <div class="ac-feed-alert" :class="`ac-feed-alert--${props.message.side}`">
        <p class="alert-box l-mar--0 l-pad-s" :class="props.message.severity">
            {{ props.message.message }}
        </p>
    </div>
</template>

<style scoped lang="scss">
.ac-feed-alert {
    width: 80%;
    display: flex;

    &--request {
        align-items: flex-start;
        justify-content: flex-end;
        margin-left: 20%;
    }
}
</style>
