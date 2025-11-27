import type { EventBusKey } from "@vueuse/core";

export const acPromptEventKey: EventBusKey<{ scrollFeed: boolean }> = Symbol("acPromptEventKey");
