/*
 * Colour parsing and transformation utilities.
 */

export type ParsedColor = {
    r: number;
    g: number;
    b: number;
    a: number;
};

type HslColor = {
    h: number;
    s: number;
    l: number;
    a: number;
};

type DerivedColorTarget = {
    cssVar: string;
    color: ParsedColor;
};

/**
 * Clamp a number between two bounds.
 * @param value The value to clamp.
 * @param min The minimum allowed value.
 * @param max The maximum allowed value.
 * @returns The clamped value.
 */
function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

/**
 * Round a number to 2 decimal places to match the server-side CSS output.
 * @param value The value to round.
 * @returns The rounded value.
 */
function roundColorValue(value: number): number {
    return Math.round(value * 100) / 100;
}

/**
 * Convert RGBA channels into HSLA values.
 * @param color The parsed colour.
 * @returns HSLA values in the same scale as the server CSS.
 */
function toHsl(color: ParsedColor): HslColor {
    const red = color.r / 255;
    const green = color.g / 255;
    const blue = color.b / 255;
    const max = Math.max(red, green, blue);
    const min = Math.min(red, green, blue);
    const delta = max - min;
    let hue = 0;

    if (delta !== 0) {
        if (max === red) {
            hue = ((green - blue) / delta) % 6;
        } else if (max === green) {
            hue = (blue - red) / delta + 2;
        } else {
            hue = (red - green) / delta + 4;
        }
    }

    hue = Math.round(hue * 60);
    if (hue < 0) {
        hue += 360;
    }

    const lightness = (max + min) / 2;
    const saturation = delta === 0 ? 0 : delta / (1 - Math.abs(2 * lightness - 1));

    return {
        h: roundColorValue(hue),
        s: roundColorValue(saturation * 100),
        l: roundColorValue(lightness * 100),
        a: roundColorValue(color.a),
    };
}

/**
 * Convert an HSL colour to RGBA channels.
 * @param color The HSLA colour to convert.
 * @returns Parsed RGBA channels.
 */
function fromHsl(color: HslColor): ParsedColor {
    const saturation = clamp(color.s / 100, 0, 1);
    const lightness = clamp(color.l / 100, 0, 1);
    const chroma = (1 - Math.abs(2 * lightness - 1)) * saturation;
    const huePrime = (((color.h % 360) + 360) % 360) / 60;
    const x = chroma * (1 - Math.abs((huePrime % 2) - 1));
    let red = 0;
    let green = 0;
    let blue = 0;

    if (huePrime >= 0 && huePrime < 1) {
        red = chroma;
        green = x;
    } else if (huePrime < 2) {
        red = x;
        green = chroma;
    } else if (huePrime < 3) {
        green = chroma;
        blue = x;
    } else if (huePrime < 4) {
        green = x;
        blue = chroma;
    } else if (huePrime < 5) {
        red = x;
        blue = chroma;
    } else {
        red = chroma;
        blue = x;
    }

    const match = lightness - chroma / 2;

    return {
        r: Math.round((red + match) * 255),
        g: Math.round((green + match) * 255),
        b: Math.round((blue + match) * 255),
        a: clamp(color.a, 0, 1),
    };
}

/**
 * Calculate the relative luminance for a colour.
 * @param color The colour to evaluate.
 * @returns Relative luminance in the range 0-1.
 */
function getRelativeLuminance(color: ParsedColor): number {
    const channelValues = [color.r, color.g, color.b].map((channel) => {
        const value = channel / 255;
        return value <= 0.03928 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * channelValues[0] + 0.7152 * channelValues[1] + 0.0722 * channelValues[2];
}

/**
 * Saturate a colour by a decimal amount.
 * @param color The colour to saturate.
 * @param amount The amount to add to saturation.
 * @returns The saturated colour.
 */
function saturateColor(color: ParsedColor, amount: number): ParsedColor {
    const hsl = toHsl(color);

    return fromHsl({
        ...hsl,
        s: clamp(hsl.s / 100 + amount, 0, 1) * 100,
    });
}

/**
 * Flatten a translucent colour onto a background.
 * @param color The foreground colour.
 * @param background The background colour.
 * @returns The flattened opaque colour.
 */
function flattenAlpha(color: ParsedColor, background: ParsedColor): ParsedColor {
    const alpha = clamp(color.a, 0, 1);

    return {
        r: Math.round(color.r * alpha + background.r * (1 - alpha)),
        g: Math.round(color.g * alpha + background.g * (1 - alpha)),
        b: Math.round(color.b * alpha + background.b * (1 - alpha)),
        a: 1,
    };
}

/**
 * Parse a supported colour string into RGBA components.
 * @param colorValue The incoming colour string.
 * @returns Parsed RGBA channels, or null if unsupported.
 */
export function parseColor(colorValue: string | null): ParsedColor | null {
    if (colorValue === null) {
        return null;
    }

    const value = colorValue.replace(/\s+/g, "").trim();

    if (value === "") {
        return null;
    }

    const hexMatch = value.match(/^#?([0-9a-f]{6})([0-9a-f]{2})?$/i);
    if (hexMatch) {
        const [, rgbHex, alphaHex] = hexMatch;
        return {
            r: Number.parseInt(rgbHex.slice(0, 2), 16),
            g: Number.parseInt(rgbHex.slice(2, 4), 16),
            b: Number.parseInt(rgbHex.slice(4, 6), 16),
            a: alphaHex ? roundColorValue(Number.parseInt(alphaHex, 16) / 255) : 1,
        };
    }

    const rgbMatch = value.match(/^rgba?\((\d+),(\d+),(\d+)(?:,([\d.]+))?\)$/i);
    if (rgbMatch) {
        const [, r, g, b, a] = rgbMatch;
        return {
            r: clamp(Number.parseInt(r, 10), 0, 255),
            g: clamp(Number.parseInt(g, 10), 0, 255),
            b: clamp(Number.parseInt(b, 10), 0, 255),
            a: a === undefined ? 1 : clamp(Number.parseFloat(a), 0, 1),
        };
    }

    return null;
}

/**
 * Pick black or white text for a background, matching the PHP threshold logic.
 * @param color The background colour.
 * @param light The light colour override.
 * @param dark The dark colour override.
 * @param threshold The light/dark threshold.
 * @returns The contrasting colour.
 */
export function calculateTextColor(
    color: ParsedColor,
    light: ParsedColor = { r: 255, g: 255, b: 255, a: 1 },
    dark: ParsedColor = { r: 0, g: 0, b: 0, a: 1 },
    threshold: number = 0.45,
): ParsedColor {
    return getRelativeLuminance(color) < threshold ? light : dark;
}

/**
 * Calculate a derived foreground colour matching the PHP transformer.
 * @param background The background colour.
 * @param opacity The overlay opacity.
 * @param saturation The extra saturation to apply.
 * @param light The light colour override.
 * @param dark The dark colour override.
 * @returns The derived foreground colour.
 */
export function calculateForegroundColor(
    background: ParsedColor,
    opacity: number = 0.35,
    saturation: number = 0.1,
    light?: ParsedColor,
    dark?: ParsedColor,
): ParsedColor {
    const textColor = calculateTextColor(background, light, dark, 0.5);
    const translucent = {
        ...textColor,
        a: opacity,
    };

    return saturateColor(flattenAlpha(translucent, background), saturation);
}

/**
 * Get the CSS custom property values for a colour, including component channels.
 * @param cssVar The base CSS custom property name.
 * @param color The parsed colour.
 * @returns CSS property name/value pairs to apply.
 */
export function getColorVariableEntries(cssVar: string, color: ParsedColor): [string, string][] {
    const hsl = toHsl(color);

    return [
        [`${cssVar}-r`, String(color.r)],
        [`${cssVar}-g`, String(color.g)],
        [`${cssVar}-b`, String(color.b)],
        [`${cssVar}-h`, String(hsl.h)],
        [`${cssVar}-s`, `${hsl.s}%`],
        [`${cssVar}-l`, `${hsl.l}%`],
        [`${cssVar}-a`, String(hsl.a)],
        [cssVar, `hsla(${hsl.h}, ${hsl.s}%, ${hsl.l}%, ${hsl.a})`],
    ];
}

/**
 * Get all CSS custom property names used by a colour.
 * @param cssVar The base CSS custom property name.
 * @returns The CSS custom property names that should be cleared together.
 */
export function getColorVariableNames(cssVar: string): string[] {
    return [
        cssVar,
        `${cssVar}-r`,
        `${cssVar}-g`,
        `${cssVar}-b`,
        `${cssVar}-h`,
        `${cssVar}-s`,
        `${cssVar}-l`,
        `${cssVar}-a`,
    ];
}

/**
 * Get the derived CSS variable names for a base skin variable.
 * @param cssVar The base CSS custom property name.
 * @returns Derived CSS custom property names.
 */
export function getDerivedColorVariableNames(cssVar: string): string[] {
    switch (cssVar) {
        case "--body-background":
            return ["--body-foreground", "--body-foreground-h1"];
        case "--navigation-background":
            return ["--navigation-foreground-highlight"];
        default:
            return [];
    }
}

/**
 * Get the derived colours for a base skin variable.
 * @param cssVar The base CSS custom property name.
 * @param color The base parsed colour.
 * @returns Derived CSS variable names with their parsed colours.
 */
export function getDerivedColorTargets(cssVar: string, color: ParsedColor): DerivedColorTarget[] {
    switch (cssVar) {
        case "--body-background":
            return [
                {
                    cssVar: "--body-foreground",
                    color: calculateForegroundColor(color),
                },
                {
                    cssVar: "--body-foreground-h1",
                    color: calculateForegroundColor(color, 0.855),
                },
            ];
        case "--navigation-background":
            return [
                {
                    cssVar: "--navigation-foreground-highlight",
                    color: calculateTextColor(color, undefined, calculateForegroundColor(color, 0.65)),
                },
            ];
        default:
            return [];
    }
}
