export interface ActiveDropdown {
    id: string;
    content: HTMLElement;
    trigger: HTMLElement;
    close: (e?: MouseEvent) => void;
}
