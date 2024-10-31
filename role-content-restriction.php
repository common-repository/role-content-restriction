<?php
/*
Plugin Name: Role Content Restriction
Plugin URI: http://imprevo.net/
Description: Restricts access to selected post types based on user roles.
Version: 1.0.0
Author: Imprevo
Author URI: http://imprevo.net/
License: GPLv2 or later
Text Domain: role-content-restriction
*/

define( 'IMCR_VERSION', '1.0.0' );


//internationalization
function role_content_restriction_load_textdomain() {
    load_plugin_textdomain( 'role-content-restriction', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'role_content_restriction_load_textdomain' );

//All the plugin functionalities are contained in this class
require_once('class-role-content-restriction.php');
$role_content_restriction = new Role_Content_Restriction();

//The rest of this file applies the necessary hooks and filters to add this plugin functionalites to WordPress
function role_content_restriction_add_meta_box() {

    global $role_content_restriction;

    $screens = $role_content_restriction->get_post_types();
	
    //add meta_box to all post types
    foreach ($screens as $screen) {
        add_meta_box(
		  'role-content-restriction',
		  //title of the metabox
		  __( 'Role Content Restriction'),
		  //content of the metabox
		  array($role_content_restriction,'output_user_interface'),
		  //apply to each standard (post, page) or custom post types
		  $screen,
		  $context = 'side'
        );    
    }	
}

add_action( 'add_meta_boxes', 'role_content_restriction_add_meta_box' );
add_action( 'save_post', array($role_content_restriction,'save_restriction'), 10, 2 );

//filter the content according to access restrictions
add_filter( 'the_content', array( $role_content_restriction,  'content_restriction'));