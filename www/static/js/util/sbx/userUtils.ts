/**
 * Any utility functions related to SBX users
 */

import type { User } from "@schoolboxLibrary/types/User";

/**
 * There's several options in the system for user first names, so this utility
 * function attempts to get the best available first name for a user object.
 * @param user - The user object
 * @returns The best available first name or an empty string if none found
 */
export function getFirstName(user: User | null): string {
    if (user === null) {
        return "";
    }
    if (user.givenName) {
        return user.givenName;
    }
    if (user.preferredName) {
        return user.preferredName;
    }
    if (user.firstname) {
        return user.firstname;
    }
    return "";
}
