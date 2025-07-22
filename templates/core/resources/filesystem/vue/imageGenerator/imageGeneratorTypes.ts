export type ImageRequest = {
    type: "request";
    entryKey: number;
    user: Pick<typeof window.schoolboxUser, "id" | "externalId" | "name">;
    prompt: string;
    attributes: Record<string, string | null>;
    previousResponseId: string | null;
};

export type ImageResponseSuccess = {
    type: "response";
    status: "success";
    entryKey: number;
    /* the openai response ID so we can reference it in the next chat */
    responseId: string;
    /* the image URL */
    src: string;
    /* a message from the AI, if any */
    message: string;
};

type ImageResponseError = {
    type: "response";
    status: "error";
    entryKey: number;
    responseId: string | null;
    message: string;
};

export type ImageResponseInProgress = {
    type: "response";
    status: "in_progress";
    entryKey: `loading-${number}`;
    responseId: string | null;
    src: string | null;
    message: string;
};

export type ImageResponse = ImageResponseSuccess | ImageResponseInProgress | ImageResponseError;

export type AiFeedMessage = ImageRequest | ImageResponse;

export type ImagePromptAttribute = {
    id: string;
    title: string;
    options: Record<string, string>;
    required: boolean;
};
