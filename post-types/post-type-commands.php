<?php
namespace WPOrg_Cli\Post_Types;

class Post_Type_Commands {

	/**
	 * @var string $post_type
	 */
	public $post_type = 'commands';

	/**
	 * @var object Singleton Object.
	 */
	private static $instance;

	/**
	 * @return object object of class
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Post_Type_Commands();
		}
		return self::$instance;
	}

	/**
	 * Post_Type_Commands constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'ee_register_post_type' ) );
	}

	/**
	 * Register post type commands
	 */
	public function ee_register_post_type() {

		$args = array(
			'labels'              => array(
				'name'               => __( 'Commands', 'pmc-soaps' ),
				'singular_name'      => __( 'Command', 'pmc-soaps' ),
				'all_items'          => __( 'All Commands', 'pmc-soaps' ),
				'add_new'            => __( 'Add New', 'pmc-soaps' ),
				'add_new_item'       => __( 'Add New Command', 'pmc-soaps' ),
				'edit_item'          => __( 'Edit Command', 'pmc-soaps' ),
				'new_item'           => __( 'New Command', 'pmc-soaps' ),
				'view_item'          => __( 'View Command', 'pmc-soaps' ),
				'search_items'       => __( 'Search Commands', 'pmc-soaps' ),
				'not_found'          => __( 'No Commands found', 'pmc-soaps' ),
				'not_found_in_trash' => __( 'No Commands found in Trash', 'pmc-soaps' ),
				'menu_name'          => __( 'Commands', 'pmc-soaps' ),
			),
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => false,
			'publicly_queryable'  => true,
			'has_archive'         => true,
			'supports'            => array( 'title', 'editor', 'author', ),
			'taxonomies'          => array( 'shows' ),
			'menu_icon'           => 'dashicons-admin-generic',
		);

		register_post_type( $this->post_type, $args );
	}

}