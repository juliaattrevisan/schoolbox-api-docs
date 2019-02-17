import * as React from 'react';
import classNames from 'classnames';

interface IEmptyState {
    /** Icon to show inside the empty state */
    icon: string;

    /** Removes the margin-bottom from the empty state */
    noMargin?: boolean;
}

const EmptyState: React.FunctionComponent<IEmptyState> = ({ children, icon, noMargin }) => {
    const emptyStateClasses = classNames({
        'empty-state': true,
        'no-margin': noMargin
    });

    return (
        <div className={emptyStateClasses}>
            <i className={`icon-${icon}`} />
            {children}
        </div>
    )
}

export { EmptyState }