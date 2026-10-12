<?php
/**
 * Result count in a polite live region. Numbers and post type nouns only.
 *
 * @package lfw-filters
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$lfwf_pt = isset( $attributes['postType'] ) ? (string) $attributes['postType'] : 'post';
list( $lfwf_s, $lfwf_p ) = lfw_filters_nouns( $lfwf_pt, $attributes['singular'], $attributes['plural'] );

// The nouns, the unfiltered total and the three sentence templates travel with
// the element, so a static copy can rewrite the count without a server.
printf(
	'<p %1$s id="lfwf-count" aria-live="polite" aria-atomic="true" tabindex="-1" data-lfwf-live data-lfwf-total="%3$d" data-lfwf-singular="%4$s" data-lfwf-plural="%5$s" data-lfwf-text-all="%6$s" data-lfwf-text-some="%7$s" data-lfwf-text-none="%8$s">%2$s</p>',
	get_block_wrapper_attributes( array( 'class' => 'lfwf lfwf-count' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	esc_html( lfw_filters_count_text( $lfwf_pt, $attributes['singular'], $attributes['plural'] ) ),
	LFW_Filters_Query::total( $lfwf_pt, false ),
	esc_attr( $lfwf_s ),
	esc_attr( $lfwf_p ),
	/* translators: 1: number, 2: noun */
	esc_attr__( '%1$s %2$s', 'lfw-filters' ),
	/* translators: 1: shown, 2: total, 3: noun */
	esc_attr__( 'Showing %1$s of %2$s %3$s', 'lfw-filters' ),
	/* translators: %s: plural noun */
	esc_attr__( 'No %s match', 'lfw-filters' )
);
