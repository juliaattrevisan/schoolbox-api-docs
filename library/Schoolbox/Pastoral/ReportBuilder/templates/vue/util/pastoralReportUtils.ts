/**
 * Any utility functions for the Pastoral Report.
 */

import type { User } from "@schoolboxLibrary/types/User";
import type { Tab, TableHeader, RowOptions, TypeSubType, RowKeys } from "../../js/types/pastoralReportTypes";
import { TabTitle } from "../../js/types/pastoralReportTypes";

/**
 * Returns the row id, currently if there's a 'user' key in rowData,
 * it'll return the user's id, otherwise it defaults to 0.
 * @returns - The id of the row, or 0 if not defined.
 */
export function getRowKeys(rowData: { [key: string]: RowOptions }): RowKeys {
    // check if there's a 'user' key in rowData and return its id if it exists
    const user = rowData.user as User;
    if (user) {
        return {
            student_id: user.id || 0,
        };
    }
    // otherwise, check for 'type_subtype' and return its ids if it exists
    const typeSubtype = rowData.type_subtype as TypeSubType;
    if (typeSubtype) {
        return {
            type_id: typeSubtype.typeId || 0,
            subtype_id: typeSubtype.subtypeId || 0,
        };
    }
    return {};
}

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
    // Return the type/sub-type and student if student cell selected
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
    // Return the type/sub-type or just the month
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
