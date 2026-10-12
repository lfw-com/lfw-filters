<?php
/**
 * Active filters: one link per validated value that leads to the same page
 * without it, and Clear filters. Labels are stored term names only; a
 * keyword shows as a generic chip, never as its text.
 *
 * @package lfw-filters
 *
 * @var array $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$lfwf_pt    = isset( $attributes['postType'] ) ? (string) $attributes['postType'] : 'post';
$lfwf_state = LFW_Filters_Request::state( $lfwf_pt );
$lfwf_items = '';

foreach ( $lfwf_state['tax'] as $lfwf_tax => $lfwf_slugs ) {
	$lfwf_known = LFW_Filters_Request::terms( $lfwf_tax );
	foreach ( $lfwf_slugs as $lfwf_slug ) {
		$lfwf_items .= '<li><a class="lfwf-chip" href="' . esc_url( lfw_filters_link( $lfwf_state, $lfwf_tax, $lfwf_slug ) ) . '" data-lfwf-remove="' . esc_attr( $lfwf_tax . ':' . $lfwf_slug ) . '">'
			. lfw_filters_sr( esc_html__( 'Remove filter:', 'lfw-filters' ) . ' ' )
			. '<span>' . esc_html( $lfwf_known[ $lfwf_slug ]->name ) . '</span><span class="lfwf-x" aria-hidden="true"></span></a></li>';
	}
}
if ( '' !== $lfwf_state['q'] ) {
	// Removing the keyword keeps the taxonomy filters (links never carry the keyword).
	$lfwf_items .= '<li><a class="lfwf-chip" href="' . esc_url( lfw_filters_link( $lfwf_state ) ) . '" data-lfwf-remove="q">'
		. lfw_filters_sr( esc_html__( 'Remove filter:', 'lfw-filters' ) . ' ' )
		. '<span>' . esc_html__( 'Keyword search', 'lfw-filters' ) . '</span><span class="lfwf-x" aria-hidden="true"></span></a></li>';
}

$lfwf_inner = '';
if ( '' !== $lfwf_items ) {
	$lfwf_inner = '<ul class="lfwf-chips" role="list" aria-label="' . esc_attr( $attributes['label'] ) . '">' . $lfwf_items . '</ul>'
		. '<a class="lfwf-clear" href="' . esc_url( lfw_filters_base_url() ) . '" data-lfwf-clear>' . esc_html( $attributes['clearLabel'] ) . '</a>';
}

// The labels and the base link travel with the element, so a static copy can
// build the chips in the browser from the same validated vocabulary.
printf(
	'<div %1$s id="lfwf-active" data-lfwf-region data-lfwf-base="%4$s" data-lfwf-list-label="%5$s" data-lfwf-clear-label="%6$s" data-lfwf-remove-label="%7$s" data-lfwf-keyword-label="%8$s"%2$s>%3$s</div>',
	get_block_wrapper_attributes( array( 'class' => 'lfwf lfwf-active' ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	'' === $lfwf_inner ? ' hidden' : '',
	$lfwf_inner, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts.
	esc_url( lfw_filters_base_url() ),
	esc_attr( $attributes['label'] ),
	esc_attr( $attributes['clearLabel'] ),
	esc_attr__( 'Remove filter:', 'lfw-filters' ),
	esc_attr__( 'Keyword search', 'lfw-filters' )
);
