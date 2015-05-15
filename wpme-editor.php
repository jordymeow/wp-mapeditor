<?php

class Meow_Map_Editor_Admin extends Meow_Map_Editor {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_menu() {
		$submenu = add_submenu_page( 'edit.php?post_type=location', 'Map Editor', 'Map Editor', 'edit_maps', 'map_editor', array( $this, 'map_editor' ) );
		add_action( 'admin_print_scripts-' . $submenu, array( $this, 'map_editor_js' ) );
		add_action( 'admin_print_styles-' . $submenu, array( $this, 'map_editor_css' ) );
	}

	function map_editor_js() {
		wp_enqueue_script( 'bootstrap', plugins_url( '/js/bootstrap.min.js', __FILE__ ), array(), "3.3.4", false );
		wp_enqueue_script( 'angular', plugins_url( '/js/angular.min.js', __FILE__ ), array(), "1.4.0-rc2", false );
		wp_enqueue_script( 'gmap', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAGBDCs9BWk0YVJR8G6B7UA-bd0pZ-9Ywc', array(), '', false );
		wp_enqueue_script( 'wpme-editor', plugins_url( '/js/wpme_editor.js', __FILE__ ), array( 'bootstrap', 'angular', 'gmap' ), "0.0.1", false );
	}

	function map_editor_css() {
		wp_register_style( 'bootstrap-css', plugins_url( '/css/bootstrap.min.css', __FILE__ ) );
		wp_enqueue_style( 'bootstrap-css' );
		wp_register_style( 'wpme-editor-css', plugins_url( '/css/wpme_editor.css', __FILE__ ) );
		wp_enqueue_style( 'wpme-editor-css' );
	}

	function map_editor() {
		?>

<div id="wpme-mapeditor" class="nowrap">

	<nav class="navbar navbar-inverse">
		<div class="container-fluid">
			<div class="navbar-header">
				<a class="navbar-brand" href="#">Map Editor</a>
			</div>
			<button type="button" class="btn btn-primary btn-sm navbar-btn"><span class="glyphicon glyphicon-plus"></span> Map</button>
			<button type="button" class="btn btn-primary btn-sm navbar-btn" data-toggle="modal" data-target="#wpme-modal-add-location"><span class="glyphicon glyphicon-plus"></span> Location</button>
		</div>
	</nav>
	<div id="wpme-map">
	</div>

</div>

<div class="modal fade" id="wpme-modal-add-location" >
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 class="modal-title">Add Location</h4>
			</div>
			<div class="modal-body">
				<p>One fine body&hellip;</p>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-primary">Add</button>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->

		<?php
	}

}

?>