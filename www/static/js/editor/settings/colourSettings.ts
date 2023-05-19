/**
 * Default swatches for font, font background, and table color pickers
 *
 * Pulls from window.sbProps.colourDefaults which includes 12 swatches from skin config, as well as some custom swatches
 * Then, add 3 more swatches to complete the grid in this use case.
 */
export const defaultSwatches = [
    ...window.sbProps.colourDefaults.swatches.map((swatch: string) => {
        return {
            color: swatch,
            label: swatch,
            hasBorder: true
        }
    }),
    ...[
        {
            color: 'hsl(0, 0%, 30%)',
            label: 'hsl(0, 0%, 30%)'
        },
        {
            color: 'hsl(0, 0%, 60%)',
            label: 'hsl(0, 0%, 60%)'
        },
        {
            color: 'hsl(0, 0%, 90%)',
            label: 'hsl(0, 0%, 90%)'
        },
    ]
];
