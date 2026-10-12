<?php
/**
 * Reading the request: the only place visitor input enters the plugin.
 *
 * Abuse controls (see readme.txt):
 * - Only public taxonomies registered for the post type are honored.
 * - Every value must be the slug of an existing term; anything else is
 *   dropped and never echoed.
 * - Caps: 6 taxonomies, 12 values per taxonomy, 24 values in all, slugs up to
 *   200 characters, keyword up to 80 characters and 8 words, and a query
 *   string over 4,096 bytes is ignored entirely.
 * - Nothing is stored. The keyword never reaches markup.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

/**
 * Request state, cached per request. Tests call reset() after changing
 * $_SERVER['QUERY_STRING'].
 */
final class LFW_Filters_Request {

	const MAX_QUERY_BYTES  = 4096;
	const MAX_TAXONOMIES   = 6;
	const MAX_PER_TAXONOMY = 12;
	const MAX_VALUES       = 24;
	const MAX_SLUG         = 200;
	const MAX_KEYWORD      = 80;
	const MAX_WORDS        = 8;
	const KEYWORD_PARAM    = 'q';

	/** @var array|null Raw parameters: name => list of strings. */
	private static $raw = null;

	/** @var array Validated state per post type. */
	private static $states = array();

	/** @var array Term maps per taxonomy: slug => WP_Term. */
	private static $terms = array();

	/** Forget everything cached (tests, or after switching blogs). */
	public static function reset() {
		self::$raw    = null;
		self::$states = array();
		self::$terms  = array();
		LFW_Filters_Query::reset();
	}

	/**
	 * Raw query string parameters. Read from QUERY_STRING because PHP keeps
	 * only the last of repeated keys (area=a&area=b), which is what a plain
	 * form with checkboxes submits. Comma lists are split too.
	 *
	 * @return array<string, string[]>
	 */
	public static function raw() {
		if ( null !== self::$raw ) {
			return self::$raw;
		}
		self::$raw = array();
		$qs        = '';
		if ( isset( $_SERVER['QUERY_STRING'] ) ) {
			$qs = (string) wp_unslash( $_SERVER['QUERY_STRING'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below, never output.
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$qs = (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		}
		if ( '' === $qs || strlen( $qs ) > self::MAX_QUERY_BYTES ) {
			return self::$raw;
		}
		foreach ( explode( '&', $qs ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$parts = explode( '=', $pair, 2 );
			$key   = urldecode( $parts[0] );
			$val   = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';
			if ( ! preg_match( '/^[a-z0-9_-]{1,32}$/', $key ) ) {
				continue;
			}
			if ( self::KEYWORD_PARAM === $key ) {
				if ( ! isset( self::$raw[ $key ] ) ) {
					self::$raw[ $key ] = array( $val );
				}
				continue;
			}
			foreach ( explode( ',', $val ) as $v ) {
				$v = trim( $v );
				if ( '' !== $v ) {
					self::$raw[ $key ][] = $v;
				}
			}
		}
		return self::$raw;
	}

	/**
	 * Taxonomies a visitor may filter by for this post type: registered for
	 * it, public and publicly queryable. Themes can narrow the list with the
	 * lfw_filters_taxonomies filter (never widen it past public ones).
	 *
	 * @param string $post_type Post type.
	 * @return string[] Taxonomy names.
	 */
	public static function taxonomies( $post_type ) {
		$out = array();
		if ( ! is_string( $post_type ) || ! post_type_exists( $post_type ) ) {
			return $out;
		}
		$pto = get_post_type_object( $post_type );
		if ( ! $pto || ! is_post_type_viewable( $pto ) ) {
			return $out;
		}
		foreach ( get_object_taxonomies( $post_type, 'objects' ) as $tax ) {
			if ( $tax->public && $tax->publicly_queryable ) {
				$out[] = $tax->name;
			}
		}
		$filtered = apply_filters( 'lfw_filters_taxonomies', $out, $post_type );
		return array_values( array_intersect( $out, (array) $filtered ) );
	}

	/**
	 * Existing terms of a taxonomy, keyed by slug.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return array<string, WP_Term>
	 */
	public static function terms( $taxonomy ) {
		if ( isset( self::$terms[ $taxonomy ] ) ) {
			return self::$terms[ $taxonomy ];
		}
		$map   = array();
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 2000,
				'orderby'    => 'name',
			)
		);
		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $t ) {
				$map[ $t->slug ] = $t;
			}
		}
		self::$terms[ $taxonomy ] = $map;
		return $map;
	}

	/**
	 * The keyword, cleaned and capped. Used only as a WP_Query search
	 * argument; never printed.
	 *
	 * @return string
	 */
	public static function keyword() {
		$raw = self::raw();
		if ( empty( $raw[ self::KEYWORD_PARAM ][0] ) ) {
			return '';
		}
		$k = wp_strip_all_tags( $raw[ self::KEYWORD_PARAM ][0] );
		$k = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $k );
		$k = trim( (string) preg_replace( '/\s+/u', ' ', (string) $k ) );
		if ( '' === $k ) {
			return '';
		}
		$k     = function_exists( 'mb_substr' ) ? mb_substr( $k, 0, self::MAX_KEYWORD ) : substr( $k, 0, self::MAX_KEYWORD );
		$words = array_slice( explode( ' ', $k ), 0, self::MAX_WORDS );
		return trim( implode( ' ', $words ) );
	}

	/**
	 * Validated filter state for a post type.
	 *
	 * @param string $post_type Post type.
	 * @return array{tax: array<string, string[]>, q: string, active: bool}
	 */
	public static function state( $post_type ) {
		if ( isset( self::$states[ $post_type ] ) ) {
			return self::$states[ $post_type ];
		}
		$raw   = self::raw();
		$tax   = array();
		$total = 0;
		foreach ( self::taxonomies( $post_type ) as $name ) {
			if ( empty( $raw[ $name ] ) ) {
				continue;
			}
			if ( count( $tax ) >= self::MAX_TAXONOMIES || $total >= self::MAX_VALUES ) {
				break;
			}
			$known = self::terms( $name );
			$keep  = array();
			foreach ( array_slice( $raw[ $name ], 0, self::MAX_PER_TAXONOMY ) as $v ) {
				if ( strlen( $v ) > self::MAX_SLUG ) {
					continue;
				}
				$slug = sanitize_title( $v );
				if ( '' === $slug || ! isset( $known[ $slug ] ) || in_array( $slug, $keep, true ) ) {
					continue;
				}
				$keep[] = $slug;
				++$total;
				if ( $total >= self::MAX_VALUES ) {
					break;
				}
			}
			if ( $keep ) {
				$tax[ $name ] = $keep;
			}
		}
		$q = self::keyword();
		self::$states[ $post_type ] = array(
			'tax'    => $tax,
			'q'      => $q,
			'active' => ( $tax || '' !== $q ),
		);
		return self::$states[ $post_type ];
	}

	/**
	 * True when the request carries any filter for any public taxonomy, or a
	 * keyword. Used for noindex before the post type is known.
	 *
	 * @return bool
	 */
	public static function looks_filtered() {
		$raw = self::raw();
		if ( ! $raw ) {
			return false;
		}
		if ( '' !== self::keyword() ) {
			return true;
		}
		$public = get_taxonomies(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			)
		);
		foreach ( array_keys( $raw ) as $key ) {
			if ( isset( $public[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}
}

/*
 * Filtered views are noindex, follow: the page's canonical link stays the
 * unfiltered page, and crawlers do not walk every combination.
 */
add_filter(
	'wp_robots',
	function ( $robots ) {
		if ( ! is_admin() && LFW_Filters_Request::looks_filtered() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);
