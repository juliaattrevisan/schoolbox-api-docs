<script lang="ts" setup>
import type { AgentChatFeedMessage, AgentChatMessageType } from "./agentChatTypes";

interface Props {
    /**
     * The message to display
     */
    message: AgentChatFeedMessage<AgentChatMessageType.ALERT>;
}

const props = defineProps<Props>();
</script>

<template>
    <div
        class="ac-feed-alert"
        :class="`ac-feed-alert--${props.message.data.side}`"
        :data-test="`ac-feed-alert-list-item-${props.message.entryKey}`"
    >
        <p class="alert-box l-mar--0 l-pad-s" :class="props.message.data.severity" data-test="ac-feed-message-content">
            <template v-if="props.message.data.title">
                <strong>{{ props.message.data.title }}</strong>
                <br />
            </template>
            {{ props.message.data.message }}
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
