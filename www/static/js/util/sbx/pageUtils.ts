/**
 * Utilities to help with page-specific tasks
 */

/**
 * Expose sbProps so we can import it in TS/Vue files
 * Notably in a vue <template> we can't directly use window.sbProps
 * so this avoids needing to create a computed property in every component just to access sbProps
 */
export const { sbProps } = window;
