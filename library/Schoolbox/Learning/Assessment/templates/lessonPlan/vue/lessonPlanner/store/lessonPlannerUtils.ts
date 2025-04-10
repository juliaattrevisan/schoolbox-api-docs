import type { LessonPlanField, LessonPlannerStoreField } from "../lessonPlannerTypes";

type LpGenerateResponseSuccess = {
    success: true;
    fields: Record<LessonPlanField["id"], string>;
};

type LpGenerateResponseFailure = {
    success: false;
    message: string;
};

/**
 * This is the final response output from the generateLessonPlan function, after parsing and validation has occured.
 * It is not necessarily the same format as the response from the server,
 * instead, this allows checking for a success or failure state in a typed/reliable manner.
 */
type LpGenerateResponse = LpGenerateResponseSuccess | LpGenerateResponseFailure;

export type LpGenerateApiFieldPayload = {
    id: LessonPlanField["id"];
    title: LessonPlanField["title"];
    description: LessonPlanField["description"];
    visibility: LessonPlanField["visibility"];
    /**
     * This changes based on what action is being taken (as per SA-19640):
     * See translateFieldForPayload for details
     */
    body: LessonPlanField["body"] | null;
    /**
     * Whether this field is to be regenerated (true) or only used as additional context (false)
     * This can be used to influence how the body above is represented in the AI prompt
     */
    selected: boolean;
};

type LpGenerateApiPayload = {
    /**
     * The current title of the lesson plan
     */
    title: string;
    /**
     * The course name/unit name/folder name/etc that this is being created for
     */
    context: string;
    /**
     * A list of curriculum node IDs that are selected for this lesson plan
     */
    curriculumNodeIds: number[];
    /**
     * The additional context/prompt refinement that the user has provided to assist the AI
     */
    promptRefinement: string;
    /**
     * All of the fields (in order) in the lesson plan,
     * and whether or not they are selected to be generated or not
     */
    fields: LpGenerateApiFieldPayload[];
};

/**
 * Translate a field into what the API payload expects
 * @param field - The base field info
 * @param selected - Whether the field is selected or not
 * @param generateForField - When generating/regenerating just one specific field
 * @returns The field data in the format for the payload
 */
export function translateFieldForPayload(
    field: LessonPlannerStoreField,
    selected: boolean,
    generateForField: LessonPlanField | null,
): LpGenerateApiFieldPayload {
    const baseFieldData = {
        id: field.id,
        title: field.title,
        description: field.description,
        visibility: field.visibility,
        selected,
    };

    /**
     * When (re)generating a single field on the create or modify page,
     * - If this is the field being generated, this is left BLANK (i.e. the AI should generate a new body from scratch)
     * - Otherwise, from all other fields, this should be the CURRENT body to use as additional context
     */
    if (generateForField) {
        if (field.id === generateForField.id) {
            return { ...baseFieldData, body: null };
        }
        return { ...baseFieldData, body: field.body };
    }

    /**
     * When (re)generating the entire lesson plan,
     * - If we are on the CREATE PAGE, i.e. field.lessonPlanTemplateField is undefined, then:
     *   - Provide the original template content, even if the fields were modified (i.e. a clean slate),
     *     so that the AI can be informed of a format to follow (i.e. dot points or a table which could be filled in)
     * - If we are on the MODIFY PAGE, i.e. field.lessonPlanTemplateField is defined, then:
     *   - leave the body BLANK, do not provide any template content or data for the fields,
     *     on this page we don't have the original template content to provide, so the AI can't use it as context
     */
    if (field.lessonPlanTemplateField === undefined) {
        return { ...baseFieldData, body: field.initialBody };
    }

    return { ...baseFieldData, body: null };
}

/**
 * Generate each field's new body content from the AI for the lesson plan
 * @param endpoint - The endpoint to fetch
 * @param payload - The data to send in the request
 * @returns The response from the fetch
 */
export async function generateLessonPlan(endpoint: string, payload: LpGenerateApiPayload): Promise<LpGenerateResponse> {
    let responseData: LpGenerateResponse = { success: false, message: "An unknown error occurred" };

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

        // If we got some form of response, set the initial message to that
        responseData.message = responseText;

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

        // The final fields to return should be a map of the field IDs to each new body
        const fields: Record<LessonPlanField["id"], string> = {};

        // We need to each field in the response and ensure it exists along with a string value
        const expectedFields = payload.fields.filter((field) => field.selected);
        const newFields = Object.entries(responseJson).map(([key, value]: [string, unknown]) => ({ key, value }));

        expectedFields.forEach((field) => {
            const newFieldBody = newFields.find(({ key }) => key.toString() === field.id.toString());

            if (!newFieldBody) {
                throw new Error(`Invalid response, missing field "${field.title}"`);
            }

            if (typeof newFieldBody.value !== "string") {
                throw new Error(`Invalid response for field "${field.title}"`);
            }

            fields[field.id] = newFieldBody.value;
        });

        responseData = { success: true, fields };
    } catch (e) {
        responseData = { success: false, message: (e as Error).message };
    }

    // If the response was unsuccessful and had no message, set a default message
    if (responseData.success === false && responseData.message === "") {
        responseData.message = "An unknown error occurred";
    }

    return responseData;
}
