/**
 * Any utility functions for the Pastoral Report.
 */

import type { User } from "@schoolboxLibrary/types/User";
import type { Tab, TableHeader, RowOptions, TypeSubType } from "../../js/types/pastoralReportTypes";
import { TabTitle } from "../../js/types/pastoralReportTypes";

/**
 * Get current metadata for the current modal selection,
 * which will vary depending on the table and current cell selection
 * @returns An array of metadata strings.
 */
export function getMeta(
    selectedTab: Tab | null,
    header: TableHeader,
    rowData?: { [key: string]: RowOptions },
): string[] {
    const meta = [];
    if (selectedTab?.title === TabTitle.STUDENT_BY_TYPE) {
        meta.push(`Type: ${header.title}`);
        if (header.subTitle) {
            meta.push(`Sub-Type: ${header.subTitle}`);
        }
        if (rowData !== undefined && rowData.user) {
            const user = rowData.user as User;
            meta.push(`Student: ${user.firstName} ${user.lastName}`);
        }
        return meta;
    }
    if (selectedTab?.title === TabTitle.TYPE_BY_MONTH) {
        if (rowData !== undefined && rowData.type_subtype) {
            const typeSubtype = rowData.type_subtype as TypeSubType;
            meta.push(`Type: ${typeSubtype.type}`);
            if (typeSubtype.subtype) {
                meta.push(`Sub-Type: ${typeSubtype.subtype}`);
            }
        }
        meta.push(`Month: ${header.title}`);
        return meta;
    }
    return [];
}
