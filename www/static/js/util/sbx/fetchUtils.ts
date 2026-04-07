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
 * @returns The parsed event type and data
 */
export function parseSseEvent(rawEvent: string): { eventType: string | undefined; data: string } {
    let eventType: string | undefined;
    const dataLines: string[] = [];

    const lines = rawEvent.split("\n");
    lines.forEach((line) => {
        if (line.startsWith("event:")) {
            if (eventType) {
                console.warn("Multiple event types in SSE chunk, using the last one");
            }
            eventType = line.replace("event:", "").trim();
        } else if (line.startsWith("data:")) {
            dataLines.push(line.replace("data:", "").trim());
        }
        // Could handle other SSE fields here (e.g. "id:", "retry:") if needed
    });

    return { eventType, data: dataLines.join("\n") };
}

/**
 * Trigger a browser download for the provided response body
 * @param response - The response containing the file to download
 * @param filename - The filename to use for the download
 * @returns The downloaded Blob
 */
export async function downloadResponse(response: Response, filename: string): Promise<Blob> {
    // TODO: probably try extract the filename from content-disposition, it appears doing that properly for a lot of cases needs some investigation first

    // Trigger the download
    const blob = await response.blob();
    const objectUrl = URL.createObjectURL(blob);
    try {
        const link = document.createElement("a");
        link.href = objectUrl;
        link.download = filename;
        link.style.display = "none";
        document.body.appendChild(link);
        link.click();
        link.remove();
    } finally {
        URL.revokeObjectURL(objectUrl);
    }

    return blob;
}
