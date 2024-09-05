/**
 * HTML canvas util methods to get the object, size and clear once used.
 */

export type CanvasDimensions = {
    width: number;
    height: number;
};

/**
 * Get the dimensions of the canvas, which can change depending on screen dimensions
 * @param canvas = the canvas element to get the dimensions of
 * @returns - the width and height of the canvas
 */
export function getCanvasDimensions(canvas: HTMLCanvasElement): CanvasDimensions {
    return {
        width: canvas.width,
        height: canvas.height,
    };
}

/**
 * Get the context
 * https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
 * @param canvas - the canvas element to get the context of
 * @returns - the context of the canvas
 */
export function getContext(canvas: HTMLCanvasElement): CanvasRenderingContext2D | null {
    return canvas.getContext("2d");
}

/**
 * Ensure there's no left over animation if user is moving through the new rewards quickly
 * @param canvas - the canvas element to clear
 */
export function clearCanvas(canvas: HTMLCanvasElement): void {
    const canvasDimensions = getCanvasDimensions(canvas);
    const context = getContext(canvas);
    context?.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
}
