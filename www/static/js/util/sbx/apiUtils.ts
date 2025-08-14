/**
 * Utilities for accessing API endpoints and receiving fully typed responses
 */

import { parseSseEvent } from "./fetchUtils";

/**
 * Metadata typically for paginated API responses
 * Includes the total count of items and cursor for pagination
 */
export type Metadata = {
    count: number;
    cursor: {
        current: string | null;
        next: string | null;
    };
    url?: string;
};

type ApiBaseResponseSuccess<TRespData> = {
    success: true;
    data: TRespData;
    metadata?: Metadata;
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

export type ApiBaseResponse<TRespData> =
    | ApiBaseResponseSuccess<TRespData>
    | ApiBaseResponseFailure
    | ApiBaseResponseUnknownFailure;

/** The base type for an API request body */
type ApiRequestBodyBase = Record<string, unknown> | FormData;

/**
 * The expected response data for a cursor-based API request
 */
export type ApiCursorResponseData<TData> = {
    data: TData[];
    metadata: {
        count: number;
        cursor: {
            current: string | null;
            next: string | null;
        };
    };
};

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
    /**
     * Optional signal to abort the request
     */
    signal?: AbortSignal;
}

export type SseMessageCallback<TMessageData> = (data: ApiBaseResponse<TMessageData>) => void;

interface SseApiRequestOptions<TMessageData, TBody extends ApiRequestBodyBase | undefined = undefined>
    extends ApiRequestOptions<TBody> {
    /**
     * The callback to handle each message from the server
     */
    onMessage: SseMessageCallback<TMessageData>;
    /**
     * The expected type of the final response
     * This will determine when the stream terminates and should have the final response included
     * If the request terminates without this, an error will be returned
     * @default "complete"
     */
    finalEventName?: string;
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
        signal: options.signal,
    };

    if (options.body instanceof FormData) {
        // If the body is a FormData, we don't set Content-Type, as the browser will set it with the correct boundary
        requestOptions.body = options.body;
        if (requestOptions.headers && "Content-Type" in requestOptions.headers) {
            delete requestOptions.headers["Content-Type"];
        }
    } else if (options.body !== undefined) {
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
 * @param eventName - When parsing a text/event-stream, the event name that is associated with the data
 * @returns - The parsed response, always extending ApiBaseResponse
 */
function parseResponse<TResponseData>(
    text: string,
    status: number,
    contentType: string = "",
    eventName?: string,
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

    if (status === 204 && text === "") {
        // 204 No Content, so we return null data
        return {
            success: true,
            data: null as unknown as TResponseData,
        };
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
    if (status !== 200 || eventName === "error") {
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
export async function apiRequest<TResponseData, TBody extends ApiRequestBodyBase | undefined = undefined>(
    options: ApiRequestOptions<TBody>,
): Promise<ApiBaseResponse<TResponseData>> {
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

/**
 * Generic function to make an API request to the server
 * Validates that the response matches our expected format
 * API errors should follow RFC 9457 "API Problem", sending an event with the name "error" will be treated like this
 * Requires the final message to match options.finalEventName, which will terminate the stream
 * @template TMessageData - The type of the response from the server for each message
 * @template TResponseData - The type of the response from the server for the final message
 * @template TBody - The type of the request body
 * @param options - Options for the API request
 * @returns - A typed response from the server including a success state
 */
export async function sseApiRequest<
    TMessageData,
    TResponseData,
    TBody extends ApiRequestBodyBase | undefined = undefined,
>(options: SseApiRequestOptions<TMessageData, TBody>): Promise<ApiBaseResponse<TResponseData>> {
    const request = buildRequest("text/event-stream", options);

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

    // If the response is a 200 and HTML, then we probably got given the offline page, return a more meaningful message instead
    // If there's no body (which can happen in some edge cases), return an unknown failure
    const contentType = response.headers.get("Content-Type") || "";
    if (!response.body || (response.status === 200 && contentType.includes("text/html"))) {
        return parseResponse<TResponseData>("", response.status, contentType);
    }

    // If the content type is json, handle as a normal request (it likely errored early)
    // depending on where the error was, the server may have provided a proper json-formatted error
    // otherwise, we'll just return an unknown failure like we usually do
    if (response.headers.get("content-type")?.includes("application/json")) {
        const text = await response.text();
        return parseResponse<TResponseData>(text, response.status);
    }

    // Parse the text/event-stream response as it comes in until it's closed or errors
    const reader = response.body.getReader();
    const decoder = new TextDecoder("utf-8");

    let buffer = "";
    let finalResponseData: string | undefined;
    while (finalResponseData === undefined) {
        // eslint-disable-next-line no-await-in-loop -- Must handle chunks sequentially
        const { done, value } = await reader.read();

        // If the stream ended normally
        if (done) {
            break;
        }

        // Decode the chunk into text and split it into individual events (always by \n\n)
        buffer += decoder.decode(value, { stream: true });
        const events = buffer.split("\n\n");

        // Process all complete events except possibly the last partial
        for (let i = 0; i < events.length - 1; i += 1) {
            const rawEvent = events[i].trim();
            if (rawEvent) {
                const { eventName, data } = parseSseEvent(rawEvent);

                if (eventName === (options.finalEventName ?? "complete")) {
                    // If the server sends the final event, close the stream
                    finalResponseData = data;
                    break;
                }

                const parsedData = parseResponse<TMessageData>(data, response.status, contentType, eventName);

                // If the parsed data indicates a failure, set it as the final response for now but let the stream keep going
                if (parsedData.success === false) {
                    finalResponseData = data;
                }

                options.onMessage(parsedData);
            }
        }

        // Keep the last (possibly incomplete) chunk in the buffer for next iteration
        buffer = events[events.length - 1];
    }

    // If the stream ended as "done" but we didn't get the final event,
    // something went wrong and we should return an unknown failure
    if (finalResponseData === undefined) {
        const failureResponse: ApiBaseResponseUnknownFailure = {
            success: false,
            responseStatus: response.status,
            detail: buffer,
            message: "Response stream ended unexpectedly",
        };
        return failureResponse;
    }

    // Otherwise, we got the final event, so stop reading and return it
    await reader.cancel();
    return parseResponse<TResponseData>(finalResponseData, response.status);
}

// Overrides for buildApiRequest to allow for an optional URL which changes the return type
export function buildApiRequest<TResponseData, TBody extends ApiRequestBodyBase | undefined = undefined>(
    options: Omit<ApiRequestOptions<TBody>, "body">,
): (body?: TBody) => Promise<ApiBaseResponse<TResponseData>>;

export function buildApiRequest<TResponseData, TBody extends ApiRequestBodyBase | undefined = undefined>(
    options: Omit<ApiRequestOptions<TBody>, "url" | "body"> & { url: null },
): (url: string, body?: TBody) => Promise<ApiBaseResponse<TResponseData>>;

/**
 * Builds an API request function with the given options
 * @param options - The options to define the API request, excluding the body
 * @returns - A function that takes an optional body and returns a promise that resolves to the response from the server
 * @see apiRequest for details on the options
 */
export function buildApiRequest<TResponseData, TBody extends ApiRequestBodyBase | undefined = undefined>(
    options: Omit<ApiRequestOptions<TBody>, "url" | "body"> & { url: string | null },
):
    | ((url: string, body?: TBody) => Promise<ApiBaseResponse<TResponseData>>)
    | ((body?: TBody) => Promise<ApiBaseResponse<TResponseData>>) {
    const targetUrl = options.url;
    if (targetUrl === null) {
        return (url: string, body?: TBody): Promise<ApiBaseResponse<TResponseData>> => {
            return apiRequest<TResponseData, TBody>({ ...options, url, body });
        };
    }

    return (body?: TBody): Promise<ApiBaseResponse<TResponseData>> => {
        return apiRequest<TResponseData, TBody>({ ...options, url: targetUrl, body });
    };
}
