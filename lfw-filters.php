<?php
/**
 * Plugin Name:       LFW Filters
 * Description:       Accessible filters for any post type, built from its public taxonomies: a filter band or panel, removable chips, a live result count and a Query Loop that follows the filters. Every filtered view is a plain link that works without JavaScript; with JavaScript the results update in place.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            LFW.com LLC
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lfw-filters
 *
 * Everything runs on the site itself. Nothing here calls an outside service,
 * and no LFW account is needed.
 *
 * Abuse case: a stranger can only choose among existing public terms and a
 * capped keyword that is never printed, so filter links cannot put their words
 * on the site, and every response is a cacheable read-only page. Details in
 * includes/request.php and readme.txt.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

define( 'LFW_FILTERS_VERSION', '0.1.0' );
define( 'LFW_FILTERS_DIR', __DIR__ );
define( 'LFW_FILTERS_URL', plugins_url( '', __FILE__ ) );

require_once __DIR__ . '/includes/request.php';
require_once __DIR__ . '/includes/query.php';
require_once __DIR__ . '/includes/markup.php';

add_action(
	'init',
	function () {
		$ver = function ( $file ) {
			return LFW_FILTERS_VERSION . '-' . filemtime( __DIR__ . '/assets/' . $file );
		};
		wp_register_style( 'lfw-filters', LFW_FILTERS_URL . '/assets/filters.css', array(), $ver( 'filters.css' ) );
		wp_register_script(
			'lfw-filters-view',
			LFW_FILTERS_URL . '/assets/filters.js',
			array(),
			$ver( 'filters.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_register_script(
			'lfw-filters-editor',
			LFW_FILTERS_URL . '/assets/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-data', 'wp-core-data' ),
			$ver( 'editor.js' ),
			true
		);
		foreach ( array( 'filters', 'results', 'active', 'count' ) as $name ) {
			register_block_type( __DIR__ . '/blocks/' . $name );
		}
	}
);
