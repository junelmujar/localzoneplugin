==== Table Rates for WooCommerce ====
Contributors: junelmujar
Tags: woocommerce, zone rates, shipping method, free shipping, local zone, local shipping
Requires at least: 3.0
Tested up to: 3.8
Stable tag: 0.1
License: GPLv2 or later

Local Zone Rates allow you to define seperate rates for city & provincial shipping.

It also allows you to define the shipping rate in relation to the total weight of items for
shipping. Please note that weight is calculated to the nearest ceiling. Example:

	0.8 kg will be rounded off to 1kg
	1.2 kg will be rounded off to 2kg

It also allows you to override the shipping fee if a certain product of a certain
category matches the override rule for the line cart item.

	Example:

	Free shipping for Product A of Category B will only take effect if it reaches 
	the minimum free shipping quantity defined. Example:

	Category B = 5 (Minimum free shipping quantity)

	Free Shipping Scenario:
		Cart:

		Product A (of Categry B)		Qty: 6		Price: 1,200.00
		Shipping & Handling:						Free Shipping

	Non Free Shipping Scenario:

		Product A (of Categry B)		Qty: 3		Price: 600.00
		Shipping & Handling:						Free Shipping

== Description ==

= 0.1 =
* Initial Release
