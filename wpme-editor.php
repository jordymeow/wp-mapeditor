<?php

class Meow_Map_Editor_Admin extends Meow_Map_Editor {

	public function __construct() {
		parent::__construct();
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
	}

	function admin_menu() {
		add_submenu_page( 'edit.php?post_type=location', 'Map Editor', 'Map Editor', 'edit_maps', 'map_editor', array( $this, 'map_editor' ) );
	}

	function map_editor() {
		echo '<div class="wrap"><div id="icon-tools" class="icon32"></div>';
			echo '<h2>Map Editor</h2>';
		echo '</div>';
	}

}

?>