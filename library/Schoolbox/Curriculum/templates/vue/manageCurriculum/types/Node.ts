/**
 * A curriculum node
 */

export default interface Node {
    children: Node[] | null;
    code: string;
    description: string;
    enabled: boolean;
    id: number;
    parent: Node | null;
    name: string;
}
