<?php
/**
 *  Settings  class.
 *
 * @category   Class
 * @package    ElementorLgMapPlugin
 * @subpackage WordPress
 * @author     THS
 * @copyright  2022 THS
 * @license    https://opensource.org/licenses/GPL-3.0 GPL-3.0-only
 * @link       link(https://letztegeneration.de/vortraege/,
 *             Letzte Generation Vortraege)
 * @since      1.0.0
 * php version 7.3.9
 */
if ( ! defined( 'ABSPATH' ) ) {
    // Exit if accessed directly.
    exit;
}

/**
 * Main Elementor MaMeetupBackendApi
 *
 */
final class MeetupSettings {

    protected static $instance = null;

    private $original_meetups = null;
    private $meetup_data = null;

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        // Initialize the plugin.

        $this->addSettingsPage();
        add_action( 'admin_init', array($this, 'registerSettings') );
    }


    function addSettingsPage() {
        add_options_page( 'Letzte Generation Custom Karten', 'LG Custom Karten Einstellungen', 'manage_options', 'elementor-lg-map-plugin_settings_page', array ($this, 'renderPluginSettings') );
    }

    function renderPluginSettings(){
        // check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // add error/update messages

        // check if the user have submitted the settings
        // WordPress will add the "settings-updated" $_GET parameter to the url
        if ( isset( $_GET['settings-updated'] ) ) {
            // add settings saved message with the class of "updated"
            add_settings_error( 'elementor-lg-map-plugin', 'elementor-lg-map-plugin_message', __( 'Settings Saved', 'elementor-lg-map-plugin' ), 'updated' );
        }

        // show error/update messages
        settings_errors( 'elementor-lg-map-plugin_messages' );
         ?>
        <h2>Letzte Generation Plugin Konfiguration</h2>
        <form action="options.php" method="post">
            <?php 
                settings_fields( 'elementor-lg-map-plugin_settings' );
                do_settings_sections( 'elementor-lg-map-plugin' ); 
                // output save settings button
                submit_button( 'Save Settings' );
            ?>

        </form>
        <?php
    }

    function registerSettings() {
        register_setting( 'elementor-lg-map-plugin_settings', 'elementor-lg-map-plugin_settings');
        add_settings_section( 'lg_meetup_settings', 'Konfiguration', array($this, 'configTextRender'), 'elementor-lg-map-plugin' );

        add_settings_field( 'elementor-lg-map-plugin_api_key', 'Google API Key', array($this, 'apiKeyRender'), 'elementor-lg-map-plugin', 'lg_meetup_settings' );
        add_settings_field( 'elementor-lg-map-plugin_meetups_url', 'Vortraege URL', array($this, 'meetupsUrlRender'), 'elementor-lg-map-plugin', 'lg_meetup_settings' );
        add_settings_field( 'elementor-lg-map-plugin_blockades_url', 'Blockaden URL', array($this, 'blockadesUrlRender'), 'elementor-lg-map-plugin', 'lg_meetup_settings' );
    }

    function configTextRender(){
        echo '<p>Die Konfiguration für das Letzte Generation Kartenanzeige</p>';
    }

    function apiKeyRender(){
        $options = get_option( 'elementor-lg-map-plugin_settings' );
        echo "<input id='elementor-lg-map-plugin_settings_api_key' name='elementor-lg-map-plugin_settings[api_key]' type='text' value='" . esc_attr( $options['api_key'] ) . "' />";
    }
    
    function meetupsUrlRender(){
        $options = get_option( 'elementor-lg-map-plugin_settings' );
        echo "<input id='elementor-lg-map-plugin_settings_meetups_url' name='elementor-lg-map-plugin_settings[meetups_url]' type='text' value='" . esc_attr( $options['meetups_url'] ) . "' />";
    }

    function blockadesUrlRender(){
        $options = get_option( 'elementor-lg-map-plugin_settings' );
        echo "<input id='elementor-lg-map-plugin_settings_blockades_url' name='elementor-lg-map-plugin_settings[blockades_url]' type='text' value='" . esc_attr( $options['blockades_url'] ) . "' />";
    }

    public static function get_instance() {
        if ( ! isset( static::$instance ) ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

   
  
}

add_action( 'admin_menu', 'my_settings_init' );
function my_settings_init() {
    MeetupSettings::get_instance();
}




