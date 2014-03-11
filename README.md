# Geo Data Store

A WordPress plugin to store latitude and longitude information in a separate (and better optimized) table.

This plugin is a fork of the original (now unmaintained and not available on GitHub)
"Geo Data Store" plugin by Scott "l3rady" Carris _[home](http://l3rady.com/) and [on GitHub](https://github.com/l3rady)_.

Scott by now is possibly married and I wish him all the best.

## Install

If you´re using Composer, simply add the following line to your `require` array:

    "wcm/geo-data-store-2" : "dev-master"

## Usage

Before activating set what meta keys you want this plugin to capture
by using the filter `sc_geodatastore_meta_keys`.

	add_filter( 'sc_geodatastore_meta_keys', 'homes_for_sale_geodata' );
	function homes_for_sale_geodata( $keys )
	{
		$keys[] = "properties_address_coords";
		return $keys;
	}

Your latitude and longitude values have to be stored in a single meta field as comma separated value:

    `51.507334,-0.127682`

### License

Inherited license from the original software: GPL v2+