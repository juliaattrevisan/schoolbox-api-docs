import * as React from 'react';

interface IActions {
    children: React.ReactNode[];
}

/**
 * Container for a list of Action components.
 */
const Actions: React.SFC<IActions> = ({ children }) => (
    <nav>{children}</nav>
)

export { Actions }