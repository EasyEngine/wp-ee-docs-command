<?php
/**
 * File to register and load custom shortcodes.
 *
 * @package ee-markdown-importer
 */

namespace WPOrg_Cli;

/**
 * Class to load short codes.
 */
class Shortcodes {

	/**
	 * Variable use to store auth token.
	 *
	 * @var string.
	 */
	private static $auth_token;

	/**
	 * Register custom shortcodes.
	 */
	public static function action_init() {
		add_shortcode( 'cli-issue-list', [ __CLASS__, 'issue_list' ] );
		add_shortcode( 'cli-repo-list', [ __CLASS__, 'repo_list' ] );
	}

	/**
	 * List all issues with a specific label
	 *
	 * @param array $atts Attributes of shortcode.
	 *
	 * @return mixed
	 */
	public static function issue_list( $atts ) {

		if ( isset( $atts['auth_token'] ) ) {
			self::$auth_token = $atts['auth_token'];
		}

		$filter_label = isset( $atts['label'] ) ? $atts['label'] : '';
		$out          = sprintf( '<h2>Issues labeled "%s" </h2>', esc_html( $filter_label ) );
		$url          = 'https://api.github.com/orgs/wp-cli/issues';
		$url          = add_query_arg(
			array_map(
				'rawurlencode',
				[
					'per_page' => 100,
					'labels'   => $filter_label,
					'filter'   => 'all',
				]
			),
			$url
		);
		$issues       = self::github_request( $url );

		if ( is_wp_error( $issues ) ) {
			$out .= sprintf( '<p>%s</p>', esc_html( $issues->get_error_message() ) ) . PHP_EOL;
			return $out;
		}

		if ( empty( $issues ) ) {
			$out .= sprintf( '<p>%s</p>', esc_html__( 'No issues found.', 'ee-markdown-importer' ) ) . PHP_EOL;
			return $out;
		}

		$repository_issues = [
			// Root repository should always be first.
			'wp-cli/wp-cli' => [],
		];

		foreach ( $issues as $issue ) {
			$repo_name = $issue->repository->full_name;
			if ( ! isset( $repository_issues[ $repo_name ] ) ) {
				$repository_issues[ $repo_name ] = [];
			}
			$repository_issues[ $repo_name ][] = $issue;
		}

		foreach ( $repository_issues as $repo_name => $issues ) {

			if ( empty( $issues ) ) {
				continue;
			}
			$out .= sprintf( '<h4>%s</h4><ul>', esc_html( $repo_name ) );

			foreach ( $issues as $issue ) {
				$out .= sprintf( '<li><a href="%s">%s</a><br />', esc_url( $issue->html_url ), esc_html( $issue->title ) ) . PHP_EOL;

				if ( ! empty( $issue->labels ) ) {

					foreach ( $issue->labels as $label ) {

						if ( $label->name === $filter_label ) {
							continue;
						}

						$text_color       = '#FFF';
						$background_color = $label->color;
						$c_r              = hexdec( substr( $background_color, 0, 2 ) );
						$c_g              = hexdec( substr( $background_color, 2, 2 ) );
						$c_b              = hexdec( substr( $background_color, 4, 2 ) );

						// Light background means dark color.
						if ( ( ( ( $c_r * 299 ) + ( $c_g * 587 ) + ( $c_b * 114 ) ) / 1000 ) > 135 ) {
							$text_color = '#000';
						}

						$style = sprintf( 'display:inline-block;padding-left:3px;padding-right:3px;color:%s;background-color:#%s', $text_color, $background_color );

						$out .= sprintf( '<span class="label" style="%s">%s</span> ', esc_attr( $style ), esc_html( $label->name ) );

					}
					$out .= '<br />';
				}
				$out .= '</li>';
			}
			$out .= '</ul>';
		}

		return $out;
	}
	/**
	 * Renders WP-CLI repositories in a table format.
	 *
	 * @param array $atts Attributes of shortcode.
	 *
	 * @return mixed
	 */
	public static function repo_list( $atts ) {

		if ( isset( $atts['auth_token'] ) ) {
			self::$auth_token = $atts['auth_token'];
		}

		$out   = '<h2>Repositories</h2>';
		$repos = self::github_request( 'https://api.github.com/orgs/wp-cli/repos?per_page=100' );

		if ( is_wp_error( $repos ) ) {
			$out .= sprintf( '<p>%s</p>', esc_html( $repos->get_error_message() ) );
			return $out;
		}

		$repo_list = [];

		foreach ( $repos as $repo ) {
			if ( ! preg_match( '#^wp-cli/.+-command$#', $repo->full_name ) ) {
				continue;
			}
			$repo_list[] = $repo->full_name;
		}

		sort( $repo_list );
		array_unshift( $repo_list, 'wp-cli/wp-cli' );

		$out .= '<table>' . PHP_EOL;
		$out .= '<thead>' . PHP_EOL;
		$out .= '<tr>' . PHP_EOL;
		$out .= '<th>Repository</th>' . PHP_EOL;
		$out .= '<th>Overview</th>' . PHP_EOL;
		$out .= '<th>Status</th>' . PHP_EOL;
		$out .= '</tr>' . PHP_EOL;
		$out .= '</thead>' . PHP_EOL;

		foreach ( $repo_list as $repo_name ) {
			$out .= '<tr>' . PHP_EOL;
			// Name.
			$out .= '<td><a href="' . esc_url( sprintf( 'https://github.com/%s', $repo_name ) ) . '">' . esc_html( $repo_name ) . '</td>' . PHP_EOL;
			// Overview.
			$out .= '<td><ul>' . PHP_EOL;
			// Overview: Active milestone.
			$url              = sprintf( 'https://api.github.com/repos/%s/milestones', $repo_name );
			$milestones       = self::github_request( $url );
			$latest_milestone = '<em>None</em>';

			if ( is_wp_error( $milestones ) ) {
				$latest_milestone = $milestones->get_error_message();
			} elseif ( ! empty( $milestones ) ) {
				$milestones       = array_shift( $milestones );
				$latest_milestone = '<a href="' . esc_url( $milestones->html_url ) . '">v' . esc_html( $milestones->title ) . '</a> (' . (int) $milestones->open_issues . ' open, ' . (int) $milestones->closed_issues . ' closed)';
			}

			$out .= '<li>Active: ' . wp_kses_post( $latest_milestone ) . '</li>';

			// Overview: Latest release.
			$url            = sprintf( 'https://api.github.com/repos/%s/releases', $repo_name );
			$releases       = self::github_request( $url );
			$latest_release = '<em>None</em>';

			if ( is_wp_error( $releases ) ) {
				$latest_release = $releases->get_error_message();
			} elseif ( ! empty( $releases ) ) {
				$releases       = array_shift( $releases );
				$latest_release = sprintf( '<a href="%s">%s</a>', esc_url( $releases->html_url ), esc_html( $releases->tag_name ) );
			}

			$out .= sprintf( '<li>Latest: %s</li>', wp_kses_post( $latest_release ) );
			$out .= '</ul></td>' . PHP_EOL;

			// Status.
			// Command dist-archive primarily uses Circle.
			if ( 'wp-cli/dist-archive-command' === $repo_name ) {
				$status_image = sprintf( 'https://circleci.com/gh/%s/tree/master.svg?style=svg', $repo_name );
				$status_link  = sprintf( 'https://circleci.com/gh/%s/tree/master', $repo_name );
			} else {
				$status_image = sprintf( 'https://travis-ci.org/%s.svg?branch=master', $repo_name );
				$status_link  = sprintf( 'https://travis-ci.org/%s/branches', $repo_name );
			}

			$out .= sprintf( '<td><a href="%s"><img src="%s"></a></td>', esc_url( $status_link ), esc_url( $status_image ) ) . PHP_EOL;
			$out .= '</tr>' . PHP_EOL;
		}

		$out .= '</table>';
		return $out;
	}

	/**
	 * Make an API request to GitHub
	 *
	 * @param string $url Github URL.
	 *
	 * @return mixed
	 */
	private static function github_request( $url ) {

		$cache_key = 'cli_github_' . md5( $url );

		$cache_value = get_transient( $cache_key );

		if ( false !== $cache_value ) {
			return $cache_value;
		}

		$request = [
			'headers' => [
				'Accept'     => 'application/vnd.github.v3+json',
				'User-Agent' => 'WordPress.org / WP-CLI',
			],
		];

		if ( isset( self::$auth_token ) ) {
			$request['headers']['Authorization'] = 'token ' . self::$auth_token;
		}

		$response = wp_remote_get( $url, $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return new \WP_Error( 'github_error', sprintf( 'GitHub API error (HTTP code %d )', $response_code ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		set_transient( $cache_key, $data, '', 3 * MINUTE_IN_SECONDS );

		return $data;
	}
}
