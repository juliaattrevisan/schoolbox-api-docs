/**
 * A curriculum node
 */

export default interface Node {
    children: Node[] | null;
    code: string | null;
    description: string | null;
    enabled: boolean;
    id: number;
    parent: Node | null;
    name: string;
}
