import * as React from "react";

interface IMeta {
  children: string;
}

/**
 * Text that describes a particular item
 */
const Meta: React.SFC<IMeta> = props => {
  const { children } = props;
  return <p className="meta">{children}</p>;
};

export { IMeta, Meta };
