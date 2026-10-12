<?php
/**
 * Test fixture: 30 fictional programs and two finder pages (band, panel).
 * Run with: wp eval-file wp-content/plugins/lfw-filters/tests/seed.php
 * Idempotent: deletes earlier fixture programs and pages first.
 *
 * @package lfw-filters
 */

defined( 'ABSPATH' ) || exit;

$terms = array(
	'area'       => array(
		'agriculture' => 'Agriculture and food',
		'business'    => 'Business',
		'education'   => 'Teaching and education',
		'engineering' => 'Engineering and computing',
		'health'      => 'Health and nursing',
		'arts'        => 'Arts and humanities',
		'science'     => 'Science and math',
		'public'      => 'Public service',
	),
	'credential' => array(
		'bachelors'  => "Bachelor's",
		'masters'    => "Master's",
		'credential' => 'Teaching credential',
		'minor'      => 'Minor',
	),
	'format'     => array(
		'inperson' => 'In person, daytime',
		'evening'  => 'Evening',
		'online'   => 'Online',
	),
	'campus'     => array(
		'alvara'   => 'Alvara',
		'riverton' => 'Riverton',
		'online'   => 'Online campus',
	),
);
foreach ( $terms as $tax => $list ) {
	foreach ( $list as $slug => $name ) {
		$found = term_exists( $slug, $tax );
		if ( $found ) {
			wp_update_term( (int) $found['term_id'], $tax, array( 'name' => $name ) );
		} else {
			wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
		}
	}
}
if ( ! term_exists( 'secret', 'internal_flag' ) ) {
	wp_insert_term( 'Secret', 'internal_flag', array( 'slug' => 'secret' ) );
}

foreach ( get_posts( array( 'post_type' => 'program', 'numberposts' => -1, 'post_status' => 'any', 'fields' => 'ids' ) ) as $id ) {
	wp_delete_post( $id, true );
}

// name, area, credentials, formats, campus.
$programs = array(
	array( 'Environmental Science', 'science', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Nursing', 'health', 'bachelors', 'inperson', 'alvara' ),
	array( 'Mechanical Engineering', 'engineering', 'bachelors', 'inperson', 'alvara' ),
	array( 'Studio Art', 'arts', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Business Administration', 'business', 'bachelors', 'online evening', 'online' ),
	array( 'Agricultural Science', 'agriculture', 'bachelors minor', 'inperson', 'riverton' ),
	array( 'Multiple Subject Teaching Credential', 'education', 'credential', 'evening', 'alvara' ),
	array( 'Computer Science', 'engineering', 'bachelors masters', 'inperson evening', 'alvara' ),
	array( 'Biology', 'science', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Master of Business Administration', 'business', 'masters', 'evening online', 'online' ),
	array( 'English', 'arts', 'bachelors masters minor', 'inperson', 'alvara' ),
	array( 'Social Work', 'public', 'masters', 'evening', 'riverton' ),
	array( 'Water Resources Management', 'agriculture', 'masters', 'online', 'online' ),
	array( 'Liberal Studies', 'education', 'bachelors', 'inperson', 'riverton' ),
	array( 'Public Administration', 'public', 'masters', 'evening online', 'online' ),
	array( 'Public Health', 'health', 'bachelors masters', 'inperson online', 'riverton' ),
	array( 'Kinesiology', 'health', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Mathematics', 'science', 'bachelors masters minor', 'inperson', 'alvara' ),
	array( 'Chemistry', 'science', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Accounting', 'business', 'bachelors', 'inperson evening', 'riverton' ),
	array( 'History', 'arts', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Music', 'arts', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Civil Engineering', 'engineering', 'bachelors masters', 'inperson', 'riverton' ),
	array( 'Data Science', 'engineering', 'masters', 'online', 'online' ),
	array( 'Criminal Justice', 'public', 'bachelors', 'inperson evening', 'riverton' ),
	array( 'Viticulture and Enology', 'agriculture', 'bachelors', 'inperson', 'riverton' ),
	array( 'Special Education Credential', 'education', 'credential masters', 'evening', 'alvara' ),
	array( 'Nutrition Science', 'health', 'bachelors', 'inperson', 'riverton' ),
	array( 'Economics', 'business', 'bachelors minor', 'inperson', 'alvara' ),
	array( 'Spanish', 'arts', 'bachelors minor', 'inperson online', 'alvara' ),
);
foreach ( $programs as $i => $p ) {
	$id = wp_insert_post(
		array(
			'post_type'    => 'program',
			'post_status'  => 'publish',
			'post_title'   => $p[0],
			'post_excerpt' => 'Fictional sample program for the filters test.',
			'post_content' => '<!-- wp:paragraph --><p>Fictional sample program.</p><!-- /wp:paragraph -->',
			'menu_order'   => $i,
		)
	);
	wp_set_object_terms( $id, array( $p[1] ), 'area' );
	wp_set_object_terms( $id, explode( ' ', $p[2] ), 'credential' );
	wp_set_object_terms( $id, explode( ' ', $p[3] ), 'format' );
	wp_set_object_terms( $id, array( $p[4] ), 'campus' );
	if ( 0 === $i % 5 ) {
		wp_set_object_terms( $id, array( 'secret' ), 'internal_flag' );
	}
}

$card = '<!-- wp:post-template {"className":"lfwf-test-cards","layout":{"type":"grid","columnCount":3}} --><!-- wp:post-title {"isLink":true,"level":3,"fontSize":"medium"} /--><!-- wp:post-excerpt /--><!-- /wp:post-template -->';

$band = ''
	. '<!-- wp:lfw-filters/filters {"postType":"program","layout":"band","align":"full","label":"Find a program","searchLabel":"Search programs","searchPlaceholder":"Search by name, like nursing or water","facets":[{"taxonomy":"area","control":"checkboxes","label":"Interest area"},{"taxonomy":"credential","control":"checkboxes","label":"Degree level"},{"taxonomy":"format","control":"checkboxes"},{"taxonomy":"campus","control":"select"}]} /-->'
	. '<!-- wp:group {"className":"lfwf-bar","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} --><div class="wp-block-group lfwf-bar"><!-- wp:lfw-filters/count {"postType":"program"} /--><!-- wp:lfw-filters/active {"postType":"program"} /--></div><!-- /wp:group -->'
	. '<!-- wp:lfw-filters/results -->'
	. '<!-- wp:query {"queryId":7,"query":{"perPage":12,"pages":0,"offset":0,"postType":"program","order":"asc","orderBy":"title","inherit":false,"lfwFilters":true}} --><div class="wp-block-query">' . $card
	. '<!-- wp:query-pagination --><!-- wp:query-pagination-previous /--><!-- wp:query-pagination-numbers /--><!-- wp:query-pagination-next /--><!-- /wp:query-pagination --></div><!-- /wp:query -->'
	. '<!-- /wp:lfw-filters/results -->';

// Panel: toggles, grouped by area, and a Query Loop WITHOUT the lfwFilters
// flag (the results block must add it on the server).
$panel = ''
	. '<!-- wp:lfw-filters/filters {"postType":"program","layout":"panel","label":"Find a program","searchLabel":"Search by name","searchPlaceholder":"Try welding or nursing","facets":[{"taxonomy":"area","control":"toggles","label":"Interest area"},{"taxonomy":"credential","control":"toggles"},{"taxonomy":"format","control":"toggles","label":"Schedule"},{"taxonomy":"campus","control":"toggles"}]} /-->'
	. '<!-- wp:group {"className":"lfwf-bar","layout":{"type":"flex","flexWrap":"wrap"}} --><div class="wp-block-group lfwf-bar"><!-- wp:lfw-filters/count {"postType":"program"} /--><!-- wp:lfw-filters/active {"postType":"program"} /--></div><!-- /wp:group -->'
	. '<!-- wp:lfw-filters/results {"groupBy":"area"} -->'
	. '<!-- wp:query {"queryId":8,"query":{"perPage":100,"pages":0,"offset":0,"postType":"program","order":"asc","orderBy":"title","inherit":true}} --><div class="wp-block-query"><!-- wp:post-template --><!-- wp:post-title {"isLink":true,"level":3,"fontSize":"medium"} /--><!-- wp:post-excerpt /--><!-- /wp:post-template --></div><!-- /wp:query -->'
	. '<!-- /wp:lfw-filters/results -->';

foreach ( array( 'programs-band' => $band, 'programs-panel' => $panel ) as $slug => $content ) {
	$old = get_page_by_path( $slug );
	if ( $old ) {
		wp_delete_post( $old->ID, true );
	}
	wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => 'band' === substr( $slug, -4 ) ? 'Programs (band)' : 'Programs (panel)',
			'post_content' => wp_slash( $content ),
		)
	);
}
echo 'Seeded ' . count( $programs ) . " programs and 2 pages.\n";
