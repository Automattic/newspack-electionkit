/**
 **** WARNING: No ES6 modules here. Not transpiled! ****
 */
/* eslint-disable import/no-nodejs-modules */

/**
 * External dependencies
 */
const getBaseWebpackConfig = require( '@automattic/calypso-build/webpack.config.js' );
const path = require( 'path' );

/**
 * Internal variables
 */
const electionkit = path.join( __dirname, 'src', 'electionkit' );

const webpackConfig = getBaseWebpackConfig(
	{ WP: true },
	{
		entry: { electionkit },
		'output-path': path.join( __dirname, 'dist' ),
	}
);

module.exports = webpackConfig;