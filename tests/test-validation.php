<?php
/**
 * PHP checks for LFW Filters: validation, caps, allowlist, query arguments,
 * counts, and markup that never reflects input. Needs the fixture
 * (sample-programs.php as a must-use plugin, then seed.php).
 *
 * Run: wp eval-file wp-content/plugins/lfw-filters/tests/test-validation.php
 * Exits non-zero on any failure.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

$lfwf_t = array(
	'pass' => 0,
	'fail' => 0,
);

$check = function ( $name, $ok, $detail = '' ) use ( &$lfwf_t ) {
	if ( $ok ) {
		++$lfwf_t['pass'];
		echo "ok   {$name}\n";
	} else {
		++$lfwf_t['fail'];
		echo "FAIL {$name}" . ( '' !== $detail ? " :: {$detail}" : '' ) . "\n";
	}
};

$req = function ( $qs ) {
	$_SERVER['QUERY_STRING'] = $qs;
	$_SERVER['REQUEST_URI']  = '/programs-band/' . ( '' !== $qs ? '?' . $qs : '' );
	LFW_Filters_Request::reset();
	return LFW_Filters_Request::state( 'program' );
};

// ---- Allowlist ----
$taxes = LFW_Filters_Request::taxonomies( 'program' );
sort( $taxes );
$check( 'allowlist is the public taxonomies of the post type', array( 'area', 'campus', 'credential', 'format' ) === $taxes, implode( ',', $taxes ) );
$check( 'unknown post type has no taxonomies', array() === LFW_Filters_Request::taxonomies( 'nope' ) );
$check( 'non-viewable post type has no taxonomies', array() === LFW_Filters_Request::taxonomies( 'wp_block' ) );

// ---- Parsing and validation ----
$s = $req( 'area=health&area=business' );
$check( 'repeated keys are all read', array( 'health', 'business' ) === $s['tax']['area'] );
$s = $req( 'area=health,business' );
$check( 'comma list is read', array( 'health', 'business' ) === $s['tax']['area'] );
$s = $req( 'area=health&area=nope&area=HEALTH&area=' );
$check( 'unknown and duplicate values dropped', array( 'health' ) === $s['tax']['area'], wp_json_encode( $s['tax'] ) );
$s = $req( 'internal_flag=secret&category=uncategorized&zz=1' );
$check( 'private taxonomy and taxonomies of other types ignored', array() === $s['tax'] && ! $s['active'], wp_json_encode( $s ) );
$s = $req( 'area=' . str_repeat( 'a', 201 ) );
$check( 'over-long slug dropped', array() === $s['tax'] );
$s = $req( 'area[]=health' );
$check( 'array-style keys ignored', array() === $s['tax'] );
$s = $req( 'area=health&' . str_repeat( 'x', 4100 ) );
$check( 'query string over 4096 bytes ignored entirely', array() === $s['tax'] );

// Caps: 12 values per taxonomy are considered, 24 in all.
$many = array();
for ( $i = 0; $i < 12; $i++ ) {
	$many[] = 'area=nope' . $i;
}
$s = $req( implode( '&', $many ) . '&area=health' );
$check( 'only the first 12 values per taxonomy are considered', ! isset( $s['tax']['area'] ) );
$check( 'cap constants', 6 === LFW_Filters_Request::MAX_TAXONOMIES && 24 === LFW_Filters_Request::MAX_VALUES && 80 === LFW_Filters_Request::MAX_KEYWORD );

// ---- Keyword ----
$s = $req( 'q=' . rawurlencode( '<script>alert(1)</script>nurs' ) );
$check( 'keyword strips tags', 'nurs' === $s['q'], $s['q'] );
$s = $req( 'q=' . rawurlencode( str_repeat( 'abcdefghij', 20 ) ) );
$check( 'keyword capped at 80 characters', 80 === strlen( $s['q'] ) );
$s = $req( 'q=one+two+three+four+five+six+seven+eight+nine+ten' );
$check( 'keyword capped at 8 words', 'one two three four five six seven eight' === $s['q'], $s['q'] );
$s = $req( 'q[]=x' );
$check( 'array keyword ignored', '' === $s['q'] );
$s = $req( 'q=' . rawurlencode( "  a\x00\x07b  " ) );
$check( 'control characters removed', 'a b' === $s['q'], wp_json_encode( $s['q'] ) );

// ---- Query arguments ----
$req( 'area=health&credential=masters&q=pub' );
$args = LFW_Filters_Query::args( 'program' );
$check( 'args: post type, publish, no password', 'program' === $args['post_type'] && 'publish' === $args['post_status'] && false === $args['has_password'] );
$check( 'args: AND across taxonomies', 'AND' === $args['tax_query']['relation'] && 2 === count( $args['tax_query'] ) - 1 );
$check( 'args: IN within a taxonomy, by slug', 'IN' === $args['tax_query'][0]['operator'] && 'slug' === $args['tax_query'][0]['field'] && array( 'health' ) === $args['tax_query'][0]['terms'] );
$check( 'args: keyword becomes s', 'pub' === $args['s'] );
$ex = LFW_Filters_Query::args( 'program', true, 'area' );
$check( 'args: except leaves a taxonomy out', 1 === count( $ex['tax_query'] ) && 'credential' === $ex['tax_query'][0]['taxonomy'] );

// ---- Counts against the 30-program fixture ----
$req( '' );
$check( 'total unfiltered is 30', 30 === LFW_Filters_Query::total( 'program', false ) );
$req( 'area=health' );
$check( 'area=health gives 4', 4 === LFW_Filters_Query::total( 'program' ) );
$req( 'area=health&area=business' );
$check( 'area=health OR business gives 8', 8 === LFW_Filters_Query::total( 'program' ) );
$req( 'area=health&credential=masters' );
$check( 'health AND masters gives 1', 1 === LFW_Filters_Query::total( 'program' ) );
$oc = LFW_Filters_Query::option_counts( 'program', 'area' );
$check( 'option counts ignore their own taxonomy', isset( $oc['health'], $oc['business'] ) && 1 === $oc['health'] && 1 === $oc['business'], wp_json_encode( $oc ) );
$req( 'area=nope&format=evening' );
$check( 'invalid value dropped, valid one kept (evening = 9)', 9 === LFW_Filters_Query::total( 'program' ) );
$req( 'q=nurs' );
$check( 'keyword nurs gives 1', 1 === LFW_Filters_Query::total( 'program' ) );

// ---- Count text ----
$req( '' );
$check( 'count text unfiltered', '30 programs' === lfw_filters_count_text( 'program' ), lfw_filters_count_text( 'program' ) );
$req( 'area=health' );
$check( 'count text filtered', 'Showing 4 of 30 programs' === lfw_filters_count_text( 'program' ) );
$req( 'q=zzzzzz' );
$check( 'count text zero', 'No programs match' === lfw_filters_count_text( 'program' ) );

// ---- Markup never reflects input ----
$hostile = 'q=' . rawurlencode( '"><script>zzhostile1</script>' ) . '&area=zzhostile2&area=health&credential=' . rawurlencode( '"><img src=x onerror=zzhostile3>' ) . '&zzhostile4=1';
$req( $hostile );
$page = get_page_by_path( 'programs-band' );
$html = do_blocks( $page->post_content );
$check( 'band page renders', false !== strpos( $html, 'data-lfwf-form' ) && false !== strpos( $html, 'id="lfwf-results"' ) );
$check( 'no hostile text in markup', ! preg_match( '/zzhostile/', $html ), 'found' );
$check( 'keyword shows as a generic chip', false !== strpos( $html, 'Keyword search' ) );
$check( 'search field is not prefilled', ! preg_match( '/<input[^>]*name="q"[^>]*value=/', $html ) );
$check( 'valid value is checked', (bool) preg_match( '/name="area" value="health" checked/', $html ) );
$check( 'live count present', false !== strpos( $html, 'aria-live="polite"' ) );

// ---- Results block filters the Query Loop ----
$req( 'area=health' );
$html = do_blocks( $page->post_content );
preg_match_all( '/<h3[^>]*><a[^>]*>([^<]+)</', $html, $m );
$check( 'results list follows filters', array( 'Kinesiology', 'Nursing', 'Nutrition Science', 'Public Health' ) === $m[1], implode( '|', $m[1] ) );
$check( 'chip links never carry the keyword', ! preg_match( '/href="[^"]*[?&]q=/', $html ) );

$panel = get_page_by_path( 'programs-panel' );
$req( 'format=evening' );
$html = do_blocks( $panel->post_content );
preg_match_all( '/class="lfwf-group-heading"[^>]*>([^<]+)</', $html, $g );
$check( 'grouped results: only groups with matches', array( 'Business ', 'Engineering and computing ', 'Public service ', 'Teaching and education ' ) === $g[1], implode( '|', $g[1] ) );
$check( 'unflagged inherit Query Loop is flagged on the server', 9 === preg_match_all( '/<h3[^>]*><a/', $html ) );

$req( 'area=agriculture&credential=credential' );
$html = do_blocks( $page->post_content );
$check( 'empty state when nothing matches', false !== strpos( $html, 'No results match all of those filters' ) );

// ---- noindex ----
$req( 'area=health' );
$check( 'filtered view looks filtered (noindex)', LFW_Filters_Request::looks_filtered() );
$req( 'utm_source=x' );
$check( 'unrelated params do not noindex', ! LFW_Filters_Request::looks_filtered() );

// ---- Unflagged Query Loops elsewhere are untouched ----
$req( 'area=health' );
$plain = do_blocks( '<!-- wp:query {"queryId":9,"query":{"perPage":50,"postType":"program","inherit":false}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template --></div><!-- /wp:query -->' );
$check( 'a Query Loop outside the results block ignores filters', 30 === preg_match_all( '/wp-block-post-title/', $plain ) );

echo "\n{$lfwf_t['pass']} passed, {$lfwf_t['fail']} failed\n";
if ( $lfwf_t['fail'] ) {
	exit( 1 );
}
