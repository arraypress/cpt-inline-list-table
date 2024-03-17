# Custom Post Type Inline List Table for WordPress

By leveraging the Custom Post Type Inline List Table library, developers can easily register, display, and manage custom
post types with enhanced functionalities such as custom columns, script and style enqueues, admin menu highlighting, and
more. Designed for seamless integration with WordPress core functionalities, this tool streamlines the administrative
tasks associated with custom post types, offering a developer-friendly approach to customization and management.

**Key Features:**

- **Custom Column Management:** Define and manage custom columns for your post type's list table, providing a tailored
  view of your data.
- **Script and Style Enqueues:** Specify admin screens where custom scripts and styles should be loaded, enhancing the
  admin UI and UX.
- **Admin Menu Highlighting:** Maintain menu and submenu highlighting within the admin sidebar, improving navigation and
  usability.
- **Flexible Pagination:** Control the number of items displayed per page in the list table, supporting efficient data
  handling and presentation.
- **Streamlined Integration:** Utilize hooks and WordPress core functions for a smooth integration, adhering to
  WordPress standards and best practices.

This library facilitates the creation of a refined administrative interface for custom post types, ensuring a coherent
and efficient management experience within the WordPress admin.

## Installation

Ensure you have the package installed in your project. If not, you can typically include it using Composer:

```php
composer require arraypress/cpt-inline-list-table
```

### Example Usage

The `register_inline_list_table` function allows for easy setup and configuration of your custom post type's inline list
table. Here's how to use it:

```php
// Example usage of register_inline_table_post_type to create a 'Conditional Fee' custom post type.
register_inline_table_post_type(
    'conditional_fee',                                  // The key for the custom post type.
    __( 'Conditional Fee', 'edd-conditional-fees' ),    // The singular name of the custom post type for labels.
    __( 'Conditional Fees', 'edd-conditional-fees' ),   // The plural name of the custom post type for labels.
    'conditional_fee',                                  // The slug for the custom post type.
    [ 'excerpt', 'custom-fields', 'editor' ],           // (Optional) Additional features the post type supports.
    false                                               // (Optional) Whether to expose this post type in the WordPress REST API. Enables use of the Gutenberg editor and REST API queries.
);

/**
 * Defines columns for the list table of a custom post type, showcasing conditional discounts.
 * This configuration automatically includes default columns such as title, date, and author.
 * Additional custom columns can specify callbacks for rendering their content or use formatters
 * for specific data presentation. If only a key and label are provided (without a callback),
 * the system will first look for a matching property in the post object, then check post meta.
 *
 * When defining columns, it's crucial to understand the built-in logic for data retrieval:
 *
 * 1. If a 'callback' is provided, it will be used to fetch and render the column's data.
 * 2. Without a 'callback', the system searches for a matching property within the post object.
 * 3. If not found in the post object, the system then searches the post meta.
 * 4. A 'formatter' function can be used to format the value obtained from the callback or automatic data retrieval.
 *
 * This approach provides flexibility in displaying both standard and custom data within your list table.
 */

$columns = [
	// Example of a custom column with a callback and formatter.
	'amount'          => [
		'label'     => __( 'Amount', 'edd-conditional-fees' ),
		'callback'  => function ( $post ) {
			return get_post_meta( $post->ID, 'amount', true );
		},
		'formatter' => function ( $value, $post ) {
			return edd_currency_filter( edd_format_amount( $value ) );
		},
	],
	// Example of a simple column that relies on automatic data sourcing.
	'expiration_date' => [
		'label' => __( 'Expiration Date', 'edd-conditional-fees' ),
		// No callback needed; the system will automatically search for 'expiration_date' in post object or meta.
	]
];

// Registers an inline list table for a specified custom post type, configuring it with
// custom columns, administrative URLs, and settings for menu highlighting.
register_inline_list_table(
	'conditional_fee', // The custom post type identifier.
	$columns, // Associative array of columns with render callbacks and formatters.
	'edd_conditional_fees_table', // Hook name to attach the list table initialization.
	10, // Priority for the hook to control when the list table is initialized.
	'edit.php?post_type=download&page=edd-settings&tab=extensions', // URL for admin redirects.
	[ 'download_page_edd-settings' ], // Admin screens where scripts/styles should be enqueued.
	'edit.php?post_type=download', // Parent file slug for menu highlighting.
	'edd-settings' // Submenu file slug for submenu highlighting.
);

// Registers a settings section for managing conditional fees within the extension settings.
function register_section( array $sections ): array {
	$sections['conditional_fees'] = __( 'Conditional Fees', 'edd-conditional-fees' );

	return $sections;
}

add_filter( 'edd_settings_sections_extensions', __NAMESPACE__ . '\\register_section' );

// Adds settings for the 'Conditional Fees' section within the extension settings, enabling the configuration of rules.
function register_settings( array $existing_settings ): array {
	return array_merge( $existing_settings, [
		'conditional_fees' => [
			[
				'id'   => 'conditional_fees_table',
				'name' => __( 'Conditional Fees', 'edd-conditional-fees' ),
				'type' => 'hook',
			],
		]
	] );
}

add_filter( 'edd_settings_extensions', __NAMESPACE__ . '\\register_settings' );
```

## Contributions

We welcome contributions to enhance the library's functionality and compatibility. Feel free to submit pull requests or
report issues on our GitHub repository.

## License

The Custom Post Type Inline List Table library is open-sourced software licensed under the GPL-2.0-or-later license. It
is free for personal and commercial use, adhering to the terms of the GNU General Public License.