export type ImageRequest = {
    type: "request";
    user: Pick<typeof window.schoolboxUser, "id" | "externalId" | "name">;
    prompt: string;
    imageSize: string | null;
    imageStyle: string | null;
    previousResponseId: string | null;
};

type ImageResponseSuccess = {
    type: "response";
    status: "success";
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
    responseId: string | null;
    message: string;
};

export type ImageResponse = ImageResponseSuccess | ImageResponseError;

export type AiFeedMessage = ImageRequest | ImageResponse;
