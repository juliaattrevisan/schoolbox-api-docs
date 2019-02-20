import * as React from "react";

interface IImage {
  src: string;
  alt: string;
}

/**
 * Just a basic image element. Requires alt text for accessibility.
 */
const Image: React.FunctionComponent<IImage> = props => {
  const { src, alt } = props;
  return <img src={src} alt={alt} />;
};

/**
 * Empty container element for creating a skeleton loader of meta text.
 */
const LoadingImage: React.FunctionComponent = props => {
  return <div className="img" />;
};

export { LoadingImage, IImage, Image };
