import type { EventBusKey } from "@vueuse/core";
import type { AgentChatRequest } from "./agentChatTypes";

/**
 * scrollFeed: Scroll the anchor into view so that the feed is scrolled and fixed to the bottom
 * retryRequest: Trigger a new request (used for retrying failed requests)
 */
export const acPromptEventKey: EventBusKey<{ scrollFeed?: boolean; retryRequest?: AgentChatRequest }> =
    Symbol("acPromptEventKey");
