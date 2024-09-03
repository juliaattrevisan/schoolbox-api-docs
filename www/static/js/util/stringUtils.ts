/**
 * Any utils that manipulate strings
 */

/**
 * shorten a string if it's over the max length
 * @param string - the string to shorten
 * @param maxLength - the max length the string should be shortened to
 * @returns shortened string
 */
export function shortenLength(string: string, maxLength: number): string {
    return string.length < maxLength ? string : `${string.substring(0, maxLength)}...`;
}

/**
 * capitalize the first letter of a string
 * @param string - the string to capitalize
 * @returns string with first letter capitalized
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
 * @param startIndex - the start index of the section to replace
 * @param endIndex - the end index of the section to replace
 * @param inputString - the string to replace the section in
 * @param replacementString - the string to replace the section with
 * @returns - the updated string
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
 * @param sectionsToReplace - the sections to replace
 * @param inputString - the string to replace sections in
 * @param replacementString - the string to replace the sections with
 * @returns - the updated string
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
