<?php

namespace WCM;

/**
 * Plugin Name: Geo Data Store 2
 * Plugin URI:  https://github.com/franz-josef-kaiser/geo-data-store-2
 * Description: Stores lng/lat co-ordinates in a better optimized table
 * Author:      Franz Josef Kaiser
 * Version:     1.0.0
 * Author URI:  http://unserkaiser.com/
 */

add_action( 'plugins_loaded', 'WCM\GeoDataStoreInit' );
function GeoDataStoreInit()
{
	sc_GeoDataStore::init();
}

class sc_GeoDataStore
{
	// Current version of the DB
	private static $db_version = "2.0";
    private static $tablename = "geodatastore";

	public static function init()
    {
		// Internationalization
		load_plugin_textdomain( 'geo-data-store', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

		// Create action hook to allow DB to be re-indexed
		add_action( 'sc_geodatastore_reindex', array( __CLASS__ , 'reindex' ) );

		// Hook into when Wordpress updates or adds meta data
		add_action( 'added_post_meta', array( __CLASS__, 'after_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'after_post_meta' ), 10, 4 );
        add_action( 'updated_postmeta', array( __CLASS__, 'after_post_meta' ), 10, 4 );

		// Hook into when Wordpress deletes meta data
		add_action( 'deleted_post_meta', array( __CLASS__, 'delete_post_meta' ), 10, 4 );
        add_action( 'deleted_postmeta', array( __CLASS__, 'delete_postmeta' ), 10, 1 );

        // Setup filter on keys so has some valid data
        add_filter( 'sc_geodatastore_meta_keys', array( __CLASS__, 'setup_keys' ), 1, 1 );

		// Setup activation hook
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );

		// Define the permission to re-index
		if( ! defined( 'SC_GDS_REINDEX_PERMISSION' ) )
            define( 'SC_GDS_REINDEX_PERMISSION', 'manage_options' );

		// Add reindex plugin link
		add_filter( 'plugin_action_links', array( __CLASS__, 'plugin_action_links' ), 10, 2 );
		add_action( 'init', array( __CLASS__, 'things_to_do' ) );

		// Check we are running latest DB
		self::checkDBVersion();
	}


    /*
     * Setup keys to be an empty array on startup
     */
    public static function setup_keys( $keys )
    {
        return array();
    }


	/**
	 * Adds Re-Index Table link to plugin.
	*/
	public static function plugin_action_links( $links, $file )
    {
		if ( plugin_basename( __FILE__ ) === $file )
        {
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'plugins.php?sc_geodatastore_reindex=1' ),
				__( 'Re-index Table', 'geo-data-store' )
			);
			array_unshift( $links, $settings_link );
		}

		return $links;
	}


    /*
     * Checks if a re-index has been requested
     */
	public static function things_to_do()
    {
        if (
	        isset( $_GET['sc_geodatastore_reindex'] )
	        AND 1 == intval( $_GET['sc_geodatastore_reindex'] )
	        AND current_user_can( SC_GDS_REINDEX_PERMISSION )
        )
            do_action( "sc_geodatastore_reindex" );
	}


    /*
     * On start check that the DB is at the correct version and structure
     */
	private static function checkDBVersion()
    {
		global $wpdb;

        // Get current DB version of plugin
		$current_db_version = get_option( "sc_gds_db_version" );

        if( $current_db_version == self::$db_version )
            return;

        $sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . self::$tablename . "` (
                  `post_id` int(11) NOT NULL,
                  `meta_id` int(11) NOT NULL,
                  `post_type` varchar(20) NOT NULL,
                  `lat` float(10,6) DEFAULT NULL,
                  `lng` float(10,6) DEFAULT NULL,
                  PRIMARY KEY  (`post_id`),
                  KEY `post_type` (`post_type`,`lat`,`lng`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

        require_once( ABSPATH . "wp-admin/includes/upgrade.php" );

        dbDelta( $sql );

        // Update options with new DB version
        update_option( "sc_gds_db_version", self::$db_version );
	}


	/**
	 * Hooked into added_post_meta and updated_post_meta
	 *
	 * @param int $meta_id Meta data row ID
	 * @param int $post_id Post ID
	 * @param string $meta_key Meta data key name
	 * @param string $meta_value Meta data value
	*/
	public static function after_post_meta( $meta_id, $post_id, $meta_key, $meta_value )
    {
		self::meta_data_captured( $meta_id, $post_id, $meta_key, "add", $meta_value );
	}


	/**
	 * Hooked into deleted_post_meta
	 *
	 * @param array $deleted_meta_ids Array of Meta data row ID's that were deleted
	 * @param int $post_id Post ID
	 * @param string $meta_key Meta data key name
	 * @param array $only_delete_these_meta_values Dunno whats here but dont need it anywway...
	*/
	public static function delete_post_meta( $deleted_meta_ids, $post_id, $meta_key, $only_delete_these_meta_values )
    {
		self::meta_data_captured( $deleted_meta_ids, "", "", "delete" );
	}


	/**
	 * Hooked into deleted_postmeta
	 *
	 * @param int $mid ID of meta
	*/
    public static function delete_postmeta( $mid )
    {
        self::meta_data_captured( $mid, "", "", "delete" );
    }


	private static function meta_data_captured( $meta_ids, $post_id, $meta_key, $action = "add", $meta_value = 0 )
    {
		global $wpdb;

		$keys = apply_filters( 'sc_geodatastore_meta_keys', null );

		if ( ! in_array( $meta_key, $keys ) )
            return;

        if ( $action == "add" )
        {
            $post_type = get_post_type( $post_id );

            $coords = explode( ',', $meta_value );

            $wpdb->query( "INSERT INTO `" . $wpdb->prefix . self::$tablename . "` (`post_id`, `meta_id`, `post_type`, `lat`, `lng`) VALUES (".(int) $post_id.", ".(int) $meta_ids.", '{$post_type}', '".(float) $coords[0]."', '".(float) $coords[1]."') ON DUPLICATE KEY UPDATE `lat` = '".(float) $coords[0]."', `lng` = '".(float) $coords[1]."'");

            return;
        }

        if ( $action == "delete" )
        {
            $meta_ids = (array) $meta_ids;

            $wpdb->query( "DELETE FROM `" . $wpdb->prefix . self::$tablename . "` WHERE `meta_id` IN({$meta_ids})" );

            return;
        }

		return;
	}


    /*
     * On active check WordPress version is greater than 3.1 and then re-index the data store
     */
	public static function activate()
    {
		if ( version_compare( get_bloginfo( 'version' ), '3.1', '<' ) )
        {
            deactivate_plugins( basename( __FILE__ ) );
            return;
        }

        // Re-index data store
		do_action( "sc_geodatastore_reindex" );
	}


    /*
     * Re-indexes the data store
     */
	public static function reindex()
    {
		global $wpdb;

		// Apply filters to keys
        $keys = apply_filters( 'sc_geodatastore_meta_keys', null );

        // Return if $keys is an empty array
        if( array() === $keys )
            return;

        $sql  = "TRUNCATE TABLE " . $wpdb->prefix . self::$tablename . ";";
        $wpdb->query($sql);

        $sql = "INSERT INTO " . $wpdb->prefix . self::$tablename . " (`post_id`, `meta_id`, `post_type`, `lat`, `lng`) ";
        $sql .= "
            SELECT
                `{$wpdb->posts}`.`ID` AS `post_id`,
                `{$wpdb->postmeta}`.`meta_id` AS `meta_id`,
                `{$wpdb->posts}`.`post_type`,
                SUBSTRING_INDEX( `{$wpdb->postmeta}`.`meta_value`, ',', 1) AS lat,
                SUBSTRING_INDEX( `{$wpdb->postmeta}`.`meta_value`, ',', -1) AS lng
            FROM
                `{$wpdb->posts}`
                JOIN `{$wpdb->postmeta}` ON (`{$wpdb->posts}`.`ID` = `{$wpdb->postmeta}`.`post_id` AND `{$wpdb->postmeta}`.`meta_key` IN ('".implode('\', \'',$keys)."'))
            ";

        $sql .= " ON DUPLICATE KEY UPDATE `lat` = VALUES(`lat`), `lng` = VALUES(`lng`)";
        $wpdb->query( $sql );

	}


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
	public static function getPostIDsOfInRange( $post_type, $radius = 50, $search_lat = 51.499882, $search_lng = -0.126178, $orderby = "ASC" )
	{
		global $wpdb;// Dont forget to include wordpress DB class

		// Calculate square radius search
		$lat1 = (float) $search_lat - ( (int) $radius / 69 );
		$lat2 = (float) $search_lat + ( (int) $radius / 69 );
		$lng1 = (float) $search_lng - (int) $radius / abs( cos( deg2rad( (float) $search_lat ) ) * 69 );
		$lng2 = (float) $search_lng + (int) $radius / abs( cos( deg2rad( (float) $search_lat ) ) * 69 );

		$sqlsquareradius = "
		SELECT
			`" . $wpdb->prefix . self::$tablename . "`.`post_id`,
			`" . $wpdb->prefix . self::$tablename . "`.`lat`,
			`" . $wpdb->prefix . self::$tablename . "`.`lng`
		FROM
			`" . $wpdb->prefix . self::$tablename . "`
		WHERE
			`" . $wpdb->prefix . self::$tablename . "`.`post_type` = '{$post_type}'
		AND
			`" . $wpdb->prefix . self::$tablename . "`.`lat` BETWEEN '{$lat1}' AND '{$lat2}'
		AND
			`" . $wpdb->prefix . self::$tablename . "`.`lng` BETWEEN '{$lng1}' AND '{$lng2}'
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
			({$sqlsquareradius}) AS `t`
		HAVING
			`distance` <= ".(int) $radius."
		ORDER BY `distance` {$orderby}
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
	public static function getPostIDsByRange( $post_type, $search_lat = 51.499882, $search_lng = -0.126178, $orderby = "ASC" )
	{
		// Dont forget to include wordpress DB class
		global $wpdb;

		// Create sql for distance check
		$sqldistancecheck = "
		SELECT
			`" . $wpdb->prefix . self::$tablename . "`.`post_id`,
			3956 * 2 * ASIN(
				SQRT(
					POWER(
						SIN(
							( ".(float) $search_lat." - `" . $wpdb->prefix . self::$tablename . "`.`lat` ) * pi() / 180 / 2
						), 2
					) + COS(
						".(float) $search_lat." * pi() / 180
					) * COS(
						`" . $wpdb->prefix . self::$tablename . "`.`lat` * pi() / 180
					) * POWER(
						SIN(
							( ".(float) $search_lng." - `" . $wpdb->prefix . self::$tablename . "`.`lng` ) * pi() / 180 / 2
						), 2
					)
				)
			) AS `distance`
		FROM
			`" . $wpdb->prefix . self::$tablename . "`
		WHERE
			`" . $wpdb->prefix . self::$tablename . "`.`post_type` = '{$post_type}'
		ORDER BY `distance` {$orderby}
		"; // End $sqldistancecheck

		return $wpdb->get_col( $sqldistancecheck );
	}
}