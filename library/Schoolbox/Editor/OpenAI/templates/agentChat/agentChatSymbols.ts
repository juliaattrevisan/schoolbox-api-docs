import type { EventBusKey } from "@vueuse/core";
import type { AgentChatFeedMessage, AgentChatMessageType } from "./agentChatTypes";

/**
 * scrollFeed: Scroll the anchor into view so that the feed is scrolled and fixed to the bottom
 * retryRequest: Trigger a new request (used for retrying failed requests)
 */
export const acPromptEventKey: EventBusKey<{
    scrollFeed?: boolean;
    retryRequest?: AgentChatFeedMessage<AgentChatMessageType.REQUEST>;
}> = Symbol("acPromptEventKey");
