/**
 * Builds the unconfirmed candidate details icon title.
 * @param missingFields Missing candidate detail field names.
 * @returns The title text to display.
 */
export function candidateDetailsTitle(missingFields: string[]): string {
    if (missingFields.length === 0) {
        return "Missing required candidate details.";
    }

    return `Missing required candidate details: ${missingFields.join(", ")}.`;
}
