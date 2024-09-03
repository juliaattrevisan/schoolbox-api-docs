/**
 * Any DOM manipulation functions specific to Learnosity
 */

/**
 * To move the marks (which are in the title div) out of the response container
 * we need to add an attribute to each title div so it can be properly targeted, as they all just have the same class name
 * @param reportId - the report id
 */
export function addTargetsToMarks(reportId: string): void {
    const report = document.getElementById(reportId);
    const reportItemResponseIds: string[] = [];
    const reportItemResponses = report?.querySelectorAll("[data-reference]");
    reportItemResponses?.forEach((response) => {
        reportItemResponseIds.push((response as HTMLDivElement).dataset.reference as string);
    });
    const reportItemTitles = report?.querySelectorAll(".lrn-report-item-title");
    reportItemTitles?.forEach((title, index) => {
        (title as HTMLDivElement).dataset.referenceTitle = reportItemResponseIds[index];
    });
}

/**
 * We should also set textareas to readonly so that they can't trigger browser save changes
 * even through they are already set as disabled
 * @param reportId - the report id
 */
export function disableTextAreas(reportId: string): void {
    const report = document.getElementById(reportId);
    const plainTextAreas = report?.querySelectorAll(".lrn_plaintextarea, .lrn_textinput");
    plainTextAreas?.forEach((textArea) => {
        const textInput = textArea.querySelector("textarea, input");
        if (textInput) {
            textInput.setAttribute("readonly", "readonly");
        }
    });
}
