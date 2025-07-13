export type ImageRequest = {
    type: "request";
    user: Pick<typeof window.schoolboxUser, "id" | "externalId" | "name">;
    prompt: string;
    imageSize: string | null;
    imageStyle: string | null;
    previousResponseId: string | null;
};

export type ImageResponse = {
    type: "response";
    /* the openai response ID so we can reference it in the next chat */
    responseId: string;
    /* the image URL */
    src: string;
    /* a message from the AI, if any */
    message: string;
};

export type AiFeedMessage = ImageRequest | ImageResponse;
