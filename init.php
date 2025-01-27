<?php
/**
 * Title: Elements Initializer
 *
 * Description: Initializes the elements. Adds all required files.
 *
 * Please do not edit this file. This file is part of the Cyber Chimps Framework and all modifications
 * should be made in a child theme.
 *
 * @category Cyber Chimps Framework
 * @package  Framework
 * @since    1.0
 * @author   CyberChimps
 * @license  http://www.opensource.org/licenses/gpl-license.php GPL v3.0 (or later)
 * @link     http://www.cyberchimps.com/
 */

// Load style for elements
function cyberchimps_add_elements_style() {

	// Set directory uri
	$directory_uri = get_template_directory_uri();

	wp_enqueue_style( 'elements_style', $directory_uri . '/elements/lib/css/elements.css' );

	wp_enqueue_script( 'elements_js', $directory_uri . '/elements/lib/js/elements.min.js' );
}

add_action( 'wp_enqueue_scripts', 'cyberchimps_add_elements_style', 30 );

// Load elements
// Set directory path
$directory_path = get_template_directory();

require_once get_parent_theme_file_path('/elements/parallax.php' );
require_once get_parent_theme_file_path('/elements/portfolio-lite.php' );
require_once get_parent_theme_file_path('/elements/slider-lite.php' );
require_once get_parent_theme_file_path('/elements/boxes.php' );
require_once get_parent_theme_file_path('/elements/testimonial.php' );
require_once get_parent_theme_file_path('/elements/contact-us.php' );

// main blog drag and drop options
function cyberchimps_selected_elements() {
	$options = array(
		'boxes_lite'     => __( 'Boxes Lite', 'cyberchimps_core' ),
		"portfolio_lite" => __( 'Portfolio Lite', 'cyberchimps_core' ),
		"blog_post_page" => __( 'Post Page', 'cyberchimps_core' ),
		"slider_lite"    => __( 'Slider Lite', 'cyberchimps_core' ),
                "testimonial"	     => __( 'Testimonial', 'cyberchimps_core'),
                "map_contact"	     => __( 'Contact Us', 'cyberchimps_core')
	);

	return $options;
}

add_filter( 'cyberchimps_elements_draganddrop_options', 'cyberchimps_selected_elements' );

function cyberchimps_selected_page_elements() {
	$options = array(
		'boxes_lite'     => __( 'Boxes Lite', 'cyberchimps_core' ),
		"portfolio_lite" => __( 'Portfolio Lite', 'cyberchimps_core' ),
		"page_section"   => __( 'Page', 'cyberchimps_core' ),
		"slider_lite"    => __( 'Slider Lite', 'cyberchimps_core' ),
                "testimonial"	     => __( 'Testimonial', 'cyberchimps_core'),
                "map_contact"	     => __( 'Contact Us', 'cyberchimps_core')
	);

	return $options;
}

add_filter( 'cyberchimps_elements_draganddrop_page_options', 'cyberchimps_selected_page_elements' );

// drop breadcrumb fields
function cyberchimps_element_drop_fields( $fields ) {
// drop unwanted fields
	foreach( $fields as $key => $value ) {
		if( $value['id'] == 'single_post_breadcrumbs' || $value['id'] == 'archive_breadcrumbs' ) {
			unset( $fields[$key] );
		}
	}

	return $fields;
}

add_filter( 'cyberchimps_field_filter', 'cyberchimps_element_drop_fields', 2 );
