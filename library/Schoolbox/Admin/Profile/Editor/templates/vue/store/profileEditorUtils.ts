type ProfileEditorFetchResponse = {
    success: boolean;
    message?: string;
};

/**
 * Fetch a given endpoint for the profile editor and process the response for errors
 * @param endpoint - The endpoint to fetch
 * @param options - The fetch options to use
 * @returns The response from the fetch
 */
export async function fetchProfileEditorEndpoint(
    endpoint: string,
    options: RequestInit,
): Promise<ProfileEditorFetchResponse> {
    const responseData: ProfileEditorFetchResponse = { success: false, message: "An unknown error occurred" };

    try {
        const response = await fetch(endpoint, options);

        // If the response is a 200 and HTML, then we probably got given the offline page, return a more meaningful message instead.
        const contentType = response.headers.get("Content-Type") || "";
        if (response.status === 200 && contentType.includes("text/html")) {
            responseData.message = "You appear to be offline, check your internet connection and try again.";
            return responseData;
        }

        const responseText = await response.text();

        // If we got some form of response, set the initial message to that
        responseData.message = responseText;

        // Safely parse the response as JSON, we don't necessarily know what it will be in advance
        let responseJson: unknown = null;
        try {
            responseJson = JSON.parse(responseText);
        } catch {
            // If it fails to parse, we'll just leave it as null
        }

        if (typeof responseJson === "object" && responseJson !== null) {
            if ("success" in responseJson && typeof responseJson.success === "boolean") {
                responseData.success = responseJson.success;
            }

            if ("message" in responseJson && typeof responseJson.message === "string") {
                responseData.message = responseJson.message;
            } else if (responseData.success === true) {
                // Remove the default error message if the response was successful but had no message
                delete responseData.message;
            }
        }
    } catch (e) {
        responseData.success = false;
        responseData.message = "An unknown error occurred";
    }

    // If the response was unsuccessful and had no message, set a default message
    if (responseData.success === false && responseData.message === "") {
        responseData.message = "An unknown error occurred";
    }

    return responseData;
}
