export type ImageRequest = {
    type: "request";
    entryKey: number;
    user: Pick<typeof window.schoolboxUser, "id" | "externalId" | "name">;
    prompt: string;
    imageSize: string | null;
    imageStyle: string | null;
    previousResponseId: string | null;
};

type ImageResponseSuccess = {
    type: "response";
    status: "success";
    entryKey: number;
    /* the openai response ID so we can reference it in the next chat */
    responseId: string;
    /* the image URL */
    src: string;
    /* a message from the AI, if any */
    message: string | null;
};

type ImageResponseError = {
    type: "response";
    status: "error";
    entryKey: number;
    responseId: string | null;
    message: string;
};

export type ImageResponse = ImageResponseSuccess | ImageResponseError;

export type AiFeedMessage = ImageRequest | ImageResponse;
