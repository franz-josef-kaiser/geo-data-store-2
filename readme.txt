=== Plugin Name ===
Contributors: l3rady
Donate link: http://l3rady.com/donate
Tags: geo, location, latitude, longitude, plugin
Requires at least: 3.2
Tested up to: 3.4.1
Stable tag: 2.0.1

Stores lng/lat co-ordinates in a better optimized table. This plugin is meant for the use of other WordPress theme and plugin authors

== Description ==

This plugin is meant to be used by other developers and to be used together with themes and other plugins. Many themes and plugins use WordPress meta data table to store longitude and latitude co-ordinates for posts. While this works fine the meta data table cannot be indexed very well. Let's take for example you have made a custom post type called 'properties'. You create 100,000 posts all attached with latitude and longitude co-ordinates. You want your users to search for those properties in a 50 mile radius for example. Because of the method of which WordPress stores the meta data the query is slow especially when dealing with large amounts of data.

This plugin has been made to retro fit your current WordPress install. You as the developer select, using filters, what meta data you want to start capturing and this plugin will put the data in a table better optimized for storing latitude and longitude co-ordinates. Upon plugin activate existing data will be index and any data from then on.

Usage:
Before activating set what meta keys you want this plugin to capture by using the filter 'sc_geodatastore_meta_keys'. Your latitude and longitude values have to be stored in a single meta field like: `51.507334,-0.127682`

Example usage of the filter:

	add_filter( 'sc_geodatastore_meta_keys', 'homes_for_sale_geodata' );
	function homes_for_sale_geodata( $keys )
	{
		$keys[] = "properties_address_coords";
		return $keys;
	}

Notice:
This plugin is currently limited to only allowing one pair of co-ordinates per post. Maybe in a later version I will allow multiple pairs per post, but since this plugin was made to serve my purpose and it serves it well I see no reason to build upon it yet.

== Installation ==

* Upload to plugins dir
* Activate plugin
* Add `sc_geodatastore_meta_keys` filter to your functions of plugin file to set what meta data keys to capture.

== Changelog ==

= 2.0.1 =
* Mostly rewritten.
* Now only supports co-ordinates to be stored in a single meta field (Storing in separate meta fields wasn't really workable without causing problems with removing rows from the store on post delete)
* Entries in data store are now removed when meta data is deleted or post with meta data is deleted.

= 1.3 =
* --- Never Publicly Released ---
* Fixed query in getPostIDsByRange() to use correct table name, not `t`.
* Fixed PHP notice on activation. Thanks @Kaiser http://chat.stackexchange.com/transcript/message/2758673#2758673
* Now hooking into `update_postmeta` and `delete_postmeta`. Credits to @sebastien.b for this http://wordpress.stackexchange.com/a/26438/4610
* Table now stores meta_id as needed to achieve above.

= 1.2 =
* Added link on plugin screen that allows you to force a re-index.
* Added function getPostIDsByRange() that includes ready made SQL for returning all post ID's in order by distance of a given point

= 1.1 =
* Changed DB key not to be UNIQUE but to be INDEX
* Added function getPostIDsOfInRange() that includes ready made SQL for searching for posts by that are within a given radius from a given point

= 1.0 =
* Initial release