=== Plugin Name ===
Contributors: l3rady
Donate link: http://l3rady.com/donate
Tags: geo, location, latitude, longitude, plugin
Requires at least: 3.1
Tested up to: 3.1.2
Stable tag: 1.1

Stores lng/lat co-ordinates in a better optimized table. This plugin is meant for the use of other WordPress theme and plugin authors

== Description ==

This plugin is meant to be used by other developers and to be used together with themes and other plugins. Many themes and plugins use WordPress meta data table to store longitude and latitude co-ordinates for posts. While this works fine the meta data table cannot be indexed very well. Let’s take for example you have made a custom post type called 'properties'. You create 100,000 posts all attached with latitude and longitude co-ordinates. You want your users to search for those properties in a 50 mile radius for example. Because of the method of which WordPress stores the meta data the query is slow especially when dealing with large amounts of data.

This plugin has been made to retro fit your current WordPress install. You as the developer select, using filters, what meta data you want to start capturing and this plugin will put the data in a table better optimized for storing latitude and longitude co-ordinates. Upon plugin activate existing data will be index and any data from then on.

Usage:
Before activating set what meta keys you want this plugin to capture by using the filter 'sc_geodatastore_meta_keys'. If you store your co-ordinates in one meta data field like '51.513123,-0.089006' then you need to add the key of that field to $keys['latlng'], but if you store your latitude and longitude values in a separate meta data fields then you need to add the keys of those fields to $keys['lat'] and $keys['lng'].

Example:

	add_filter('sc_geodatastore_meta_keys', 'homes_for_sale_geodata');
	function homes_for_sale_geodata($keys) {	
		$keys['lat'][] = "chb_homes_for_sale_address_latitude";
		$keys['lng'][] = "chb_homes_for_sale_address_longitude";
		return $keys;
	}
	
Credits:
Big thanks to Jan Fabry for helping me with the mammoth re-index query. I thought it couldn’t be done in one query and he proved me wrong :P

Notice:
This plugin is currently limited to only allowing one pair of co-ordinates per post. Maybe in a later version I will allow multiple pairs per post, but since this plugin was made to serve my purpose and it serves it well I see no reason to build upon it yet.

== Installation ==

* Upload to plugins dir
* Activate plugin
* Add `sc_geodatastore_meta_keys` filter to your functions of plugin file to set what meta data keys to capture.

== Changelog ==

= 1.1 =
* Changed DB key not to be UNIQUE but to be INDEX
* Added function getPostIDsOfInRange() that includes ready made SQL for searching for posts by that are within a given radius from a given point

= 1.0 =
* Initial release