/**
 * HTML canvas util methods to get the object, size and clear once used.
 */

export type CanvasDimensions = {
    width: number;
    height: number;
};

/**
 * Get the dimensions of the canvas, which can change depending on screen dimensions
 * @param {HTMLCanvasElement} canvas = the canvas element to get the dimensions of
 * @returns {CanvasDimensions} - the width and height of the canvas
 */
export function getCanvasDimensions(canvas: HTMLCanvasElement) {
    return {
        width: canvas.width,
        height: canvas.height,
    };
}

/**
 * Get the context
 * https://developer.mozilla.org/en-US/docs/Web/API/CanvasRenderingContext2D
 * @param {HTMLCanvasElement} canvas - the canvas element to get the context of
 * @returns {CanvasRenderingContext2D|null} - the context of the canvas
 */
export function getContext(canvas: HTMLCanvasElement) {
    return canvas.getContext("2d");
}

/**
 * Ensure there's no left over animation if user is moving through the new rewards quickly
 * @param {HTMLCanvasElement} canvas - the canvas element to clear
 */
export function clearCanvas(canvas: HTMLCanvasElement) {
    const canvasDimensions = getCanvasDimensions(canvas);
    const context = getContext(canvas);
    context?.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
}
