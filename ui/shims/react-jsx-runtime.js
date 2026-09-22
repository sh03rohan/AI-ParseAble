/* eslint-disable -- vendored, minified React code. */
/**
 * react-jsx-runtime for WordPress 6.4 and 6.5.
 *
 * Bundles built with @wordpress/scripts use React's automatic JSX runtime and depend on the
 * "react-jsx-runtime" script handle, which core only registers from 6.6. This entry exposes the
 * same production build core ships (react/cjs/react-jsx-runtime.production.min.js, React 18.3.1,
 * MIT licensed, Copyright (c) Meta Platforms, Inc. and affiliates) as window.ReactJSXRuntime, and
 * the plugin registers it only when core has not.
 */
import React from 'react';

const runtime = {};
( function ( exports, require ) {
	'use strict';var f=require("react"),k=Symbol.for("react.element"),l=Symbol.for("react.fragment"),m=Object.prototype.hasOwnProperty,n=f.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,p={key:!0,ref:!0,__self:!0,__source:!0};
function q(c,a,g){var b,d={},e=null,h=null;void 0!==g&&(e=""+g);void 0!==a.key&&(e=""+a.key);void 0!==a.ref&&(h=a.ref);for(b in a)m.call(a,b)&&!p.hasOwnProperty(b)&&(d[b]=a[b]);if(c&&c.defaultProps)for(b in a=c.defaultProps,a)void 0===d[b]&&(d[b]=a[b]);return{$$typeof:k,type:c,key:e,ref:h,props:d,_owner:n.current}}exports.Fragment=l;exports.jsx=q;exports.jsxs=q;
} )( runtime, () => React );

window.ReactJSXRuntime = runtime;
