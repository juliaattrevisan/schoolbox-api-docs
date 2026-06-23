<script setup lang="ts">
import { assertUnreachable } from "@js/util/errorUtils";
import { useAgentChatStore } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/store/agentChatStore";
import AcFeed from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/ac-feed.vue";
import {
    AgentChatGuardrailSeverity,
    AgentChatMessageType,
} from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import type { AgentChatLogEntry } from "@schoolboxLibrary/Editor/OpenAI/templates/agentChat/agentChatTypes";
import { AiFeature, AiLogFormat } from "./payload";
import type { AiSessionLogApiResponse } from "./payload";
import { AiModerationStatus } from "./moderationEvent";
import type { AiModerationEvent } from "./moderationEvent";

interface Props {
    feature: AiFeature;
    sessionLogs: AiSessionLogApiResponse<AiLogFormat.AGENT_CHAT | AiLogFormat.GUARDRAIL>["logs"];
    moderationEvents: AiModerationEvent[];
}

const props = defineProps<Props>();
const acStore = useAgentChatStore();

/**
 * Get a human-readable description for a particular moderation event, depending on its status.
 * @param event - moderation event
 * @returns relevant description, or null to exclude the event
 */
function getModerationEventDescription(event: AiModerationEvent): string | null {
    switch (event.status) {
        case AiModerationStatus.NO_BEHAVIOUR_FLAGGED:
            return null; // these shouldn't be rendered as they are not a concern
        case AiModerationStatus.REQUIRES_ATTENTION:
            return "Current Status: Requires attention";
        case AiModerationStatus.NO_ACTION_REQUIRED:
            return `Deemed no further action required by ${event.reviewedBy?.fullName ?? "Unknown"}`;
        case AiModerationStatus.FURTHER_ACTION_REQUIRED:
            return `Further action required by ${event.reviewedBy?.fullName ?? "Unknown"}`;
        case AiModerationStatus.FURTHER_ACTION_TAKEN:
            return `Resolved by ${event.resolvedBy?.fullName ?? "Unknown"}`;
        default:
            return assertUnreachable(event.status);
    }
}

const sessionFeed = window.Vue.computed<ReturnType<typeof acStore.parseFeed>>(() => {
    const firstLog = props.sessionLogs[0];
    if (firstLog === undefined) {
        return [];
    }

    const feed: AgentChatLogEntry[] = [];
    for (const entry of props.sessionLogs) {
        if (entry.logFormat === AiLogFormat.AGENT_CHAT) {
            /** An agent chat message can be directly pushed to the feed */
            feed.push(entry.data.input);
            feed.push(entry.data.output);
        } else if (entry.logFormat === AiLogFormat.GUARDRAIL) {
            /** Convert guardrail log entries to the agent chat log format */

            /** Ignore any successful guardrail checks, we don't need to show them here */
            if (entry.data.output.type === "guardrail" && entry.data.output.data.flagged === false) {
                continue;
            }

            /**
             * The input is still relevant, it reflects the original request
             *
             * As we're hiding successful guardrail checks, there shouldn't be duplicate inputs
             * since a failed guardrail will prevent the AgentChat log from ever being created
             */
            feed.push({
                createdAt: entry.data.input.createdAt,
                type: AgentChatMessageType.REQUEST,
                data: entry.data.input.data,
            });

            /** Show full information about why the request failed guardrails as this is the usage log in admin */
            if (entry.data.output.type === "guardrail") {
                feed.push({
                    createdAt: entry.data.output.createdAt,
                    type: AgentChatMessageType.ALERT,
                    data: {
                        side: AgentChatMessageType.RESPONSE,
                        severity: AgentChatGuardrailSeverity,
                        title: `The request failed to meet expected guidelines (${entry.data.output.data.guardrail})`,
                        message: JSON.stringify(entry.data.output.data.metadata), // just stringify this for now, could be improved later
                    },
                });
            } else {
                feed.push({
                    createdAt: entry.data.output.createdAt,
                    type: AgentChatMessageType.ALERT,
                    data: {
                        side: AgentChatMessageType.RESPONSE,
                        severity: "alert",
                        message: entry.data.output.data,
                    },
                });
            }
        } else {
            assertUnreachable(entry);
        }
    }

    /* jam in any flagged moderation events as if they were guardrail violations */
    for (const event of props.moderationEvents) {
        const eventDescription = getModerationEventDescription(event);
        if (eventDescription === null) {
            continue; // skip events that aren't a concern
        }

        feed.push({
            createdAt: event.createdAt,
            type: AgentChatMessageType.ALERT,
            data: {
                side: AgentChatMessageType.RESPONSE,
                severity: "warning",
                message: eventDescription,
            },
        });
    }

    return acStore.parseFeed(feed, firstLog.createdBy);
});

const agentIcon = window.Vue.computed(() => {
    if (props.feature === AiFeature.DIALOG_AGENT) {
        return "dialog-agent";
    }

    return "ai";
});
</script>

<template>
    <ac-feed
        :feed="sessionFeed"
        :allow-selection="false"
        max-height="85vh"
        error-prefix="There was a problem retrieving the response."
        :agent-icon="agentIcon"
        show-timestamps
    />
</template>

<style scoped></style>
