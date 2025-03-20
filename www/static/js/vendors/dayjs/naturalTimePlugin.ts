import strtotime from "locutus/php/datetime/strtotime";
import type { PluginFunc, Dayjs as DayjsInstance } from "dayjs";

/**
 * Smooth out formats so strtotime digests times more easily
 * @param text - The natural language date input
 * @returns - The denaturalized string
 */
function denaturalize(text: string): string {
    return text
        .trim()
        .toLowerCase()
        .replace(/\s+/g, " ")
        .replace(/^in\s+/g, "")
        .replace(/\s+from\s+now$/g, "")
        .replace(/\s+at\s+/g, "")
        .replace(/\s+on\s+/g, "")
        .replace(/,\s/g, "")
        .replace(/zero/g, "0")
        .replace(/one/g, "1")
        .replace(/two/g, "2")
        .replace(/three/g, "3")
        .replace(/four/g, "4")
        .replace(/five/g, "5")
        .replace(/six/g, "6")
        .replace(/seven/g, "7")
        .replace(/eight/g, "8")
        .replace(/nine/g, "9")
        .replace(/ten/g, "10")
        .replace(/eleven/g, "11")
        .replace(/twelve/g, "12")
        .replace(/\//g, "-")
        .replace(/\s+/g, " ")
        .replace(/\ban\b/g, "1") // replace "an" with "1"
        .replace(/\ba\b/g, "1") // replace "a" with "1"
        .replace(/\band\b/g, ","); // replace "and" with ","
}

/**
 * Custom dayjs plugin that allows parsing natural language dates
 * For example, "in 2 hours", "tomorrow", "5 days ago", etc
 * @param option - The plugin options
 * @param dayjsClass - The dayjs class
 * @param dayjsFactory - The dayjs factory
 */
const strToTimePlugin: PluginFunc = (option, dayjsClass, dayjsFactory) => {
    Object.defineProperty(dayjsFactory, "parseNatural", {
        value: (text: string): DayjsInstance | false => {
            const denaturalized = denaturalize(text);
            const timestamp = strtotime(denaturalized);
            return timestamp ? dayjsFactory(timestamp * 1000) : false;
        },
    });
};

declare module "dayjs" {
    /**
     * Parse a user-friendly date string into a Dayjs object
     * For example, "in 2 hours", "tomorrow", "5 days ago", etc
     * @param text - The natural language date input
     * @returns - The Dayjs object or false if the input could not be parsed
     */
    export const parseNatural: (text: string) => DayjsInstance | false;
}

export default strToTimePlugin;
