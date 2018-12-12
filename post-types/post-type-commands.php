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

		add_filter( 'template_include', array( $this, 'ee_template_include' ) );

		add_action( 'pre_get_posts', array( $this, 'ee_pre_get_posts' ) );

		add_filter( 'the_content', array( $this, 'ee_add_subcommands' ) );
	}

	public static function ee_command_description( $excerpt_content, $post ) {

		if ( ! empty( $excerpt_content ) || ! $post instanceof \WP_Post) {
			return $excerpt_content;
		}

		$content = $post->post_content;
		$content = rtrim( strtok( $content, "\n" ) );

		return $content;
	}

	function ee_add_subcommands( $content ) {

		if ( ! is_singular('commands') ) {
			return $content;
		}

		$post_id        = get_the_ID();
		$parent_page_id = wp_get_post_parent_id( $post_id );

		if ( empty( $parent_page_id ) ) {
			$parent_page_id = $post_id;
		}

		$args = array(
			'post_type'      => $this->post_type,
			'post_parent'    => $parent_page_id,
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$content_parts = explode( '<h3>GLOBAL PARAMETERS</h3>', $content );

		$subcommands = new \WP_Query( $args );

		$subcommands_content = '';

		$subcommands_exists = false;

		if ( $subcommands->have_posts() ) {
			$subcommands_content .= '<h3 id="subcommands">SUBCOMMANDS</h3>';
			$subcommands_content .= '<table class="wp-block-table ee-commands-table">';
			$subcommands_content .= '<thead>';
			$subcommands_content .= '<tr>';
			$subcommands_content .= '<th>Name</th>';
			$subcommands_content .= '<th>Description</th>';
			$subcommands_content .= '</tr>';
			$subcommands_content .= '</thead>';
			$subcommands_content .= '<tbody>';

			while ( $subcommands->have_posts() ) {
				$subcommands->the_post();

				if ( $post_id === get_the_ID() ) {
					continue;
				}

				$subcommands_exists = true;

				$subcommands_content .= '<tr>';
				$subcommands_content .= sprintf( '<td><a href="%s">%s</a></td>',
					get_the_permalink(),
					get_the_title()
				);

				remove_filter( 'the_content', array( $this, 'ee_add_subcommands' ) );
				add_filter( 'get_the_excerpt', array( $this, 'ee_command_description'), 1, 2 );

				$subcommands_content .= sprintf( '<td>%s</td>', get_the_excerpt() );

				remove_filter( 'get_the_excerpt', array( $this, 'ee_command_description') );
				add_filter( 'the_content', array( $this, 'ee_add_subcommands' ) );

				$subcommands_content .= '</tr>';
			}

			wp_reset_postdata();
			$subcommands_content .= '</tbody>';
			$subcommands_content .= '</table>';
		}

		if ( count( $content_parts ) > 1 ) {
			$content           = $content_parts[0];
			$content           .= empty( $subcommands_exists ) ? '' : $subcommands_content;
			$global_parameters = empty( $content_parts[1] ) ? '' : '<h3>GLOBAL PARAMETERS</h3>' . $content_parts[1];
			$global_parameters = str_replace( '<table>', '<table class="wp-block-table ee-global-parameter-table">', $global_parameters );
			$global_parameters = str_replace( 'style="text-align: left"', '', $global_parameters );
			$global_parameters = str_replace( '<code>', '', $global_parameters );
			$content           .= $global_parameters;
		} else {
			$content = $content_parts[0];
			$content .= empty( $subcommands_exists ) ? '' : $subcommands_content;
		}

		return $content;
	}

	function ee_pre_get_posts( $query ) {

		if (
			is_admin() ||
			! $query->is_main_query() ||
			! is_post_type_archive( $this->post_type )
		) {
			return;
		}

		if ( defined( 'REST_REQUEST' ) && constant( 'REST_REQUEST' ) ) {
			return;
		}

		$query->set( 'posts_per_page', 200 );
		$query->set( 'post_parent', 0 );
	}

	/**
	 * Locate custom template for commands.
	 *
	 * @param string $template The path of the template to include.
	 *
	 * @return string The path of the template to include.
	 */
	function ee_template_include( $template ) {

		if ( is_singular( $this->post_type ) ) {
			remove_filter( 'the_excerpt', 'wptexturize' );
			remove_filter( 'the_content', 'wptexturize' );
		}

		if ( ! is_post_type_archive( $this->post_type ) ) {
			return $template;
		}

		return EE_MARKDOWN_PLUGIN_DIR . '/page-templates/archive-commands.php';
	}

	/**
	 * Register post type commands
	 */
	public function ee_register_post_type() {
		$args = array(
			'labels'              => array(
				'name'               => __( 'Commands', 'ee-markdown-importer' ),
				'singular_name'      => __( 'Command', 'ee-markdown-importer' ),
				'all_items'          => __( 'All Commands', 'ee-markdown-importer' ),
				'add_new'            => __( 'Add New', 'ee-markdown-importer' ),
				'add_new_item'       => __( 'Add New Command', 'ee-markdown-importer' ),
				'edit_item'          => __( 'Edit Command', 'ee-markdown-importer' ),
				'new_item'           => __( 'New Command', 'ee-markdown-importer' ),
				'view_item'          => __( 'View Command', 'ee-markdown-importer' ),
				'search_items'       => __( 'Search Commands', 'ee-markdown-importer' ),
				'not_found'          => __( 'No Commands found', 'ee-markdown-importer' ),
				'not_found_in_trash' => __( 'No Commands found in Trash', 'ee-markdown-importer' ),
				'menu_name'          => __( 'Commands', 'ee-markdown-importer' ),
			),
			'rewrite'             => array(
				'slug'       => $this->post_type,
				'with_front' => false
			),
			'show_in_rest'        => true,
			'public'              => true,
			'exclude_from_search' => false,
			'show_in_nav_menus'   => true,
			'publicly_queryable'  => true,
			'has_archive'         => true,
			'supports'            => array( 'title', 'editor', 'excerpt', 'author', 'page-attributes' ),
			'hierarchical'        => true,
			'taxonomies'          => array( 'shows' ),
			'menu_icon'           => 'dashicons-admin-generic',
		);

		register_post_type( $this->post_type, $args );
	}

}