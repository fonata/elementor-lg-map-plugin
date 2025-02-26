<?php
/**
 * Meetup Backend API  class.
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
 * Main Elementor MeetupBackendApi
 *
 */
final class MeetupBackendApi {

    protected static $instance = null;

    private $original_meetups = null;
    private $meetup_data = null;
    private $geocode_addresses = array();

    /**
     * Constructor
     *
     * @since 1.0.0
     * @access public
     */
    public function __construct() {
        $this->original_meetups = get_transient("elementor-lg-map-plugin_meetups_csv");
        $this->meetup_data = get_transient("elementor-lg-map-plugin_meetups_api");
        // Initialize the plugin.
        $this->meetupRoutes();
    }

    // API Routes
    function meetupRoutes() {
      register_rest_route( 'meetup/v1', '/all', array(
        'methods' => 'GET',
        'callback' => array ($this, 'getAllMeetups')
      ) );

     register_rest_route( 'meetup/v1', '/original', array(
        'methods' => 'GET',
        'callback' => array ($this, 'getOriginalData')
      ) );

    register_rest_route( 'meetup/v1', '/refresh', array(
        'methods' => 'GET',
        'callback' => array ($this, 'refreshCache'),
        'permission_callback' => function () {
            return current_user_can( 'manage_options' );
        }
      ) );
    }

    function refreshCache(){
        $this->refresh();

        return new WP_REST_Response("Cache refresh", 200);
    }


    function loadCSV($csvUrl){
        $etag = get_transient("elementor-lg-map-plugin_meetups_csv_etag");

        $data = $this->restRequestCSV($csvUrl, $etag);

        if($data && array_key_exists('csv', $data)) {
            if($data['csv']){
                $this->original_meetups = array();
                $rows = explode("\n",$data['csv']);

                foreach($rows as $row) {
                    //skip empty lines
                    $trimmedRow = trim($row);

                    if(str_starts_with($trimmedRow, "DATUM,UHRZEIT,")){
                        continue;
                    }

                    if (strlen(ltrim($trimmedRow, ',')) == 0) {
                        # empty entry
                        continue;
                    }

                    if(strlen($trimmedRow) > 0){
                        $this->original_meetups[] = str_getcsv($trimmedRow);
                    }
                }

                set_transient("elementor-lg-map-plugin_meetups_csv", $this->original_meetups, $this->getBackendCacheDuration());
                delete_transient("elementor-lg-map-plugin_meetups_api");
            }
        } else if($data && array_key_exists('cache', $data)){
            $this->increaseMetrics('cache_hits');
            $this->original_meetups = get_transient("elementor-lg-map-plugin_meetups_csv");
        }
    }


    function restRequestCSV($csvUrl, $etag){
        $data = file_get_contents($csvUrl);
        $curl = curl_init();
        $headers = [];

        curl_setopt($curl, CURLOPT_URL, $csvUrl);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADERFUNCTION,
            function ($curl, $header) use (&$headers) {
                $len = strlen($header);
                $header = explode(':', $header, 2);
                if (count($header) < 2) // ignore invalid headers
                    return $len;

                $headers[strtolower(trim($header[0]))][] = trim($header[1]);

                return $len;
            }
        );
        if($etag){
            curl_setopt($curl, CURLOPT_HTTPHEADER, array('If-None-Match:'.$etag));
        }

        $curl_response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($curl_response === false) {
            $info = curl_getinfo($curl);
            error_log('Could not request CSV Data ' . curl_error($curl));
            curl_close($curl);
            return false;
        }

        $etagResponse = $this->getEtag($headers);

        curl_close($curl);

        if($httpcode == 304 && get_transient("elementor-lg-map-plugin_meetups_csv")){
            $this->increaseMetrics('etag_hits');
            return array('cache' => true);
        }

        $this->increaseMetrics('csv_loads');

        if($httpcode != 200){
            error_log('Could not retrieve data '. $httpcode);
            return false;
        }

        set_transient("elementor-lg-map-plugin_meetups_csv_etag", $etagResponse, $this->getBackendCacheDuration());
        $this->updateLoadTimer();

        return array('csv' => $curl_response);
    }

    function getEtag($headers) {
        $etagOriginal = $headers['etag'][0];

        return str_replace("W/", "", $etagOriginal);
    }

    function prepareData($apikey){
        if(!get_transient("elementor-lg-map-plugin_meetups_api")) {
            $this->meetup_data = array();
            foreach($this->original_meetups as $row){
                $address = $this->extractAddress($row);
                if(strlen($address) > 0){
                    $geocodeData = $this->geocodeCacheWrapper($apikey, $address);

                    if($geocodeData){
                        $this->meetup_data[] = $this->buildApiData($row, $address, $geocodeData);
                    } else {
                        // retry with only city
                        $geocodeData = $this->geocodeCacheWrapper($apikey, $row[2]);

                        if($geocodeData){
                            $this->meetup_data[] = $this->buildApiData($row, $address, $geocodeData);
                        } else {
                            //write to error log
                            error_log('Failed while geodecoding following entry, skipping it: ' . print_r($row, true));
                        }
                    }
                }
            }


            set_transient("elementor-lg-map-plugin_meetups_api", $this->meetup_data, $this->getBackendCacheDuration());
        } else {
            $this->increaseMetrics('cache_hits');
            $this->meetup_data = get_transient("elementor-lg-map-plugin_meetups_api");
        }
    }

    function extractAddress($entry){
        $address = "";
        if(isset($entry[3]) && strlen($entry[3])
            && !str_contains($entry[3],"<a")){
            $address = $entry[3];
        }

        if(isset($entry[2]) && strlen($entry[2])){
            $address .= " " . $entry[2];
        }

        return trim($address);
    }

    function buildApiData($entry, $usedAddress, $geocodeData){
        return array(
                 'lecturer' => trim($entry[4]),
                 'location' => trim($entry[3]),
                 'city' => trim($entry[2]),
                 'date' => trim($entry[1]),
                 'time' => trim($entry[0]),
                 'usedAddress' => $usedAddress,
                 'formatted_address' => $geocodeData[2],
                 'geodata' => array(
                     'lat' => $geocodeData[0],
                     'lng' => $geocodeData[1]
                 )
             );
    }

    function geocodeCacheWrapper($apikey, $address){
        if(in_array($address, $this->geocode_addresses)){
            return $this->geocode_addresses[$address];
        }

        $response = $this->geocode($apikey, $address);

        if($response){
            $this->geocode_addresses[$address] = $response;
        }

        return $response;
    }

    function geocode($apikey, $address) {
        $this->increaseMetrics('geocode_calls');
        $curl = curl_init();

        $escapedAddress = curl_escape($curl, $address);

        // google map geocode api url
        $url = "https://maps.googleapis.com/maps/api/geocode/json?key={$apikey}&address={$escapedAddress}";

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $curl_response = curl_exec($curl);
        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($curl_response === false) {
            $info = curl_getinfo($curl);
            error_log('Geocoding failed with status code: ' . $httpcode);
            error_log('Could not geocode the following entry: ' . curl_error($curl));
            curl_close($curl);
            return false;
        }

        curl_close($curl);

        $resp = json_decode($curl_response, true);

        // response status will be 'OK', if able to geocode given address 
        if($resp['status'] == 'OK'){
     
            // get the important data
            $lati = isset($resp['results'][0]['geometry']['location']['lat']) ? $resp['results'][0]['geometry']['location']['lat'] : "";
            $longi = isset($resp['results'][0]['geometry']['location']['lng']) ? $resp['results'][0]['geometry']['location']['lng'] : "";
            $formatted_address = isset($resp['results'][0]['formatted_address']) ? $resp['results'][0]['formatted_address'] : "";
             
            // verify if data is complete
            if($lati && $longi && $formatted_address){
                return array($lati, 
                        $longi, 
                        $formatted_address);            
                 
            } else{
                error_log("Could not find lat&long for address: ". $address." with information: ".print_r($resp, true));
                return false;
            }
             
        } else if(strtolower($resp['status']) === strtolower("OVER_QUERY_LIMIT")){
            error_log("Reached query limit ". $address ." with information: ".print_r($resp, true));
            $this->increaseMetrics('query_limit_hits');
            return false;

        } else{
            error_log("Error during geocoding ". $address ." with information: ".print_r($resp, true));
            return false;
        }
    }

    public function dataExists(){
        return $this->meetup_data && $this->original_meetups;
    }

    public function refresh() {
        $this->increaseMetrics('api_requests');
        $apikey = get_option( 'elementor-lg-map-plugin_settings' )['api_key'];
        $csvUrl = get_option( 'elementor-lg-map-plugin_settings' )['meetups_url'];

        $this->loadCSV($csvUrl);
        $this->prepareData($apikey);
    }

    function increaseMetrics($identifier){
        $options = get_option(  'elementor-lg-map-plugin_metrics'  );
   
        if($options && array_key_exists($identifier, $options))  {
            $options[$identifier] = $options[$identifier]+1;
        } else {
            $options[$identifier] = 1;
        }

        update_option('elementor-lg-map-plugin_metrics' , $options);
    }

    function resetMetrics(){
        $options = get_option(  'elementor-lg-map-plugin_metrics'  );
        foreach ($options as $key => $value){
            $options[$key] = 0;
        }
        update_option('elementor-lg-map-plugin_metrics' , $options);
    }


    function updateLoadTimer(){
        $options = get_option(  'elementor-lg-map-plugin_settings'  );

        $current_date = new DateTime(null, new DateTimeZone('Europe/Stockholm'));
        $options['meetup_csv_load_time'] =  $current_date->format("H:i:s d.m.Y");

        update_option('elementor-lg-map-plugin_settings' , $options);
    }

    function resetLoadTimer(){
        $options = get_option(  'elementor-lg-map-plugin_settings'  );

        $options['meetup_csv_load_time'] =  null;

        update_option('elementor-lg-map-plugin_settings' , $options);
    }

    function getFrontendCacheDuration(){
        return get_option( 'elementor-lg-map-plugin_settings' )['cache_duration'] ? get_option( 'elementor-lg-map-plugin_settings' )['cache_duration'] : 1800;
    }

    function getBackendCacheDuration(){
        return get_option( 'elementor-lg-map-plugin_settings' )['backend_cache_duration'] ? get_option( 'elementor-lg-map-plugin_settings' )['backend_cache_duration'] : 86400;
    }


    function getMeetupsByLocation(){
        $meetupDataByLocation = array();
        foreach($this->meetup_data as $row){
            if(!array_key_exists($row['location'], $meetupDataByLocation)) {
                $meetupDataByLocation[$row['location']] = array(
                         'location' => $row['location'],
                         'city' =>  $row['city'],
                         'usedAddress' => $row['usedAddress'],
                         'formatted_address' => $row['formatted_address'],
                         'geodata' => $row['geodata'],
                         'meetups' => array(
                                array(
                                 'lecturer' => $row['lecturer'],
                                 'date' => $row['date'],
                                 'time' => $row['time']
                             )
                            )
                     );
            } else {
                $meetupDataByLocation[$row['location']]['meetups'][] = array(
                                         'lecturer' => $row['lecturer'],
                                         'date' => $row['date'],
                                         'time' => $row['time']
                                     );
            }
        }

        return $meetupDataByLocation;
    }

    // API Endpoints
    function getAllMeetups(WP_REST_Request $request) {
        $groupByLocation = $request->get_param( 'groupByLocation' );

        $result = null;

        if($groupByLocation){
            $result = new WP_REST_Response($this->getMeetupsByLocation(), 200);
        } else {
            $result = new WP_REST_Response($this->meetup_data, 200);
        }
        

        // Set headers.
        $result->set_headers(array('Cache-Control' => 'max-age='.$this->getFrontendCacheDuration()));

        return $result;
    }

    function getOriginalData(WP_REST_Request $request) {
        $result = new WP_REST_Response($this->original_meetups, 200);

        // Set headers.
        $result->set_headers(array('Cache-Control' => 'max-age='.$this->getFrontendCacheDuration()));

        return $result;
    }
    

    public static function get_instance() {
        if ( ! isset( static::$instance ) ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

   
  
}




