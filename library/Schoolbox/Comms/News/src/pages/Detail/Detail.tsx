import * as React from "react";
import { Card } from "@schoolbox/Cards/Card/Card"

interface IDetail {
  exampleProp: string;
}

class Detail extends React.Component<IDetail> {
  public render() {
    return (
      <>
        <Card title="Example imported component" meta={`Example prop: ${this.props.exampleProp}`} />
      </>
    );
  }
}

export default Detail;
