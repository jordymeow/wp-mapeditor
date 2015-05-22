<?php

class Meow_Map_Admin_Editor extends Meow_Map_Editor {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'wp_ajax_add_location', array( $this, 'ajax_add_location' ) );
		add_action( 'wp_ajax_load_locations', array( $this, 'ajax_load_locations' ) );
		add_action( 'wp_ajax_load_maps', array( $this, 'ajax_load_maps' ) );

		add_filter( 'list_terms_exclusions', array( $this, 'list_terms_exclusions' ), 10, 2 );
	}

	function list_terms_exclusions( $exclusions, $args ) {
		//$exclusions .= " AND ( t.term_id NOT IN (2) )";


		return $exclusions;
	}

	function admin_menu() {
		$submenu = add_submenu_page( 'edit.php?post_type=location', 'Map Editor', 'Map Editor', 'edit_maps', 'map_editor', array( $this, 'map_editor' ) );
		add_action( 'admin_print_scripts-' . $submenu, array( $this, 'map_editor_js' ) );
		add_action( 'admin_print_styles-' . $submenu, array( $this, 'map_editor_css' ) );
	}

	function map_editor_js() {
		wp_enqueue_script( 'bootstrap', plugins_url( '/js/bootstrap.min.js', __FILE__ ), array(), "3.3.4", false );
		wp_enqueue_script( 'angular', plugins_url( '/js/angular.min.js', __FILE__ ), array(), "1.4.0-rc2", false );
		wp_enqueue_script( 'multi-select', plugins_url( '/js/isteven-multi-select.js', __FILE__ ), array( 'angular' ), "4.0.0", false );
		wp_enqueue_script( 'gmap', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAGBDCs9BWk0YVJR8G6B7UA-bd0pZ-9Ywc', array(), '', false );
		wp_enqueue_script( 'gmap-richmarker', plugins_url( '/js/richmarker.min.js', __FILE__ ), array( 'gmap' ), '', false );
		wp_enqueue_script( 'wpme-editor', plugins_url( '/js/wpme_editor.js', __FILE__ ), array( 'bootstrap', 'angular', 'gmap' ), "0.0.1", false );
	}

	function map_editor_css() {
		wp_register_style( 'bootstrap-css', plugins_url( '/css/bootstrap.min.css', __FILE__ ) );
		wp_enqueue_style( 'bootstrap-css' );
		wp_register_style( 'multi-select-css', plugins_url( '/css/isteven-multi-select.css', __FILE__ ) );
		wp_enqueue_style( 'multi-select-css' );
		wp_register_style( 'wpme-editor-css', plugins_url( '/css/wpme_editor.css', __FILE__ ) );
		wp_enqueue_style( 'wpme-editor-css' );
	}

	function ajax_add_location() {
		global $wpdb;
		echo "OH YEAH";
		wp_die();
	}

	function ajax_load_maps() {
		global $wpdb;
		$table = $this->get_db_role();
		$user_id = get_current_user_id();
		$lastticked = get_transient( "wme_lastticked_" . $user_id );
		$results = $wpdb->get_results( 
			"SELECT t.term_id id, t.name name, 0 ticked
			FROM $table r, $wpdb->terms t
			WHERE r.user_id = $user_id
			AND r.term_id = t.term_id
			GROUP BY t.term_id, t.name", OBJECT );
		if ( !empty( $lastticked ) )
			foreach ( $results as $result ) {
				$result->ticked = false;
				if ( $result->id == $lastticked )
					$result->ticked = true;
			}
		echo json_encode( $results );
		wp_die();
	}

	function ajax_load_locations() {
		$term_id = intval( $_POST['term_id'] );
		global $wpdb;
		$table = $this->get_db_role();
		$user_id = get_current_user_id();
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID id, p.post_title name, 
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_coordinates') coordinates,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_status') status,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_type') type,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_period') period,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_rating') rating,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_difficulty') difficulty
			FROM $table r, $wpdb->posts p, $wpdb->term_relationships s
			WHERE r.user_id = %d
			AND r.term_id = %d
			AND p.ID = s.object_id
			AND s.term_taxonomy_id = r.term_id", $user_id, $term_id ), OBJECT );
		echo json_encode( $results );
		set_transient( "wme_lastticked_" . $user_id, $term_id, 60 * 60 * 24 * 100 );
		wp_die();
	}

	function map_editor() {
		?>

<div ng-app="MapEditor">
<p style="position: absolute; margin: 20px; font-size: 14px; font-style: italic;">Loading Google Maps...</p>
<div ng-controller="EditorCtrl" id="wpme-mapeditor" class="ng-hide" ng-show="gmapLoaded">

	<nav id="wme-navbar-header" class="navbar navbar-inverse">
		<div class="container-fluid">
			<isteven-multi-select class="btn-sm navbar-btn"
				input-model="maps"
				output-model="selectedMaps"
				helper-elements=""
				button-label="icon name"
				item-label="icon name maker"
				disable-property="disabled"
				on-item-click="mapSelect(data)"
				tick-property="ticked">
			</isteven-multi-select>
			<div class="btn-group">
				<button type="button" class="btn btn-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
					<span ng-if="displayMode === 'status'">
						<span class="glyphicon glyphicon-flag"></span> Status <span class="caret"></span>
					</span>
					<span ng-if="displayMode === 'type'">
						<span class="glyphicon glyphicon-tree-conifer"></span> Type <span class="caret"></span>
					</span>
					<span ng-if="displayMode === 'period'">
						<span class="glyphicon glyphicon-tree-conifer"></span> Period <span class="caret"></span>
					</span>
				</button>
				<ul class="dropdown-menu" role="menu">
					<li><a href="#" ng-click="setDisplayMode('status')"><span class="glyphicon glyphicon-flag"></span> Status</a></li>
					<li><a href="#" ng-click="setDisplayMode('type')"><span class="glyphicon glyphicon-tree-conifer"></span> Type</a></li>
					<li><a href="#" ng-click="setDisplayMode('period')"><span class="glyphicon glyphicon-tree-conifer"></span> Period</a></li>
				</ul>
			</div>
			<button type="button" class="btn btn-success btn-sm navbar-btn" data-toggle="modal" data-target="#wpme-modal-add-location">
				<span class="glyphicon glyphicon-plus"></span> Location
			</button>
			<button type="button" class="btn btn-success btn-sm navbar-btn" data-toggle="modal" data-target="#wpme-modal-add-location">
				<span class="glyphicon glyphicon-asterisk"></span>
			</button>
		</div>

	</nav>
	<div id="wpme-info" class="ng-hide" ng-show="editor.selectedLocation">
		<div class="header">
			<span class="name">{{editor.selectedLocation.name}}</span><br />
			<span class="coordinates">{{editor.selectedLocation.coordinates}}</span>
		</div>
		<div class="info">
			Status: {{editor.selectedLocation.status}}<br />
			Type: {{editor.selectedLocation.type}}<br />
			Rating: {{editor.selectedLocation.rating}}<br />
			Difficulty: {{editor.selectedLocation.difficulty}}<br />
		</div>
		<button type="button" class="btn btn-primary btn-sm navbar-btn" data-toggle="modal" data-target="#wpme-modal-add-location">
			<span class="glyphicon glyphicon-pencil"></span>
		</button>
		<button type="button" class="btn btn-primary btn-sm navbar-btn" data-toggle="modal" data-target="#wpme-modal-add-location">
			<span class="glyphicon glyphicon glyphicon-move"></span>
		</button>
		<button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#wpme-modal-add-location">
			<span class="glyphicon glyphicon-asterisk"></span>
		</button>
		<button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#wpme-modal-add-location">
			<span class="glyphicon glyphicon-trash"></span>
		</button>
	</div>
	<div id="wpme-map"></div>
	<nav id="wme-navbar-footer">
		{{editor.hoveredLocation.name}}
		<span class="distance" ng-show="editor.distanceFromSelected">
			({{editor.distanceFromSelected}})
		</span>
	</nav>

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
</div>

		<?php
	}

}

?>