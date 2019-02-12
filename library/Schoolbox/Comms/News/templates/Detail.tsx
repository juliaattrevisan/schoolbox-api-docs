import * as React from "react";

interface IDetail {
  exampleProp: string;
}

class Detail extends React.Component<IDetail> {
  public render() {
    return (
      <>
        [React components to come]
        <div>Example prop: {this.props.exampleProp}</div>
      </>
    );
  }
}

export default Detail;
