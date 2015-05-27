<?php
/*
Plugin Name: WP Map Editor
Plugin URI: http://apps.meow.fr
Description: Create and browse your maps using your WordPress installation.
Version: 0.0.1
Author: Jordy Meow
Author URI: http://apps.meow.fr
*/

class Meow_MapEditor {

	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		// Metabox (add 'Mistake' in Location + save)
		add_action( 'add_meta_boxes', array( $this, 'add_location_metaboxes' ) );
		add_action( 'save_post', array( $this, 'save_location_metaboxes' ), 1, 2 );
	}

	function init() {
		$this->create_db();
		$this->create_infrastructure();
		$this->create_roles();

		if ( is_admin() ) {
			// Friendly display of the Locations
			add_filter( 'manage_edit-map_columns', array( $this, 'manage_map_columns' ), 10, 2 );
			add_action( 'manage_map_posts_custom_column', array( $this, 'manage_map_columns_content' ), 10, 2 );
			add_action( 'created_map', array( $this, 'created_map' ), 10, 2 );
			add_action( 'delete_map', array( $this, 'delete_map' ), 10, 2 );
		}
	}

	/******************************
		FUNCTIONS
	******************************/

	function update_meta( $post_id, $meta_key, $new_value ) {
		$new_value = trim( $new_value );
		$old_value = get_post_meta( $post_id, $meta_key, true );
		if ( $new_value == '' )
			return;
		else if ( $old_value == '' && $new_value )
			add_post_meta( $post_id, $meta_key, $new_value, true );
		else if ( $old_value != $new_value )
			update_post_meta( $post_id, $meta_key, $new_value );
		/*
		else if ( $new_value == '' && $old_value )
		  delete_post_meta( $post_id, $meta_key, $old_value );
		*/
	}

	/******************************
		MAINTAIN AUTHOR FOR MAPS
	******************************/

	function created_map( $term_id, $tt_id ) {
		global $wpdb;
		$table = $this->get_db_role();
		$this->delete_map( $term_id, $tt_id );
		$wpdb->insert(
			$table,
			array(
				'term_id' => $term_id,
				'user_id' => get_current_user_id()
			),
			array( '%d', '%d', '%s' )
		);
	}

	function delete_map( $term_id, $tt_id ) {
		global $wpdb;
		$table = $this->get_db_role();
		$wpdb->delete( $table, array( 'term_id' => $term_id ) );
	}

	/******************************
		FRIENDLY DISPLAY FOR ADMIN
	******************************/

	function manage_map_columns( $columns ) {
		$new_columns = array();
		$new_columns['map'] = __( 'Location', 'wpme' );;
		unset( $columns['date'] );
		return array_merge( $columns, $new_columns );
	}

	function manage_map_columns_content( $column_name, $post_id ) {
		if ( 'map' == $column_name ) {
			$mistake = get_post_meta( $post_id, '_mistake', true );
			$map = get_post_meta( $post_id, '_map', true );
			$correction = get_post_meta( $post_id, '_correction', true );
			if ( empty( $correction ) && empty( $mistake ) ) {
				echo '<span style="color: #0185B5;">' . $map . '</span>';
				return;
			}
			else if ( empty( $mistake ) ) {
				echo '<span style="color: red; font-weight: bold;">There is a correction but no mistake.</span>';
				return;
			}
			else if ( empty( $correction ) ) {
				echo '<span style="color: red; font-weight: bold;">There is a mistake but no correction.</span>';
				return;
			}
			$mistakes = explode( ',', $mistake );
			$words = explode( ' ', $map );
			foreach ( $mistakes as $m ) {
				if ( $m - 1 > count( $words ) ) {
					echo '<span style="color: red; font-weight: bold;">Mistake is out of the map.</span>';
					return;
				}
				$words[$m - 1] = '<span style="color: #F91818;">' . $words[$m - 1] . "</span>";
			}
			$html = implode( ' ', $words );
			$html .= '<br />&#8594; ' . $correction;
			echo $html;
		}
	}

	/**************************************
		METABOXES AND METADATA FOR SENTENCE
	**************************************/

	// function default_title( $post_title, $post ) {
	// 	if ( $post->post_type !== 'map' )
	// 		return $post_title;
	// 	return "N/A";
	// }

	// Add the metaboxes for Location
	function add_location_metaboxes() {
	// 	add_meta_box( 'wpme_maps_map', 'Location', array( $this, 'display_map_metabox' ), 'map', 'normal', 'high' );
	// 	add_meta_box( 'wpme_maps_mistake', 'Mistake(s)', array( $this, 'display_mistake_metabox' ), 'map', 'normal', 'high' );
	// 	add_meta_box( 'wpme_maps_correction', 'Correction', array( $this, 'display_correction_metabox' ), 'map', 'normal', 'high' );
	}

	// function display_map_metabox() {
	// 	global $post;
	// 	echo '<input type="hidden" name="map_meta_noncename" id="map_meta_noncename" value="' . 
	// 	wp_create_nonce( plugin_basename(__FILE__) ) . '" />';
	// 	$mistake = get_post_meta($post->ID, '_map', true);
	// 	echo '<input type="text" name="_map" value="' . $mistake  . '" class="widefat" />';
	// 	echo '<p class="description">Words must be separated by a space.</p>';
	// }

	// // Display the metaboxes for Location
	// function display_correction_metabox() {
	// 	global $post;
	// 	$mistake = get_post_meta( $post->ID, '_correction', true );
	// 	echo '<input type="text" name="_correction" value="' . $mistake  . '" class="widefat" />';
	// 	echo '<p class="description">Simply the correction of the map.>';
	// }

	// // Display the metaboxes for Location
	// function display_mistake_metabox() {
	// 	global $post;
	// 	$mistake = get_post_meta( $post->ID, '_mistake', true );
	// 	echo '<input type="text" name="_mistake" value="' . $mistake  . '" class="widefat" />';
	// 	echo '<p class="description">No mistakes? Keep empty. Mistake on the first word? Use 1. Mistakes on 3rd and 4th words? Use 3,4.</p>';
	// }

	// // Save the metaboxes for Location
	function save_location_metaboxes( $post_id, $post ) {
	// 	if ( $post->post_type == 'revision' )
	// 			return;
	// 	if ( !isset( $_POST[ 'map_meta_noncename'] ) || !wp_verify_nonce( $_POST[ 'map_meta_noncename'],  plugin_basename( __FILE__ ) ) )
	// 		return $post->ID;
	// 	if ( !current_user_can( 'edit_post', $post->ID ))
	// 		return $post->ID;

	// 	// The meta for Location
	// 	$events_meta['_map'] = sanitize_text_field( $_POST['_map'] );
	// 	$events_meta['_correction'] = sanitize_text_field( $_POST['_correction'] );
	// 	$events_meta['_mistake'] = sanitize_text_field( $_POST['_mistake'] );

	// 	// Create, update or delete
	// 	foreach ( $events_meta as $key => $value ) {
	// 		$value = implode(',', (array)$value); // If $value is an array, make it a CSV (unlikely)
	// 		if ( get_post_meta( $post->ID, $key, FALSE ) )
	// 			update_post_meta($post->ID, $key, $value);
	// 		else
	// 			add_post_meta($post->ID, $key, $value);
	// 		if ( !$value ) 
	// 			delete_post_meta( $post->ID, $key );
	// 	}
	}

	/*************************
		OVERRIDES FOR DISPLAY
	*************************/

	// Override the text for the title of Locations
	function gettext( $input ) {
		global $post_type;
		// if ( is_admin() && 'Enter title here' == $input && 'map' == $post_type )
		// 	return 'Enter map here (words separated with spaces)';
    return $input;
	}

	/***********************
		ROLES AND CAPABILITY
	************************/

	function create_roles() {
		$capabilities = array( 'publish','delete','delete_private','delete_published','edit','edit_private','edit_published','read_private' );
		
		// For Map Editor
		remove_role( "map_editor" );
		$maprole = add_role( "map_editor" , "Map Editor" );
		$maprole->add_cap( "read" );
		$maprole->add_cap( "manage_categories" );
		foreach ( $capabilities as $cap ) {
			$maprole->add_cap( "{$cap}_maps" );
		}

		// For Admin
		$adminrole = get_role( 'administrator' );
		$capabilities_admin = array_merge( array( 'edit_others', 'delete_others' ), $capabilities );
		foreach ( $capabilities_admin as $cap ) {
			$adminrole->add_cap( "{$cap}_maps" );
		}
	}

	function get_db_role() {
		global $wpdb;
		$table_name = $wpdb->prefix . "wme_role"; 
		return $table_name;
	}

	/****************************
		DATABASE
	****************************/

	function create_db() {
		$table_name = $this->get_db_role();
		$sql = "CREATE TABLE $table_name (
			id BIGINT(20) NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) NULL,
			term_id BIGINT(20) NULL,
			role TINYINT DEFAULT '6',
			UNIQUE KEY id (id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	}

	/****************************
		CREATES SENTENCE AND RULE
	****************************/

	function create_infrastructure() {
		// Post type: Locations
		$labels = array(
			'name'               => _x( 'Locations', 'post type general name', 'wpme' ),
			'singular_name'      => _x( 'Location', 'post type singular name', 'wpme' ),
			'menu_name'          => _x( 'Locations', 'admin menu', 'wpme' ),
			'name_admin_bar'     => _x( 'Location', 'add new on admin bar', 'wpme' ),
			'add_new'            => _x( 'Add New', 'location', 'wpme' ),
			'add_new_item'       => __( 'Add New Location', 'wpme' ),
			'new_item'           => __( 'New Location', 'wpme' ),
			'edit_item'          => __( 'Edit Location', 'wpme' ),
			'view_item'          => __( 'View Location', 'wpme' ),
			'all_items'          => __( 'All Locations', 'wpme' ),
			'search_items'       => __( 'Search Locations', 'wpme' ),
			'parent_item_colon'  => __( 'Parent Locations:', 'wpme' ),
			'not_found'          => __( 'No locations found.', 'wpme' ),
			'not_found_in_trash' => __( 'No locations found in Trash.', 'wpme' )
		);
		$args = array(
			'labels'             		=> $labels,
			'public'             		=> true,
			'publicly_queryable' 		=> true,
			'show_ui'            		=> true,
			'show_in_menu'       		=> true,
			'query_var'          		=> true,
			'rewrite'            		=> array( 'slug' => 'location' ),
			'has_archive'        		=> false,
			'hierarchical'       		=> false,
			'capability_type'		 		=> 'map',
			'map_meta_cap'			 		=> true,
			'menu_position'      		=> null,
			'supports'							=> array( 'title', 'thumbnail', 'editor', 'author' ),
			'register_meta_box_cb'	=> array( $this, 'add_location_metaboxes' )
		);
		register_post_type( 'location', $args );

		// Taxonomy: Maps
		$labels = array(
			'name'              => _x( 'Maps', 'taxonomy general name' ),
			'singular_name'     => _x( 'Map', 'taxonomy singular name' ),
			'search_items'      => __( 'Search Maps' ),
			'all_items'         => __( 'All Maps' ),
			'edit_item'         => __( 'Edit Map' ),
			'update_item'       => __( 'Update Map' ),
			'add_new_item'      => __( 'Add New Map' ),
			'new_item_name'     => __( 'New Map Name' ),
			'menu_name'         => __( 'Maps' ),
		);

		$args = array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'capabilities' => array(
				'manage_terms'=> 'manage_categories',
				'edit_terms'=> 'manage_categories',
				'delete_terms'=> 'manage_categories',
				'assign_terms' => 'read'
			),
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'map' ),
		);

		register_taxonomy( 'map', array( 'location' ), $args );
	}
		
}

add_action( 'plugins_loaded', 'meow_map_editor_init' );

function meow_map_editor_init() {
	if ( class_exists( 'Meow_MapEditor' ) ) {
		if ( is_admin() ) {
			include "editor-server.php";
			if ( is_super_admin() ) {
				include "admin-tools.php";
				new Meow_MapEditor_Tools;
			}
			else {
				new Meow_MapEditor_Server;
			}
		}
		else {
			new Meow_MapEditor;
		}
	}
}

?>
