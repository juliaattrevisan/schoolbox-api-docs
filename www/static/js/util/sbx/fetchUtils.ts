/**
 * Utilities to help with fetch requests.
 */

/**
 * Extracts and formats an error message from a response.
 * @param response The fetch response object.
 * @returns The formatted error message.
 */
export async function extractErrorMessage(response: Response): Promise<string> {
    let errorMessage = (await response.text()) || "An unexpected error occurred";

    // If the response is JSON, then parse it correctly for the response text
    const contentType = response.headers.get("content-type");
    if (contentType && contentType.includes("application/json")) {
        // Usually errors from SchoolBox should include a title, type, and status code:
        const responseJSON = JSON.parse(errorMessage) as { title: string; type: string; status: number };
        errorMessage = responseJSON.title || errorMessage;
    }

    return errorMessage;
}

/**
 * Parse an SSE event chunk
 * Typically an event chunk includes an event: line and data: line
 * @param rawEvent - The chunk to parse
 * @returns The parsed event name and data
 */
export function parseSseEvent(rawEvent: string): { eventName: string | undefined; data: string } {
    let eventName: string | undefined;
    const dataLines: string[] = [];

    const lines = rawEvent.split("\n");
    lines.forEach((line) => {
        if (line.startsWith("event:")) {
            if (eventName) {
                console.warn("Multiple event names in SSE chunk, using the last one");
            }
            eventName = line.replace("event:", "").trim();
        } else if (line.startsWith("data:")) {
            dataLines.push(line.replace("data:", "").trim());
        }
        // Could handle other SSE fields here (e.g. "id:", "retry:") if needed
    });

    return { eventName, data: dataLines.join("\n") };
}
