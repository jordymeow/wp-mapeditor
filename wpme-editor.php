<?php

class Meow_Map_Admin_Editor extends Meow_Map_Editor {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'delete_post', array( $this, 'delete_post' ) );
		add_action( 'wp_ajax_edit_location', array( $this, 'ajax_edit_location' ) );
		add_action( 'wp_ajax_add_location', array( $this, 'ajax_add_location' ) );
		add_action( 'wp_ajax_delete_location', array( $this, 'ajax_delete_location' ) );
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
		
		// Google Maps
		wp_enqueue_script( 'gmap', 'https://maps.googleapis.com/maps/api/js?key=AIzaSyAGBDCs9BWk0YVJR8G6B7UA-bd0pZ-9Ywc', array(), '', false );
		wp_enqueue_script( 'gmap-richmarker', plugins_url( '/js/richmarker.min.js', __FILE__ ), array( 'gmap' ), '', false );

		// Ladda
		wp_enqueue_script( 'spin-js', plugins_url( '/js/spin.min.js', __FILE__ ), array(), "0.0.1", false );
		wp_enqueue_script( 'ladda-js', plugins_url( '/js/ladda.min.js', __FILE__ ), array( 'spin-js' ), "0.0.1", false );
		wp_enqueue_script( 'angular-ladda', plugins_url( '/js/angular-ladda.min.js', __FILE__ ), array( 'angular', 'ladda-js' ), "0.0.1", false );

		// Editor
		wp_enqueue_script( 'wpme-editor', plugins_url( '/js/wpme_editor.js', __FILE__ ), array( 'bootstrap', 'angular', 'ladda-js', 'gmap' ), "0.0.1", false );
	}

	function map_editor_css() {
		wp_register_style( 'bootstrap-css', plugins_url( '/css/bootstrap.min.css', __FILE__ ) );
		wp_enqueue_style( 'bootstrap-css' );
		wp_register_style( 'ladda-themeless', plugins_url( '/css/ladda-themeless.min.css', __FILE__ ) );
		wp_enqueue_style( 'ladda-themeless' );
		wp_register_style( 'multi-select-css', plugins_url( '/css/isteven-multi-select.css', __FILE__ ) );
		wp_enqueue_style( 'multi-select-css' );
		wp_register_style( 'wpme-editor-css', plugins_url( '/css/wpme_editor.css', __FILE__ ) );
		wp_enqueue_style( 'wpme-editor-css' );
	}

	function user_has_role_for_map( $map_id ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = $this->get_db_role();
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table r WHERE r.user_id = %d AND r.term_id = %d", $user_id, $map_id ) );
		return $count > 0;
	}

	function user_has_role_for_location( $id ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = $this->get_db_role();
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table r, $wpdb->term_relationships rp WHERE r.user_id = %d AND rp.term_taxonomy_id = r.term_id AND rp.object_id = %d", $user_id, $id ) );
		
		return $count > 0;
	}

	function ajax_add_location() {
		$location = json_decode( stripslashes( $_POST['location'] ) );
		if ( empty( $location ) ) {
			echo json_encode( array( 'success' => false, 'message' => "No location information." ) );
			wp_die();
		}
		global $wpdb;
		if ( $this->user_has_role_for_map( $location->mapId ) ) {
			$location->id = wp_insert_post( array(
				'post_title' => $location->name,
				'post_content' => $location->description,
				'post_status' => "draft",
				'post_type' => "location",
			), true );
			if ( is_wp_error( $location->id ) ) {
				echo json_encode( array( 'success' => false, 'data' => $post_id->get_error_message() ) );
				die;
			}
			wp_set_object_terms( $location->id, (int)$location->mapId, 'map' );
			$this->update_meta( $location->id, 'wme_type', $location->type );
			$this->update_meta( $location->id, 'wme_period', $location->period );
			$this->update_meta( $location->id, 'wme_status', $location->status );
			$this->update_meta( $location->id, 'wme_rating', $location->rating );
			$this->update_meta( $location->id, 'wme_coordinates', $location->coordinates );
			$this->update_meta( $location->id, 'wme_difficulty', $location->difficulty );
			echo json_encode( array( 'success' => true, 'data' => $location ) );
			wp_die();
		}
		else {
			echo json_encode( array( 'success' => false, 'message' => "You have no access to this map." ) );
			wp_die();
		}
	}

	function ajax_delete_location() {
		$id = intval( stripslashes( $_POST['id'] ) );
		if ( empty( $id ) ) {
			echo json_encode( array( 'success' => false, 'message' => "No location information." ) );
			wp_die();
		}
		if ( !$this->user_has_role_for_location( $id ) ) {
			echo json_encode( array( 'success' => false, 'message' => "You have no right to delete this location." ) );
			wp_die();
		}
		wp_trash_post( $id );
		echo json_encode( array( 'success' => true ) );
		wp_die();
	}

	function delete_post( $id ) {
		delete_post_meta( $id, 'wme_type' );
		delete_post_meta( $id, 'wme_period' );
		delete_post_meta( $id, 'wme_status' );
		delete_post_meta( $id, 'wme_rating' );
		delete_post_meta( $id, 'wme_coordinates' );
		delete_post_meta( $id, 'wme_difficulty' );
	}

	function ajax_edit_location() {
		$location = json_decode( stripslashes( $_POST['location'] ) );
		if ( empty( $location ) ) {
			echo json_encode( array( 'success' => false, 'message' => "No location information." ) );
			wp_die();
		}
		global $wpdb;
		if ( $this->user_has_role_for_location( $location->id ) ) {
			$result = $wpdb->update( $wpdb->posts, array(
				'post_title' => $location->name,
				'post_content' => $location->description
			),
			array( 'ID' => $location->id ), 
			array( '%s', '%s' ), array( '%d' ) );
			$this->update_meta( $location->id, 'wme_type', $location->type );
			$this->update_meta( $location->id, 'wme_period', $location->period );
			$this->update_meta( $location->id, 'wme_status', $location->status );
			$this->update_meta( $location->id, 'wme_rating', $location->rating );
			$this->update_meta( $location->id, 'wme_coordinates', $location->coordinates );
			$this->update_meta( $location->id, 'wme_difficulty', $location->difficulty );
			echo json_encode( array( 'success' => true, 'data' => $location ) );
			wp_die();
		}
		else {
			echo json_encode( array( 'success' => false, 'message' => "You have no right to modify this location." ) );
			wp_die();
		}
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
		echo json_encode( array( 'success' => true, 'data' => $results ) );
		wp_die();
	}

	function ajax_load_locations() {
		$term_id = intval( $_POST['term_id'] );
		global $wpdb;
		$table = $this->get_db_role();
		$user_id = get_current_user_id();
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.ID id, p.post_title name, p.post_content description,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_coordinates') coordinates,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_status') status,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_type') type,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_period') period,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_rating') rating,
			(SELECT meta_value FROM $wpdb->postmeta m WHERE m.post_id = p.ID AND m.meta_key = 'wme_difficulty') difficulty
			FROM $table r, $wpdb->posts p, $wpdb->term_relationships s
			WHERE r.user_id = %d
			AND p.post_status <> 'trash'
			AND r.term_id = %d
			AND p.ID = s.object_id
			AND s.term_taxonomy_id = r.term_id", $user_id, $term_id ), OBJECT );
		set_transient( "wme_lastticked_" . $user_id, $term_id, 60 * 60 * 24 * 100 );
		echo json_encode( array( 'success' => true, 'data' => $results ) );
		wp_die();
	}

	function map_editor() {
		?>

<div ng-app="MapEditor" ng-controller="EditorCtrl">
<p style="position: absolute; margin: 20px; font-size: 14px; font-style: italic;">Loading Google Maps...</p>
<div id="wpme-mapeditor" class="ng-hide" ng-show="gmapLoaded">

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
			<button type="button" class="btn btn-success btn-sm navbar-btn" ng-click="onAddLocationClick()">
				<span class="glyphicon glyphicon-plus"></span> Location
			</button>
<!-- 			<button type="button" class="btn btn-success btn-sm navbar-btn">
				<span class="glyphicon glyphicon-asterisk"></span>
			</button> -->
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
		<button ladda="isSavingLocation" ng-disabled="isDragging" type="button" class="btn btn-primary btn-sm navbar-btn" ng-click="onEditLocationClick()">
			<span class="glyphicon glyphicon-pencil"></span>
		</button>
		<button ladda="isSavingLocation" ng-hide="isDragging" type="button" class="btn btn-primary btn-sm navbar-btn" ng-click="startDraggable()">
			<span class="glyphicon glyphicon glyphicon-move"></span>
		</button>
		<button ladda="isSavingLocation" ng-show="isDragging" type="button" class="btn btn-primary btn-sm navbar-btn" ng-click="saveDraggable()">
			<span class="glyphicon glyphicon glyphicon-ok"></span>
		</button>
		<button ladda="isSavingLocation" ng-disabled="isDragging" type="button" class="btn btn-success btn-sm">
			<span class="glyphicon glyphicon-asterisk"></span>
		</button>
		<button ladda="isSavingLocation" ng-disabled="isDragging" type="button" class="btn btn-danger btn-sm" ng-click="deleteLocation()">
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

<div class="modal fade" id="wpme-modal-location">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-body">
				<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
				<h4 style="margin-top: 0px;">Location</h4>
				<form>
					<div class="form-group">
						<input type="text" class="form-control" id="name" placeholder="Name" ng-model="editor.editLocation.name">
					</div>
					<div class="form-group">
						<input type="text" class="form-control" id="coordinates" placeholder="GPS Coordinates" ng-model="editor.editLocation.coordinates">
					</div>
					<div class="form-group">
						<div class="row">
							<div class="col-md-6">
								<select id="status" class="form-control" 
									ng-options="s as s for s in constants.statuses" ng-model="editor.editLocation.status">
								</select>
							</div>
							<div class="col-md-6">
								<select id="type" class="form-control" 
									ng-options="t as t for t in constants.types" ng-model="editor.editLocation.type">
								</select>
							</div>
						</div>
					</div>
					<div class="form-group">
						<textarea class="form-control" id="description" rows="3" placeholder="Description" ng-model="editor.editLocation.description"></textarea>
					</div>
					<div class="form-group">
						<div class="row">
							<div class="col-md-4">
								<select id="period" class="form-control" 
									ng-options="p as p for p in constants.periods" ng-model="editor.editLocation.period">
								</select>
							</div>
							<div class="col-md-4">
								<select id="difficulty" class="form-control" 
									ng-options="d as d for d in constants.difficulties" ng-model="editor.editLocation.difficulty">
								</select>
							</div>
							<div class="col-md-4">
								<select id="rating" class="form-control" 
									ng-options="r as r for r in constants.ratings" ng-model="editor.editLocation.rating">
								</select>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div class="modal-footer">
				<button ng-show="isEditingLocation" type="button" ladda="isSavingLocation" ng-click="editLocation()" class="btn btn-primary pull-right"><span class="glyphicon glyphicon-pen"></span> Modify</button>
				<button ng-show="isAddingLocation" type="button" ladda="isSavingLocation" ng-click="addLocation()" class="btn btn-primary pull-right"><span class="glyphicon glyphicon-plus"></span> Add</button>
				<div ng-show="isAddingLocation" class="form-group pull-right">
					<select id="map" class="form-control" 
						ng-options="r.id as r.name for r in maps" ng-model="editor.editLocation.mapId"
						style="margin: 3px 13px 3px 0px; width: 200px;">
					</select>
				</div>
			</div>
		</div><!-- /.modal-content -->
	</div><!-- /.modal-dialog -->
</div><!-- /.modal -->

</div>

		<?php
	}

}

?>