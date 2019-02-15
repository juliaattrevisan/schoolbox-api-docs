import * as React from 'react';

interface IActions {
    children: JSX.Element[];
}

/**
 * Container for a list of Action components.
 */
const Actions: React.FunctionComponent<IActions> = ({ children }) => (
    <nav>{children}</nav>
)

export { Actions, IActions }