import * as React from "react";
import { Card } from "@schoolbox/Card"

interface IDetail {
  exampleProp: string;
}

class Detail extends React.Component<IDetail> {
  public render() {
    return (
      <>
        <Card id="1" title="Example imported component" meta={`Example prop: ${this.props.exampleProp}`} />
      </>
    );
  }
}

export default Detail;
