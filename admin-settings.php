<?php

$wme_settings_api = "";

class Meow_MapEditor_Settings extends Meow_MapEditor_Server {

    public function __construct() {
        parent::__construct();
        add_action( 'admin_menu', array( $this, 'admin_menu_settings' ) );
        add_action( 'admin_init', array( $this, 'settings_init' ) );
        add_action( 'admin_notices', array( $this, 'admin_notice' ) );
    }

    function admin_notice() {
        // $gmaps_apikey = $this->get_option( 'gmaps_apikey', 'wme_basics', '' );
        // if ( empty( $gmaps_apikey ) ) {
        //     $class = "error";
        //     $message = "The API KEY for Google Maps is required by WP Map Editor.";
        //     echo"<div class=\"$class\"> <p>$message</p></div>";
        // }
    }

    function admin_menu_settings() {
        add_options_page( 'WP Map Editor', 'Map Editor', 'manage_options', 'wme_settings', array( $this, 'settings_page' ) );
    }

    function settings_init() {
        require( 'class.settings-api.php' );
        $sections = array(
            array(
                'id' => 'wme_basics',
                'title' => __( 'Basics', 'wp-mapeditor' )
            )
        );
        $fields = array(
            'wme_basics' => array(
                array(
                    'name' => 'gmaps_apikey',
                    'label' => __( 'Google Maps API key', 'wp-mapeditor' ),
                    'desc' => __( '<br />You can use Google Maps without an API KEY but it might stop working after a while. To make sure that it keeps working, <a href="https://developers.google.com/maps/signup" target="_blank">create an API key</a> (it is free). The referrer needs to be: <b>' . get_site_url() . '/wp-admin/admin.php?page=map_editor</b>', 'wp-mapeditor' ),
                    'type' => 'text',
                    'default' => ""
                ),
                array(
                    'name' => 'flickr_apikey',
                    'label' => __( 'Flickr API key', 'wp-mapeditor' ),
                    'desc' => __( '<br />If set, you will be able to see what photos have been taken on the map. You can get a key <a href="https://www.flickr.com/services/apps/create/">here</a>.', 'wp-mapeditor' ),
                    'type' => 'text',
                    'default' => ""
                ),
                array(
                    'name' => 'multimaps',
                    'disabled' => true,
                    'label' => __( 'Multi-maps', 'wp-mapeditor' ),
                    'desc' => __( 'Select many maps at the same time.', 'wp-mapeditor' ),
                    'type' => 'checkbox',
                    'default' => ""
                ),
                array(
                    'name' => 'allusers',
                    'disabled' => true,
                    'label' => __( 'For all users', 'wp-mapeditor' ),
                    'desc' => __( 'All users with the <b>Map Editor</b> role will be able to use the map editing functions.', 'wp-mapeditor' ),
                    'type' => 'checkbox',
                    'default' => ""
                ),
                array(
                    'name' => 'import',
                    'label' => __( 'Enable Import', 'wp-mapeditor' ),
                    'desc' => __( 'Enable the tools to import locations.', 'wp-mapeditor' ),
                    'type' => 'checkbox',
                    'default' => ""
                ),
                array(
                    'name' => 'export',
                    'label' => __( 'Enable Export', 'wp-mapeditor' ),
                    'desc' => __( 'Enable the tools to export locations.', 'wp-mapeditor' ),
                    'type' => 'checkbox',
                    'default' => ""
                ),
            )
        );
        global $wme_settings_api;
        $wme_settings_api = new WeDevs_Settings_API;
        $wme_settings_api->set_sections( $sections );
        $wme_settings_api->set_fields( $fields );
        $wme_settings_api->admin_init();
    }

    function settings_page() {
        global $wme_settings_api;
        echo '<div class="wrap">';
        echo "<div id='icon-options-general' class='icon32'><br></div><h2>WP Map Editor";
        echo "</h2>";
        $wme_settings_api->show_navigation();
        $wme_settings_api->show_forms();
        echo '</div>';
    }

}

?>
