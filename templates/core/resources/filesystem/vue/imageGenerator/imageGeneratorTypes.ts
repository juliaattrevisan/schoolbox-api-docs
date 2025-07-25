import type { FileDropFile } from "@js/resource/fileDropTypes";
import type { ImageGeneratorApiPayload } from "./store/imageGeneratorPayload";

type Image = {
    b64: string;
    name: string;
    mime: "image/png"; // currently always PNG, but here if we want to support other formats in the future
};

export type ImageRequest = {
    type: "request";
    entryKey: number;
    user: Pick<typeof window.schoolboxUser, "id" | "externalId" | "name">;
    payload: ImageGeneratorApiPayload;
    file: FileDropFile | null;
};

export type ImageResponseSuccess = {
    type: "response";
    status: "success";
    entryKey: number;
    /* the openai response ID so we can reference it in the next chat */
    responseId: string;
    /* the base64-encoded image */
    image: Image | null;
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
    image: Image | null;
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
