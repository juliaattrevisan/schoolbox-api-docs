/**
 * Any DOM manipulation functions specific to Learnosity
 */

/**
 * To move the marks (which are in the title div) out of the response container
 * we need to add an attribute to each title div so it can be properly targeted, as they all just have the same class name
 * @param {string} reportId - the report id
 */
export function addTargetsToMarks(reportId: string) {
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
