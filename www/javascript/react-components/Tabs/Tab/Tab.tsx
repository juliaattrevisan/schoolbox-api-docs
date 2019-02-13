import * as React from "react";
import { TabPanel as ReactTabPanel } from "react-tabs";

interface ITab {
    /** Unique ID for the tab */
    id: string;

    /** The title for the tab (couldn't use title because of conflict with the html attribute) */
    tab: string;

    /** Content for the tab */
    children: any;
}

/**
 * A single tab (used inside a Tabs component)
 */
class Tab extends React.Component<ITab> {
    private static tabsRole: string = "TabPanel";

    public render() {
        return (
            <ReactTabPanel id="{this.props.id}" className="tabs-content no-margin" {...this.props}>{this.props.children}</ReactTabPanel>
        )
    }
}

export { Tab, ITab }