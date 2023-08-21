export interface ActiveDropdown {
    id: string;
    trigger: HTMLElement;
    content: HTMLElement;
    close: (e?: MouseEvent) => void;
}
