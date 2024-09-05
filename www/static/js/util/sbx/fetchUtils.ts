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
