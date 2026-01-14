import type { AnyAgentChatFeedMessage } from "./agentChatTypes";

/**
 * Format the createdAt date for a feed entry into a
 * time that can be displayed against a message
 * @param message The feed message to render the timestamp for
 * @returns The formatted timestamp
 */
export function formatTimestamp(message: AnyAgentChatFeedMessage): string {
    const date = window.dayjs(message.createdAt);
    const time = date.format(window.dateDisplayFormats.dayjsTime);

    /** from today */
    if (date.isSame(window.dayjs(), "day")) {
        return time;
    }

    /** less than a week in the past */
    if (date.startOf("day").isAfter(window.dayjs().startOf("day").subtract(7, "day"))) {
        return `${date.format("dddd")} ${time}`;
    }

    /** older than a week */
    return `${date.format(window.dateDisplayFormats.dayjsDateShort)} ${time}`;
}
