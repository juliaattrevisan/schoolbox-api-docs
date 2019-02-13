import * as React from 'react';

interface IActions {
    children: any; // TODO: what type to use here
}

/**
 * Container for a list of Action components.
 */
const Actions: React.SFC<IActions> = ({ children }) => (
    <nav>{children}</nav>
)

export { Actions, IActions }