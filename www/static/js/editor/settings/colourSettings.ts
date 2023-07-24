/**
 * Swatch colours to be shown in all CKEditor colour pickers
 *
 * Includes 5 shades of gray and 5 colours from the school's skin config
 */
const globalSwatches = [
    ...[
        {
            color: "hsl(0, 0%, 100%)",
            label: "White",
        },
        {
            color: "hsl(0, 0%, 90%)",
            label: "Light grey",
        },
        {
            color: "hsl(0, 0%, 60%)",
            label: "Grey",
        },
        {
            color: "hsl(0, 0%, 30%)",
            label: "Dim grey",
        },
        {
            color: "hsl(0, 0%, 0%)",
            label: "Black",
        },
    ],
    ...window.sbProps.colourDefaults.skin.slice(0, 5).map((swatch: string) => {
        return {
            color: swatch,
            label: "School Colours",
            hasBorder: true,
        };
    }),
];

/**
 * Swatch colours to be shown in the CKEditor font colour picker
 */
export const fontColourSwatches = [
    ...[
        {
            color: "hsl(0, 70%, 50%)",
            label: "Red",
        },
        {
            color: "hsl(30, 70%, 50%)",
            label: "Orange",
        },
        {
            color: "hsl(60, 70%, 50%)",
            label: "Yellow",
        },
        {
            color: "hsl(90, 70%, 50%)",
            label: "Light green",
        },
        {
            color: "hsl(120, 70%, 50%)",
            label: "Green",
        },
        {
            color: "hsl(150, 70%, 50%)",
            label: "Aquamarine",
        },
        {
            color: "hsl(180, 70%, 50%)",
            label: "Turquoise",
        },
        {
            color: "hsl(210, 70%, 50%)",
            label: "Light blue",
        },
        {
            color: "hsl(240, 70%, 50%)",
            label: "Blue",
        },
        {
            color: "hsl(270, 70%, 50%)",
            label: "Purple",
        },
    ],
    ...globalSwatches,
];

/**
 * Swatch colours to be shown in the CKEditor font background colour picker
 *
 * Slightly adjusted to be more saturated and lighter compared to fontColourSwatches for better contrast
 */
export const fontBackgroundColourSwatches = [
    ...[
        {
            color: "hsl(0, 100%, 60%)",
            label: "Red",
        },
        {
            color: "hsl(30, 100%, 60%)",
            label: "Orange",
        },
        {
            color: "hsl(60, 100%, 60%)",
            label: "Yellow",
        },
        {
            color: "hsl(90, 100%, 60%)",
            label: "Light green",
        },
        {
            color: "hsl(120, 100%, 60%)",
            label: "Green",
        },
        {
            color: "hsl(150, 100%, 60%)",
            label: "Aquamarine",
        },
        {
            color: "hsl(180, 100%, 60%)",
            label: "Turquoise",
        },
        {
            color: "hsl(210, 100%, 60%)",
            label: "Light blue",
        },
        {
            color: "hsl(240, 100%, 60%)",
            label: "Blue",
        },
        {
            color: "hsl(270, 100%, 60%)",
            label: "Purple",
        },
    ]
    ...globalSwatches,
];

/**
 * Swatch colours to be shown in the CKEditor table border colour picker
 *
 * Uses the same colours as fontBackgroundColourSwatches
 */
export const tableBorderColourSwatches = fontBackgroundColourSwatches;

/**
 * Swatch colours to be shown in the CKEditor table background colour picker
 *
 * Adjusted to be very light to better suit cell backgrounds and provide contrast with text
 */
export const tableBackgroundColourSwatches = [
    ...[
        {
            color: "hsl(0, 100%, 95%)",
            label: "Red",
        },
        {
            color: "hsl(30, 100%, 95%)",
            label: "Orange",
        },
        {
            color: "hsl(60, 100%, 95%)",
            label: "Yellow",
        },
        {
            color: "hsl(90, 100%, 95%)",
            label: "Light green",
        },
        {
            color: "hsl(120, 100%, 95%)",
            label: "Green",
        },
        {
            color: "hsl(150, 100%, 95%)",
            label: "Aquamarine",
        },
        {
            color: "hsl(180, 100%, 95%)",
            label: "Turquoise",
        },
        {
            color: "hsl(210, 100%, 95%)",
            label: "Light blue",
        },
        {
            color: "hsl(240, 100%, 95%)",
            label: "Blue",
        },
        {
            color: "hsl(270, 100%, 95%)",
            label: "Purple",
        },
    ]
    ...globalSwatches,
];
