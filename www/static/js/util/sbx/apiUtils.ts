/**
 * Utilities for accessing API endpoints and recieving fully typed responses
 */

/** The base response type returned by the api tools, not necessarily the API itself */
type BaseResponseData = Record<string, unknown>;

type ApiBaseResponseSuccess<TRespData extends BaseResponseData> = {
    success: true;
    data: TRespData;
};

type ApiBaseResponseFailure = {
    success: false;
    message: string;
    data?: unknown;
};

// For unknown failures, such as if we can't parse the response
// In this case, we provide the original status code as an extra field in case we need to handle it
interface ApiBaseResponseUnknownFailure extends ApiBaseResponseFailure {
    responseStatus: number;
    detail: unknown;
}

export type ApiBaseResponse<TRespData extends BaseResponseData> =
    | ApiBaseResponseSuccess<TRespData>
    | ApiBaseResponseFailure
    | ApiBaseResponseUnknownFailure;

/** The base type for an API request body */
type ApiRequestBodyBase = Record<string, unknown>;

interface ApiRequestOptions<TBody extends ApiRequestBodyBase | undefined = undefined> {
    /**
     * The API endpoint to send the request to
     */
    url: string;
    /**
     * The HTTP method to use for the request
     */
    method: "GET" | "POST" | "PUT" | "PATCH" | "DELETE";
    /**
     * The body of the request, if applicable
     * For a GET request, this is transformed into query parameters where possible
     */
    body?: TBody;
}

/**
 * Build a fetch request to an API endpoint
 * @param type - The type of the request response to expect (JSON or text/event-stream)
 * @param options - The options for the request
 * @returns - A promise that resolves to the response from the server
 */
async function buildRequest(
    type: "application/json" | "text/event-stream",
    options: ApiRequestOptions<ApiRequestBodyBase | undefined>,
): Promise<Response> {
    let { url } = options;

    /** include application/json in a text/event-stream request to get the right error format */
    let acceptHeader = "application/json";
    if (type === "text/event-stream") {
        acceptHeader = "application/json, text/event-stream";
    }

    const requestOptions: RequestInit = {
        method: options.method,
        headers: {
            "Content-Type": "application/json",
            Accept: acceptHeader,
        },
    };

    if (options.body !== undefined) {
        if (options.method === "GET") {
            // Remove any undefined values from the query string
            const filteredBody = Object.fromEntries(
                Object.entries(options.body as Record<string, string | string[]>).filter(
                    ([, value]) => value !== undefined,
                ),
            );

            const queryParams = new URLSearchParams();

            Object.entries(filteredBody).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    // If the value is an array, append each value separately with the same key
                    // The backend will parse a key with [] as an array (e.g. key[]=value1&key[]=value2)
                    value.forEach((v) => queryParams.append(`${key}[]`, v.toString()));
                } else {
                    queryParams.append(key, value.toString());
                }
            });

            const queryString = queryParams.toString();
            url += url.includes("?") ? `&${queryString}` : `?${queryString}`;
        } else {
            // All other methods send as JSON body
            requestOptions.body = JSON.stringify(options.body);
        }
    }

    return fetch(url, requestOptions);
}

/**
 * Parses a JSON response from the server and validates that it matches the expected format (any object)
 * If it does not match, or it fails to parse, a failure response is returned in its place
 * @template TResponseData - The expected type of the response from the server
 * @param text - The response from the server as a string
 * @param status - The HTTP status code of the response
 * @param contentType - The content type of the response (e.g. "application/json")
 * @returns - The parsed response, always extending ApiBaseResponse
 */
function parseResponse<TResponseData extends BaseResponseData>(
    text: string,
    status: number,
    contentType: string = "",
): ApiBaseResponse<TResponseData> {
    let responseJSON: TResponseData;

    /* If the response is a 200 and HTML, then we probably got given the offline page, return a more meaningful message instead */
    if (status === 200 && contentType.includes("text/html")) {
        const failureResponse: ApiBaseResponseUnknownFailure = {
            success: false,
            responseStatus: status,
            message: "You appear to be offline, check your internet connection and try again.",
            detail: null,
        };
        return failureResponse;
    }

    try {
        responseJSON = JSON.parse(text) as TResponseData;
    } catch (e) {
        const failureResponse: ApiBaseResponseUnknownFailure = {
            success: false,
            responseStatus: status,
            message: "An unexpected error occurred",
            detail: e,
            data: text,
        };
        return failureResponse;
    }

    /* Does the response match the expected format? For now this is just any object
     * as most of our endpoints don't return any field to indicate success or failure
     */
    if (typeof responseJSON !== "object" || responseJSON === null || Array.isArray(responseJSON)) {
        const failureResponse: ApiBaseResponseUnknownFailure = {
            success: false,
            responseStatus: status,
            message: "Invalid response format",
            detail: text,
            data: responseJSON,
        };
        return failureResponse;
    }

    /* Is this not a successful response? If so, it theoretically should be following RFC 9457 "API Problem" */
    if (status !== 200) {
        const failureResponse: ApiBaseResponseFailure = {
            success: false,
            message: "An unexpected error occurred",
            data: responseJSON,
        };
        if ("title" in responseJSON && typeof responseJSON.title === "string") {
            failureResponse.message = responseJSON.title;
        }
        if ("detail" in responseJSON && typeof responseJSON.detail === "string") {
            failureResponse.message += `: ${responseJSON.detail}`;
        }
        return failureResponse;
    }

    const successResponse: ApiBaseResponseSuccess<TResponseData> = {
        success: true,
        data: responseJSON,
    };
    return successResponse;
}

/**
 * Generic function to make an API request to the server
 * Validates that the response matches our expected format
 * API errors should follow RFC 9457 "API Problem"
 * @template TResponseData - The type of the response from the server
 * @template TBody - The type of the request body
 * @param options - Options for the API request
 * @returns - A typed response from the server including a success state
 */
export async function apiRequest<
    TResponseData extends BaseResponseData,
    TBody extends ApiRequestBodyBase | undefined = undefined,
>(options: ApiRequestOptions<TBody>): Promise<ApiBaseResponse<TResponseData>> {
    const request = buildRequest("application/json", options);

    let response: Response;
    try {
        response = await request;
    } catch (e) {
        console.error("Failed to make API request", e, options);
        const failureResponse: ApiBaseResponseUnknownFailure = {
            success: false,
            responseStatus: 0,
            message: e instanceof Error ? e.message : "An unexpected error occurred",
            detail: e,
        };
        return failureResponse;
    }

    const contentType = response.headers.get("Content-Type") || "";
    const responseBody = await response.text();
    const responseJSON = parseResponse<TResponseData>(responseBody, response.status, contentType);

    return responseJSON;
}
