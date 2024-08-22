/**
 * Any utils that manipulate strings
 */

/**
 * shorten a string if it's over the max length
 * @param {string} string - the string to shorten
 * @param {number} maxLength - the max length the string should be shortened to
 * @returns {string} shortened string
 */
export function shortenLength(string: string, maxLength: number): string {
    return string.length < maxLength ? string : `${string.substring(0, maxLength)}...`;
}

/**
 * capitalize the first letter of a string
 * @param {string} string - the string to capitalize
 * @returns {string} string with first letter capitalized
 */
export function capitalizeFirstLetter(string: string): string {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

/**
 * A start and end index of a section of a string
 */
export type StringSection = {
    startIndex: number;
    endIndex: number;
};

/**
 * Replaces a section of a string with a replacement string and returns the updated string
 * @param { number } startIndex - the start index of the section to replace
 * @param { number } endIndex - the end index of the section to replace
 * @param { string } inputString - the string to replace the section in
 * @param { string } replacementString - the string to replace the section with
 * @returns { string } - the updated string
 */
export function replaceStringSection(
    startIndex: number,
    endIndex: number,
    inputString: string,
    replacementString: string,
): string {
    const replacedString =
        inputString.substring(0, startIndex) + replacementString + inputString.substring(endIndex + 1);
    return replacedString;
}

/**
 * Replaces all the sections of a string with a replacement string and returns the updated string
 * @param { StringSection[] } sectionsToReplace - the sections to replace
 * @param { string } inputString - the string to replace sections in
 * @param { string } replacementString - the string to replace the sections with
 * @returns { string } - the updated string
 */
export function replaceAllStringSections(
    sectionsToReplace: StringSection[],
    inputString: string,
    replacementString: string,
): string {
    let newString = inputString;
    let stringAtSection = "";
    sectionsToReplace.forEach((section, index) => {
        if (index === 0) {
            stringAtSection = inputString.substring(section.startIndex, section.endIndex + 1);
            newString = replaceStringSection(section.startIndex, section.endIndex, newString, replacementString);
        } else {
            // after the first replacement, the indexes will be off by the length of the replacement string
            newString = replaceStringSection(
                section.startIndex + index * (replacementString.length - stringAtSection.length),
                section.endIndex + index * (replacementString.length - stringAtSection.length),
                newString,
                replacementString,
            );
        }
    });
    return newString;
}
