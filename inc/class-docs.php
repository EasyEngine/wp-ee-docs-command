<?php
/**
 * File to add docs related WP_CLI commands.
 *
 * @package ee-markdown-importer
 */

namespace WPOrg_Cli;

/**
 * Class to load Docs related WP_CLI command.
 */
class Docs {

	/**
	 * Contain output directory path.
	 *
	 * @var string
	 */
	public $output_dir = EE_DOC_OUTPUT_DIR;

	// phpcs:disable Squiz.Commenting.FunctionComment.MissingParamTag
	/**
	 * Generate markdowns for all commands, generate manifest for commands markdown and generate manifest for handbook.
	 *
	 * [<output-dir>]
	 * : Output docs directory
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp ee gen-docs
	 *
	 * @subcommand gen-docs
	 */
	public function gen_all( $args ) {

		if ( ! empty( $args[0] ) ) {
			$this->output_dir = $args[0];
		}

		if ( ! is_dir( $this->output_dir ) ) {
			mkdir( $this->output_dir );
		}

		$tmp_file_location = download_url( EE_DOWNLOAD_PHAR_URL );

		rename( $tmp_file_location, EE_PHAR_FILE );

		if ( ! file_exists( EE_PHAR_FILE ) ) {
			return new \WP_Error( 'ee-phar-not-found', 'EasyEngine v4 phar file not found at location: ' . EE_PHAR_FILE . '. Please add it and try it again' );
		}

		$this->gen_commands();
		$this->gen_commands_manifest();

		$ee_root_dir = rtrim( getenv( 'HOME' ), '/\\' ) . '/easyengine';

		if ( is_dir( $ee_root_dir ) ) {
			shell_exec( 'rm -r ' . $ee_root_dir );
		}

		\WP_CLI::success( 'Generated all doc pages.' );
	}
	// phpcs:enable

	/**
	 * Generate markdown file for each ee command.
	 *
	 * @return void
	 */
	private function gen_commands() {

		$subcommands = self::get_subcommands();

		foreach ( $subcommands as $cmd ) {

			if ( 'handbook' === $cmd['name'] ) {
				continue;
			}

			self::gen_cmd_pages( $cmd );

		}

		\WP_CLI::success( 'Generated all command pages.' );
	}

	/**
	 * Function to get sub command.
	 *
	 * @return array
	 */
	public static function get_subcommands() {

		$ee           = shell_exec( 'php ' . EE_PHAR_FILE . ' site cmd-dump' );
		$bundled_cmds = [];
		$ee           = json_decode( $ee, true );

		foreach ( $ee['subcommands'] as $k => $cmd ) {

			if ( in_array( $cmd['name'], [ 'website', 'api-dump' ], true ) ) {
				unset( $ee['subcommands'][ $k ] );
				continue;
			}

			$bundled_cmds[] = $cmd['name'];
		}

		$ee['subcommands'] = array_values( $ee['subcommands'] );

		return $ee['subcommands'];
	}

	/**
	 * Update the commands data array with new data
	 *
	 * @param object $command       Command object.
	 * @param array  $commands_data Contain command data.
	 * @param string $parent        Parent command string.
	 *
	 * @return void
	 */
	private static function update_commands_data( $command, &$commands_data, $parent ) {

		$full       = trim( $parent . ' ' . $command->get_name() );
		$reflection = new \ReflectionClass( $command );
		$repo_url   = '';

		if ( 'help' === substr( $full, 0, 4 ) || 'cli' === substr( $full, 0, 3 ) ) {
			$repo_url = 'https://github.com/EasyEngine/handbook';
		}

		if ( $reflection->hasProperty( 'when_invoked' ) ) {
			$when_invoked = $reflection->getProperty( 'when_invoked' );
			$when_invoked->setAccessible( true );
			$closure            = $when_invoked->getValue( $command );
			$closure_reflection = new \ReflectionFunction( $closure );
			$static             = $closure_reflection->getStaticVariables();

			if ( isset( $static['callable'][0] ) ) {

				$reflection_class = new \ReflectionClass( $static['callable'][0] );
				$filename         = $reflection_class->getFileName();
				preg_match( '#vendor/([^/]+/[^/]+)#', $filename, $matches );

				if ( ! empty( $matches[1] ) ) {
					$repo_url = 'https://github.com/' . $matches[1];
				}
			} else {
				\WP_CLI::error( 'No callable for: ' . var_export( $static, true ) );
			}
		}

		$commands_data[ $full ] = [
			'repo_url' => $repo_url,
		];

		$len = count( $commands_data );

		foreach ( $command->get_subcommands() as $subcommand ) {
			$sub_full = trim( $full . ' ' . $subcommand->get_name() );
			self::update_commands_data( $subcommand, $commands_data, $full );
		}

		if ( isset( $sub_full ) && ! $commands_data[ $full ]['repo_url'] && ! empty( $commands_data[ $sub_full ]['repo_url'] ) ) {
			$commands_data[ $full ]['repo_url'] = $commands_data[ $sub_full ]['repo_url'];
		}
	}

	/**
	 * Generate `bin/commands-manifest.json` file from markdown in `commands` directory.
	 *
	 * @return void
	 */
	private function gen_commands_manifest() {

		$manifest      = [];
		$paths         = [
			$this->output_dir . '/commands/*.md',
			$this->output_dir . '/commands/*/*.md',
			$this->output_dir . '/commands/*/*/*.md',
		];
		$commands_data = [];

		foreach ( $paths as $path ) {

			foreach ( glob( $path ) as $file ) {
				$slug     = basename( $file, '.md' );
				$cmd_path = str_replace( [ $this->output_dir . '/commands/', '.md' ], '', $file );
				$title    = '';
				$contents = file_get_contents( $file );
				if ( preg_match( '/^#\s(.+)/', $contents, $matches ) ) {
					$title = $matches[1];
				}
				$parent = null;

				if ( stripos( $cmd_path, '/' ) ) {
					$bits = explode( '/', $cmd_path );
					array_pop( $bits );
					$parent = implode( '/', $bits );
				}

				$manifest[ $cmd_path ] = [
					'title'           => $title,
					'slug'            => $slug,
					'cmd_path'        => $cmd_path,
					'parent'          => $parent,
					'markdown_source' => sprintf( '%s/commands/%s.md', $this->output_dir, $cmd_path ),
				];

				if ( ! empty( $commands_data[ $title ] ) ) {
					$manifest[ $cmd_path ] = array_merge( $manifest[ $cmd_path ], $commands_data[ $title ] );
				}
			}
		}

		if ( ! is_dir( $this->output_dir . '/bin' ) ) {
			mkdir( $this->output_dir . '/bin' );
		}

		file_put_contents( $this->output_dir . '/bin/commands-manifest.json', json_encode( $manifest, JSON_PRETTY_PRINT ) );
		$count = count( $manifest );
		\WP_CLI::success( "Generated commands-manifest.json of {$count} commands" );
	}

	/**
	 * Function to generate command pages.
	 *
	 * @param array $cmd         Command details.
	 * @param array $parent      Parent command.
	 * @param bool  $skip_global To skip global variable.
	 * @param bool  $return_str  To return string.
	 *
	 * @return mixed
	 */
	private function gen_cmd_pages( $cmd, $parent = [], $skip_global = false, $return_str = false ) {

		$parent[] = $cmd['name'];
		static $params;

		if ( ! isset( $params ) ) {
			$params = shell_exec( 'php ' . EE_PHAR_FILE . ' cli param-dump' );
			$params = json_decode( $params, true );
		}

		$binding                = $cmd;
		$binding['synopsis']    = implode( ' ', $parent );
		$binding['path']        = implode( '/', $parent );
		$path                   = $this->output_dir . '/commands/';
		$binding['breadcrumbs'] = '[Commands](' . $path . ')';

		foreach ( $parent as $i => $p ) {
			$path .= $p . '/';
			if ( $i < ( count( $parent ) - 1 ) ) {
				$binding['breadcrumbs'] .= " &raquo; [{$p}]({$path})";
			} else {
				$binding['breadcrumbs'] .= " &raquo; {$p}";
			}
		}

		$binding['has-subcommands'] = isset( $cmd['subcommands'] ) ? [ true ] : false;

		if ( $cmd['longdesc'] ) {
			$docs = $cmd['longdesc'];
			$docs = htmlspecialchars( $docs, ENT_COMPAT, 'UTF-8' );

			// Decrease header level.
			$docs = preg_replace( '/^## /m', '### ', $docs );
			// Escape `--` so that it doesn't get converted into `&mdash;`.
			$docs = preg_replace( '/^(\[?)--/m', '\1\--', $docs );
			$docs = preg_replace( '/^\s\s--/m', '  \1\--', $docs );

			// Remove wordwrapping from docs.
			// Match words, '().,;', and --arg before/after the newline.
			$bits         = explode( "\n", $docs );
			$in_yaml_doc  = false;
			$in_code_bloc = false;

			$loop_limit = count( $bits );
			for ( $i = 0; $i < $loop_limit; $i++ ) {
				if ( ! isset( $bits[ $i ] ) || ! isset( $bits[ $i + 1 ] ) ) {
					continue;
				}
				if ( '---' === $bits[ $i ] || '\---' === $bits[ $i ] ) {
					$in_yaml_doc = ! $in_yaml_doc;
				}
				if ( '```' === $bits[ $i ] ) {
					$in_code_bloc = ! $in_code_bloc;
				}
				if ( $in_yaml_doc || $in_code_bloc ) {
					continue;
				}
				if ( preg_match( '#([\w\(\)\.\,\;]|[`]{1})$#', $bits[ $i ] ) &&
					preg_match( '#^([\w\(\)\.\,\;`]|\\\--[\w]|[`]{1})#', $bits[ $i + 1 ] )
				) {
					$bits[ $i ] .= ' ' . $bits[ $i + 1 ];
					unset( $bits[ $i + 1 ] );
					--$i;
					$bits = array_values( $bits );
				}
			}

			$docs = implode( "\n", $bits );

			// Hack to prevent double encoding in code blocks.
			$docs              = preg_replace( '/ &lt; /', ' < ', $docs );
			$docs              = preg_replace( '/ &gt; /', ' > ', $docs );
			$docs              = preg_replace( '/ &lt;&lt;/', ' <<', $docs );
			$docs              = preg_replace( '/&quot;/', '"', $docs );
			$docs              = preg_replace( '/ee&gt; /', 'ee> ', $docs );
			$docs              = preg_replace( '/=&gt;/', '=>', $docs );
			$global_parameters = <<<EOT
| **Argument**    | **Description**              |
|:----------------|:-----------------------------|
EOT;
			foreach ( $params as $param => $meta ) {

				if ( false === $meta['runtime'] ||
					empty( $meta['desc'] ) ||
					! empty( $meta['deprecated'] )
				) {
					continue;
				}

				$param_arg = '--' . $param;
				if ( ! empty( $meta['runtime'] ) && true !== $meta['runtime'] ) {
					$param_arg .= $meta['runtime'];
				}
				if ( 'color' === $param ) {
					$param_arg = '--[no-]color';
				}
				$global_parameters .= PHP_EOL . '| `' . str_replace( '|', '\\|', $param_arg ) . '` | ' . str_replace( '|', '\\|', $meta['desc'] ) . ' |';
			}

			if ( $skip_global ) {
				$replace_global = '';
			} else {
				// Replace Global parameters with a nice table.
				if ( $binding['has-subcommands'] ) {
					$replace_global = '';
				} else {
					$replace_global = '$1' . PHP_EOL . PHP_EOL . $global_parameters;
				}
			}
			$docs = preg_replace( '/(#?## GLOBAL PARAMETERS).+/s', $replace_global, $docs );

			$binding['docs'] = $docs;
		}

		$path = $this->output_dir . '/commands/' . $binding['path'];

		if ( ! is_dir( dirname( "$path.md" ) ) ) {
			mkdir( dirname( "$path.md" ), 0777, true );
		}

		$markdown_doc = $this->render( 'subcmd-list.mustache', $binding );

		if ( $return_str ) {
			return $markdown_doc;
		}

		file_put_contents( "$path.md", $markdown_doc );

		\WP_CLI::log( 'Generated ' . $path . '/' );

		if ( ! isset( $cmd['subcommands'] ) ) {
			return;
		}

		foreach ( $cmd['subcommands'] as $key => $subcmd ) {
			if ( strpos( $subcmd['name'], 'publish --type=' ) !== false ) {
				unset( $cmd['subcommands'][ $key ] );
				$subcmd['name']                = 'publish';
				$cmd['subcommands']['publish'] = $subcmd;
			}
		}

		$bundle_command = [];

		foreach ( $cmd['subcommands'] as $subcmd ) {

			if ( strpos( $subcmd['name'], ' --type=' ) !== false ) {
				$command_name                         = explode( ' --type=', $subcmd['name'] );
				$bundle_command[ $command_name[0] ][] = $subcmd;
			} else {
				self::gen_cmd_pages( $subcmd, $parent );
			}
		}

		foreach ( $bundle_command as $name => $command ) {
			$pop    = array_pop( $bundle_command[ $name ] );
			$md_doc = '';
			foreach ( $command as $subcommand ) {
				if ( $pop['name'] === $subcommand['name'] ) {
					$md_doc .= $this->gen_cmd_pages( $subcommand, $parent, false, true );
					$path    = $this->output_dir . '/commands/' . implode( '/', $parent ) . "/$name";
					if ( ! is_dir( dirname( $path ) ) ) {
						mkdir( dirname( $path ) );
					}
					file_put_contents( "$path.md", $md_doc );
				} else {
					$md_doc .= $this->gen_cmd_pages( $subcommand, $parent, true, true );
				}
			}
		}
	}

	/**
	 * Function to render template.
	 *
	 * @param string $path    Path of template.
	 * @param string $binding Binding content with template.
	 *
	 * @return string
	 */
	private function render( $path, $binding ) {
		$m        = new \Mustache_Engine();
		$template = file_get_contents( EE_MARKDOWN_PLUGIN_DIR . "/templates/$path" );

		return $m->render( $template, $binding );
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'ee', '\WPOrg_Cli\Docs' );
}
