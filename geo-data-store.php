<?php
/*
Plugin Name: Geo Data Store
Plugin URI: http://l3rady.com/projects/geo-data-store/
Description: Stores lng/lat co-ordinates in a better optimized table
Author: Scott Cariss
Version: 1.2
Author URI: http://l3rady.com/
*/

/*
Full Description:
This plugin is meant to be used by other developers and to be used together with
themes and other plugins. Many themes and plugins use WordPress meta data table
to store longitude and latitude co-ordinates for posts. While this works fine
the meta data table cannot be indexed very well. Let’s take for example you
have made a custom post type called 'properties'. You create 100,000 posts all
attached with latitude and longitude co-ordinates. You want your users to search
for those properties in a 50 mile radius for example. Because of the method of
which WordPress stores the meta data the query is slow especially when dealing
with large amounts of data.

This plugin has been made to retro fit your current WordPress install. You as
the developer select, using filters, what meta data you want to start capturing
and this plugin will put the data in a table better optimized for storing latitude
and longitude co-ordinates. Upon plugin activate existing data will be index and
any data from then on.

Usage:
Before activating set what meta keys you want this plugin to capture by using
the filter 'sc_geodatastore_meta_keys'. If you store your co-ordinates in one
meta data field like '51.513123,-0.089006' then you need to add the key of that
field to $keys['latlng'], but if you store your latitude and longitude values in
a separate meta data fields then you need to add the keys of those fields to
$keys['lat'] and $keys['lng'].

Example:

	add_filter('sc_geodatastore_meta_keys', 'homes_for_sale_geodata');
	function homes_for_sale_geodata($keys) {	
		$keys['lat'][] = "chb_homes_for_sale_address_latitude";
		$keys['lng'][] = "chb_homes_for_sale_address_longitude";
		return $keys;
	}
	
Credits:
Big thanks to Jan Fabry for helping me with the mammoth re-index query. I thought
it couldn’t be done in one query and he proved me wrong :P

Notice:
This plugin is currently limited to only allowing one pair of co-ordinates per post.
Maybe in a later version I will allow multiple pairs per post, but since this plugin
was made to serve my purpose and it serves it well I see no reason to build upon it yet.

*/

/*  Copyright 2011  Scott Cariss  (email : scott@l3rady.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if (!class_exists('sc_GeoDataStore')) {
	class sc_GeoDataStore {
		
		
		private static $db_version = "1.1"; // Current version of the DB
		protected $keys = array( // Array to hold meta data keys that store longitude and latitude values
							'lat' => array(), // just latitude keys
						  	'lng' => array(), // just longitude keys
							'latlng' => array() // latitude,longitude keys
						  );
		public $tablename = ""; // name of table
		
		
		/**
		 * I AM CONSTRUCT!
		 *
		 * Pretty much self explanatory. Just setup my own action and hook into others.
		 * Setup activation hook and check we running latest version of DB.
		*/
		function __construct() {
			// Internationalization
			load_plugin_textdomain('geo-data-store', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
			// Create action hook to allow DB to be re-indexed
			add_action('sc_geodatastore_reindex', array(&$this, 'reindex'));
			// Hook into when Wordpress updates or adds meta data
			add_action('added_post_meta', array(&$this, 'after_post_meta'), 10, 4);
			add_action('updated_post_meta', array(&$this, 'after_post_meta'), 10, 4);
			// Hook into when Wordpress deletes meta data
			add_action('deleted_post_meta', array(&$this, 'deleted_post_meta'), 10, 4);
			// Setup activation hook
			register_activation_hook(__FILE__, array(&$this, 'activate'));
			// Define the permission to re-index
			if(!defined('SC_GDS_REINDEX_PERMISSION')) {define('SC_GDS_REINDEX_PERMISSION', 'manage_options');}
			// Add reindex plugin link
			add_filter('plugin_action_links', array(&$this, 'plugin_action_links'), 10, 2);
			add_action('init', array(&$this, 'things_to_do')); // Check for things to do when needed
			// Check we are running latest DB
			$this->checkDBVersion();
		} // End __construct function
		
		
		/**
		 * Adds Re-Index Table link to plugin.
		*/
		public function plugin_action_links($links, $file) {
			static $this_plugin;
			
			if (!$this_plugin) {
				$this_plugin = plugin_basename(__FILE__);
			}
			
			if ($file == $this_plugin){
				$settings_link = '<a href="'.site_url().'/wp-admin/plugins.php?sc_geodatastore_reindex=1">'.__("Re-index Table", "geo-data-store").'</a>';
				array_unshift($links, $settings_link);
			}
			
			return $links;
		} // End plugin_action_links function
		
		
		/**
		 * Lookout for actions this plugin needs to perform on
		*/
		public function things_to_do() {
			if((int) $_GET['sc_geodatastore_reindex'] == 1 && current_user_can(SC_GDS_REINDEX_PERMISSION)) {
				do_action("sc_geodatastore_reindex");
			}
		}
		
		
		/**
		 * Check we have latest we are using latest version of DB
		*/
		protected function checkDBVersion() {
			global $wpdb; // Dont forget to include wordpress DB class
			$current_db_version = get_option("sc_gds_db_version"); // Get what version DB this plugin is running
			$this->tablename = $wpdb->prefix."geodatastore"; // Set the table name being used by this plugin
			
			// Does the current table version match the version used in this plugin?
			if($current_db_version != self::$db_version) {
				// No. Lets create table.
				$sql = "CREATE TABLE IF NOT EXISTS `".$this->tablename."` (
						  `post_id` int(11) NOT NULL,
						  `post_type` varchar(20) NOT NULL,
						  `lat` float(10,6) NOT NULL,
						  `lng` float(10,6) NOT NULL,
						  PRIMARY KEY  (`post_id`),
						  KEY `post_type` (`post_type`,`lat`,`lng`)
						) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
				// Include upgrade.php because it has dbDelta() function that we need
				require_once(ABSPATH . "wp-admin/includes/upgrade.php");
				dbDelta($sql); // Create DB table. More on dbDelta here: http://codex.wordpress.org/Creating_Tables_with_Plugins
				update_option("sc_gds_db_version", self::$db_version); // set the latest DB version
			} // End if current DB version = to plugin DB
			
		} // End checkDBVersion function
		
		
		/**
		 * Hooked into added_post_meta and updated_post_meta
		 *
		 * @param int $meta_id Meta data row ID
		 * @param int $post_id Post ID
		 * @param string $meta_key Meta data key name
		 * @param string $meta_value Meta data value
		*/
		public function after_post_meta($meta_id, $post_id, $meta_key, $meta_value) {
			$this->meta_data_captured($post_id, $meta_key, "add", $meta_value);
		} // End after_post_meta function
		
		
		/**
		 * Hooked into deleted_post_meta
		 * 
		 * @param array $deleted_meta_ids Array of Meta data row ID's that were deleted
		 * @param int $post_id Post ID
		 * @param string $meta_key Meta data key name
		 * @param array $only_delete_these_meta_values Dunno whats here but dont need it anywway...
		*/
		public function deleted_post_meta($deleted_meta_ids, $post_id, $meta_key, $only_delete_these_meta_values) {
			$this->meta_data_captured($post_id, $meta_key, "delete");
		} // End deleted_post_meta function
		
		
		/**
		 * Deal with captured meta save or delete
		 *
		 * @param int $post_id ID of post meta data being saved
		 * @param string $meta_key Post meta key name
		 * @param string $action Are we adding or removing
		 * @param string $meta_value
		*/
		protected function meta_data_captured($post_id, $meta_key, $action = "add", $meta_value = 0) {
			global $wpdb; // Dont forget to include wordpress DB class
			
			// Apply filters to keys
			$this->keys = apply_filters('sc_geodatastore_meta_keys', $this->keys);
			
			// See if the current meta_key is in the array keys
			if($this->in_array_recursive($meta_key, $this->keys)) {
				
				// Are we adding?
				if($action == "add") {
					// What is the post type of this post?
					$post_type = get_post_type($post_id);
					
					// is the meta key a lat key?
					if (in_array($meta_key, $this->keys['lat'])) {
						$wpdb->query("INSERT INTO `".$this->tablename."` (`post_id`, `post_type`, `lat`, `lng`) VALUES (".(int)$post_id.", '".$post_type."', '".(float) $meta_value."', '') ON DUPLICATE KEY UPDATE `lat` = '".(float) $meta_value."'");
					// is the meta key a lng key?
					} elseif(in_array($meta_key, $this->keys['lng'])) {
						$wpdb->query("INSERT INTO `".$this->tablename."` (`post_id`, `post_type`, `lat`, `lng`) VALUES (".(int)$post_id.", '".$post_type."', '', '".(float) $meta_value."') ON DUPLICATE KEY UPDATE `lng` = '".(float) $meta_value."'");
					// is the meta key a latlng key?
					} elseif (in_array($meta_key, $this->keys['latlng'])) {
						$coords = explode(',', $meta_value); // Split the latlng in to lat and lng parts
						$wpdb->query("INSERT INTO `".$this->tablename."` (`post_id`, `post_type`, `lat` `lng`,) VALUES (".(int)$post_id.", '".$post_type."', '".(float) $coords[0]."', '".(float) $coords[1]."') ON DUPLICATE KEY UPDATE `lat` = '".(float) $coords[0]."' AND `lng` = '".(float) $coords[1]."'");
					}
				
				// We must be deleting
				} elseif($action == "delete") {
					$wpdb->query("DELETE FROM `".$this->tablename."` WHERE `post_id` = ".$post_id." LIMIT 1");
				}
				
			} // End if current meat_key is in the array keys
			
		} // End meta_data_captured fun ction
		
		
		/**
		 * Works same as in_array() but is recursive
		*/
		public function in_array_recursive($needle, $haystack) {
			$it = new RecursiveIteratorIterator(new RecursiveArrayIterator($haystack));
			foreach($it as $element) {
				if($element == $needle) {
					return true;
				}
			}
			return false;
		}
		
		
		/**
		 * Run on plugin activation
		*/
		public function activate() {
			if ( version_compare( get_bloginfo( 'version' ), '3.1', '<' ) ) {
				deactivate_plugins( basename( __FILE__ ) );
			}
			do_action("sc_geodatastore_reindex");
		}
		
		
		/**
		 * Function to reindex the table. Takes all post meta data
		 * that already exists and places into optimized table.
		*/
		public function reindex() {
			global $wpdb;// Dont forget to include wordpress DB class
			
			// Apply filters to keys
			$this->keys = apply_filters('sc_geodatastore_meta_keys', $this->keys);
			
			// How many keys?
			$count1 = count($this->keys['lat']);
			$count2 = count($this->keys['lng']);
			$count3 = count($this->keys['latlng']);
			
			
			// Only run this if you have set some keys up using the above filter
			if(($count1+$count2+$count3) >= 1) {
				// I'm not going to try and explain this sql as I don't 100% fully undertsand it...
				// Although its laid out nicely so you should be able to make sense of it...
				$sql = "";
				$sql .= "INSERT INTO $this->tablename (`post_id`, `post_type`, `lat`, `lng`) ";
				$sql .= "SELECT * FROM (";
					$sql .= "
						SELECT
							`$wpdb->posts`.`ID` AS `post_id`,
							`$wpdb->posts`.`post_type`,
							`lat_field`.`meta_value` AS `lat`,
							`lng_field`.`meta_value` AS `lng`
						FROM
							`$wpdb->posts`
							LEFT JOIN `$wpdb->postmeta` AS `lat_field` ON (`$wpdb->posts`.`ID` = `lat_field`.`post_id` AND `lat_field`.`meta_key` IN ('".implode('\', \'',$this->keys['lat'])."'))
							LEFT JOIN `$wpdb->postmeta` AS `lng_field` ON (`$wpdb->posts`.`ID` = `lng_field`.`post_id` AND `lng_field`.`meta_key` IN ('".implode('\', \'',$this->keys['lng'])."'))
						WHERE `$wpdb->posts`.`post_status` = 'publish' AND (`lat_field`.`meta_value` IS NOT NULL OR `lng_field`.`meta_value` IS NOT NULL)
					";

					$sql .= " UNION ";

					$sql .= "
						SELECT
							`$wpdb->posts`.`ID` AS `post_id`,
							`$wpdb->posts`.`post_type`,
							SUBSTRING_INDEX( `$wpdb->postmeta`.`meta_value`, ',', 1) AS lat,
							SUBSTRING_INDEX( `$wpdb->postmeta`.`meta_value`, ',', -1) AS lng
						FROM
							`$wpdb->posts`
							JOIN `$wpdb->postmeta` ON (`$wpdb->posts`.`ID` = `$wpdb->postmeta`.`post_id` AND `$wpdb->postmeta`.`meta_key` IN ('".implode('\', \'',$this->keys['latlng'])."'))
					";
				$sql .= ") AS `t`";
				$sql .= " ON DUPLICATE KEY UPDATE `lat` = VALUES(`lat`), `lng` = VALUES(`lng`)";
				// Run the query
				$wpdb->query($sql);
			} // End if any keys
			
		} // End function reindex
		
		
		/**
		 * Get all post id's of those that are in range
		 *
		 * @param string $post_type The post type of posts you are searching
		 * @param int $radius The search radius in MILES
		 * @param float $search_lat The latitude of where you are searching
		 * @param float $search_lng The Longitude of where you are searching
		 * @param string $orderby What order do you want the ID's returned as? ordered by distance ASC or DESC?
		 * @return array $wpdb->get_col() array of ID's of posts in radius. You can use this array in 'post__in' in WP_Query
		*/
		public function getPostIDsOfInRange($post_type, $radius = 50, $search_lat = 51.499882, $search_lng = -0.126178, $orderby = "ASC") {
			global $wpdb;// Dont forget to include wordpress DB class
			
			// Calculate square radius search
			$lat1 = (float) $search_lat - ( (int) $radius / 69 );
			$lat2 = (float) $search_lat + ( (int) $radius / 69 );
			$lng1 = (float) $search_lng - (int) $radius / abs( cos( deg2rad( (float) $search_lat ) ) * 69 );
			$lng2 = (float) $search_lng + (int) $radius / abs( cos( deg2rad( (float) $search_lat ) ) * 69 );
			
			$sqlsquareradius = "
			SELECT
				`$this->tablename`.`post_id`,
				`$this->tablename`.`lat`,
				`$this->tablename`.`lng`
			FROM
				`$this->tablename`
			WHERE
				`$this->tablename`.`post_type` = '".$post_type."'
			AND
				`$this->tablename`.`lat` BETWEEN '".$lat1."' AND '".$lat2."'
			AND
				`$this->tablename`.`lng` BETWEEN '".$lng1."' AND '".$lng2."'
			"; // End $sqlsquareradius
			
			// Create sql for circle radius check
			$sqlcircleradius = "
			SELECT
				`t`.`post_id`,
				3956 * 2 * ASIN(
					SQRT(
						POWER(
							SIN(
								( ".(float) $search_lat." - `t`.`lat` ) * pi() / 180 / 2
							), 2
						) + COS(
							".(float) $search_lat." * pi() / 180
						) * COS(
							`t`.`lat` * pi() / 180
						) * POWER(
							SIN(
								( ".(float) $search_lng." - `t`.`lng` ) * pi() / 180 / 2
							), 2
						)
					)
				) AS `distance`
			FROM
				(".$sqlsquareradius.") AS `t`
			HAVING
				`distance` <= ".(int) $radius."
			ORDER BY `distance` ".$orderby."
			"; // End $sqlcircleradius
			
			return $wpdb->get_col($sqlcircleradius);
			
		} // End function getPostIDsOfInRange
		
		
		/**
		 * Get all post id's ordered by distance from given point
		 *
		 * @param string $post_type The post type of posts you are searching
		 * @param float $search_lat The latitude of where you are searching
		 * @param float $search_lng The Longitude of where you are searching
		 * @param string $orderby What order do you want the ID's returned as? ordered by distance ASC or DESC?
		 * @return array $wpdb->get_col() array of ID's in ASC or DESC order as distance from point
		*/
		public function getPostIDsByRange($post_type, $search_lat = 51.499882, $search_lng = -0.126178, $orderby = "ASC") {
			global $wpdb;// Dont forget to include wordpress DB class
			
			// Create sql for distance check
			$sqldistancecheck = "
			SELECT
				`$this->tablename`.`post_id`,
				3956 * 2 * ASIN(
					SQRT(
						POWER(
							SIN(
								( ".(float) $search_lat." - `t`.`lat` ) * pi() / 180 / 2
							), 2
						) + COS(
							".(float) $search_lat." * pi() / 180
						) * COS(
							`t`.`lat` * pi() / 180
						) * POWER(
							SIN(
								( ".(float) $search_lng." - `t`.`lng` ) * pi() / 180 / 2
							), 2
						)
					)
				) AS `distance`
			FROM
				`$this->tablename`
			WHERE
				`$this->tablename`.`post_type` = '".$post_type."'
			ORDER BY `distance` ".$orderby."
			"; // End $sqldistancecheck
			
			return $wpdb->get_col($sqldistancecheck);
		} // End function getPostIDsByRange
		
	} // End class sc_GeoDataStore
	
} // End if sc_GeoDataStore exists


// Create instance of plugin class
if (!isset($sc_gds) && function_exists('add_action')) {
	$sc_gds = new sc_GeoDataStore();
}
?>