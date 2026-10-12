<?php
/**
 * Test fixture only (never shipped as part of a site): a "program" post type
 * with four taxonomies, used by the LFW Filters checks. Load it as a
 * must-use plugin in a local test stack.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'init',
	function () {
		register_post_type(
			'program',
			array(
				'label'        => 'Programs',
				'labels'       => array(
					'name'          => 'Programs',
					'singular_name' => 'Program',
				),
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt' ),
				'rewrite'      => array( 'slug' => 'program' ),
			)
		);
		$taxes = array(
			'area'       => array( 'Interest area', 'Interest areas' ),
			'credential' => array( 'Credential', 'Credentials' ),
			'format'     => array( 'When classes meet', 'When classes meet' ),
			'campus'     => array( 'Campus', 'Campuses' ),
		);
		foreach ( $taxes as $tax => $names ) {
			register_taxonomy(
				$tax,
				'program',
				array(
					'labels'            => array(
						'name'          => $names[1],
						'singular_name' => $names[0],
					),
					'public'            => true,
					'hierarchical'      => true,
					'show_in_rest'      => true,
					'show_admin_column' => true,
				)
			);
		}
		// A private taxonomy on the same post type: the filters must ignore it.
		register_taxonomy(
			'internal_flag',
			'program',
			array(
				'public'       => false,
				'show_ui'      => true,
				'show_in_rest' => false,
				'label'        => 'Internal flag',
			)
		);
	}
);
