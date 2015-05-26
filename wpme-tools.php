<?php

class Meow_Map_Admin_Tools extends Meow_Map_Admin_Editor {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu_tools' ) );
	}

	function admin_menu_tools() {
		$submenu = add_submenu_page( 'edit.php?post_type=location', 'Tools', 'Tools', 'edit_maps', 'map_tools', array( $this, 'map_tools' ) );
	}

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

	function map_tools() {
		$action = isset ( $_POST[ 'submit' ] ) ? $_POST[ 'submit' ] : null;
		$term = isset ( $_POST[ 'term' ] ) ? $_POST[ 'term' ] : null;
		if ( $action == 'import' && !empty( $term ) ) {
			$str = file_get_contents( $_FILES['file']['tmp_name'] );
			$data = json_decode( $str, true );
			echo "Found " . count ($data['data'] ) . " locations.";
			if ( count ($data['data'] ) < 1 )
				return;

			// create taxonomy
			wp_insert_term( $term, 'map' );

			foreach ( $data['data'] as $location ) {
				$trans = "WME_IMPORT: " . $location['MapID'] . "-" . $location['ID'];
				if ( get_transient( $trans ) )
					continue;
				echo "Will add " . $location['Name'] . "<br />";
				
				$post_id = wp_insert_post( array(
					'post_title' => $location['Name'],
					'post_content' => $location['Name'],
					'post_status' => "publish",
					//'tax_input' => array( 'map' => 'Untitled' ),
					'post_type' => "location",
				), true );
				if ( is_wp_error( $post_id ) ) {
					echo "Error: " . $post_id->get_error_message() . "<br />";
				}
				else {
					wp_set_object_terms( $post_id, $term, 'map', true );
					$this->update_meta( $post_id, 'wme_coordinates', $location['GPS'] );
					if ( $location['Type'] == "WINTER" || $location['Type'] == "SUMMER" ||
						$location['Type'] == "SPRING" || $location['Type'] == "AUTUMN" ) {
						$this->update_meta( $post_id, 'wme_type', 'LANDSCAPE' );
						$this->update_meta( $post_id, 'wme_period', $location['Type'] );
					}
					else {
						$this->update_meta( $post_id, 'wme_type', !empty( $location['Type'] ) ? $location['Type'] : 'UNSPECIFIED' );
						$this->update_meta( $post_id, 'wme_period', "ANYTIME" );
					}
					$this->update_meta( $post_id, 'wme_status', !empty( $location['Status'] ) ? $location['Status'] : 'DRAFT' );
					$this->update_meta( $post_id, 'wme_rating', $location['Rating'] );
					$this->update_meta( $post_id, 'wme_difficulty', $location['Difficulty'] );
					echo "Added: " . $location['Name'] . "<br />";
					set_transient( $trans, 60 * 60 * 24 );
				}
				
			}

			echo "Finished!<br />";
		}
		?>

<div class='wrap'>
<div id="icon-upload" class="icon32"><br></div>

	<h2>Import</h2>
	<label for="filename">Import those locations in a new map.</label>
	<form method="post" enctype="multipart/form-data">
		<label>Map name</label>
		<input name="term" type="text" id="term"><br />
		<label>File</label>
		<input name="file" type="file" id="file"><br />
		<input type="submit" name="submit" id="submit" class="button button-primary" value="import">
		<input type="hidden" name="action" value="import">
	</form>
</div>

		<?php

	}

}