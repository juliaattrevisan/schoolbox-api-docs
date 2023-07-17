/**
 * HTML canvas util methods to get the object, size and clear once used.
 */

/**
 * Get the dimensions of the canvas, which can change depending on screen dimensions
 * @param canvas { HTMLCanvasElement }
 * @return { Number, Number }
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
 *
 * @param canvas { HTMLCanvasElement }
 * @return { CanvasRenderingContext2D|null }
 */
export function getContext(canvas: HTMLCanvasElement) {
    return canvas.getContext("2d");
}

/**
 * Ensure there's no left over animation if user is moving through the new rewards quickly
 * @param canvas { HTMLCanvasElement }
 */
export function clearCanvas(canvas: HTMLCanvasElement) {
    const canvasDimensions = getCanvasDimensions(canvas);
    const context = getContext(canvas);
    context?.clearRect(0, 0, canvasDimensions.width, canvasDimensions.height);
}
