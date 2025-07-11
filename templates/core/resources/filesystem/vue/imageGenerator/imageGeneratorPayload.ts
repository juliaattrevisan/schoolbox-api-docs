type ImageGeneratorResponseSuccess = {
    success: true;
    data: unknown; // TODO
};

type ImageGeneratorResponseFailure = {
    success: false;
    message: string;
};

/**
 * This is the final response output from the generateImage function, after parsing and validation has occured.
 * It is not necessarily the same format as the response from the server,
 * instead, this allows checking for a success or failure state in a typed/reliable manner.
 */
type ImageGeneratorApiResponse = ImageGeneratorResponseSuccess | ImageGeneratorResponseFailure;

type ImageGeneratorApiPayload = {
    userPrompt: string;
    imageSize: string | null;
    imageStyle: string | null;
    previousResponseId: string | null;
};

/**
 * Generate each field's new body content from the AI for the lesson plan
 * @param endpoint - The endpoint to fetch
 * @param payload - The data to send in the request
 * @returns The response from the fetch
 */
export async function generateImage(
    endpoint: string,
    payload: ImageGeneratorApiPayload,
): Promise<ImageGeneratorApiResponse> {
    let responseData: ImageGeneratorApiResponse = { success: false, message: "An unknown error occurred" };

    try {
        const response = await fetch(endpoint, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
        });

        // If the response is a 200 and HTML, then we probably got given the offline page, return a more meaningful message instead.
        const contentType = response.headers.get("Content-Type") || "";
        if (response.status === 200 && contentType.includes("text/html")) {
            responseData.message = "You appear to be offline, check your internet connection and try again.";
            return responseData;
        }

        const responseText = await response.text();

        // Safely parse the response as JSON, we don't necessarily know what it will be in advance
        let responseJson: unknown = null;
        try {
            responseJson = JSON.parse(responseText);
        } catch {
            // If it fails to parse, we'll just leave it as null for the next check
        }

        // The response must be an object
        if (typeof responseJson !== "object" || responseJson === null || Array.isArray(responseJson)) {
            throw new Error("Invalid response");
        }

        // If we didn't get a 200 response, then handle as per RFC 9457 "API Problem"
        if (response.status !== 200) {
            responseData.success = false;
            if ("title" in responseJson && typeof responseJson.title === "string") {
                responseData.message = responseJson.title;
            }
            if ("detail" in responseJson && typeof responseJson.detail === "string") {
                responseData.message += `: ${responseJson.detail}`;
            }
            return responseData;
        }

        // TODO: just dump the data for now
        responseData = { success: true, data: responseJson };
    } catch (e) {
        responseData = { success: false, message: (e as Error).message };
    }

    // If the response was unsuccessful and had no message, set a default message
    if (responseData.success === false && responseData.message === "") {
        responseData.message = "An unknown error occurred";
    }

    return responseData;
}
