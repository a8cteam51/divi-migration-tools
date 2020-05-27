<?php
/**
 * To migrates the Divi shortcodes to gutenberg blocks.
 *
 * @since             1.0.0
 * @package           divi-migration-tools
 */

WP_CLI::add_command( 'divi-cli', 'Divi_Shortcode_Migration' );

class Divi_Shortcode_Migration extends WP_CLI_Command {

	private $dry_run   = true;
	private $post_type = 'post';

	private $migratable_shortcodes = array( 'et_pb_video', 'et_pb_button', 'et_pb_image', 'et_pb_fullwidth_image' );
	private $clearable_shortcodes  = array( 'et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_text', 'et_pb_fullwidth_header', 'et_pb_code', 'et_pb_cta', 'et_pb_row_inner', 'et_pb_column_inner', 'et_pb_sidebar', 'et_pb_slider', 'et_pb_slide', 'et_pb_post_title', 'et_pb_line_break_holder', 'et_pb_divider', 'et_pb_toggle', 'et_pb_fullwidth_code' );
	private $skippable_shortcodes  = array( 'et_social_follow', 'embed', 'caption', 'toc', 'Sarcastic', 'gallery', 'Tweet', 'Proof', 'et_pb_social_media_follow', 'et_pb_social_media_follow_network', 'et_pb_testimonial', 'et_pb_contact_form', 'et_pb_contact_field', 'et_pb_blog', 'et_pb_pricing_tables', 'et_pb_blurb', 'et_pb_video_slider', 'et_pb_video_slider_item', 'et_pb_team_member', 'et_pb_tabs', 'et_pb_tab' );

	/**
	 * To reset Divi post Content.
	 *
	 * ## EXAMPLES
	 *
	 *   wp divi-cli reset-post-content
	 *
	 * @subcommand reset-post-content
	 *
	 * @param array $args Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 *
	 * @throws \Exception If any errors.
	 */
	public function reset_divi_post_content( $args, $assoc_args ) {

		// Starting time of the script.
		$start_time = time();

		// To enable WP_IMPORTING.
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		if ( ! empty( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {

			$this->dry_run = false;
		}

		if ( ! empty( $assoc_args['post-type'] ) && in_array( $assoc_args['post-type'], array( 'page', 'project' ), true ) ) {

			$this->post_type = $assoc_args['post-type'];
		}

		if ( $this->dry_run ) {

			$this->write_log( '' );
			$this->warning( 'You have called the command divi-cli:reset-post-content in dry run mode.' . "\n" );
		}

		$limit = -1;

		if ( ! empty( $assoc_args['limit'] ) ) {
			$limit = intval( $assoc_args['limit'] );
		}

		$post_status = 'publish';

		if ( ! empty( $assoc_args['status'] ) && 'draft' === $assoc_args['status'] ) {
			$post_status = 'draft';
		}

		$this->write_log( '' );
		$this->write_log( sprintf( 'Migrating the shortcodes from %s post type.', $this->post_type ) . "\n" );

		$args = array(
			'numberposts' => $limit,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'post_type'   => $this->post_type,
			'post_status' => $post_status,
		);

		$posts = get_posts( $args ); // @codingStandardsIgnoreLine: No need to maintain the caching here, so get_posts is okay to use.

		$total_found   = count( $posts );
		$success_count = 0;
		$fail_count    = 0;

		$this->write_log( sprintf( 'Found %d posts to be pass through migration', $total_found ) . "\n" );

		foreach ( $posts as $post ) {
			$post = (array) $post;

			if ( ! $this->dry_run ) {

				$new_post = array(
					'ID'                => $post['ID'],
					'post_content'      => get_post_meta( $post['ID'], '_divi_post_content', true ),
					'post_modified'     => $post['post_modified'],
					'post_modified_gmt' => $post['post_modified_gmt'],
				);

				$result = wp_update_post( $new_post, true );
				if ( is_wp_error( $result ) ) {
					$fail_count++;
				} else {
					$success_count++;
				}
			}
		}

		if ( $this->dry_run ) {

			$this->success( sprintf( 'Total %d posts will be processed.', $total_found ) );
			$this->warning( sprintf( 'Total %d posts will be failed to process.', $fail_count ) );

		} else {

			$this->success( sprintf( 'Total %d posts have been processed.', $success_count ) );
			$this->warning( sprintf( 'Total %d posts have been failed to process.', $fail_count ) );
		}

		WP_CLI::line( '' );
		WP_CLI::success( sprintf( 'Total time taken by this script: %s', human_time_diff( $start_time, time() ) ) . PHP_EOL . PHP_EOL );
	}

	/**
	 * To convert the Divi shortcodes in post content.
	 *
	 * ## EXAMPLES
	 *
	 *   wp divi-cli migrate-shortcodes
	 *
	 * @subcommand migrate-shortcodes
	 *
	 * @param array $args Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 *
	 * @throws \Exception If any errors.
	 */
	public function sop_divi_migrate_shortcodes( $args, $assoc_args ) {

		// Starting time of the script.
		$start_time = time();

		// To enable WP_IMPORTING.
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		if ( ! empty( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {

			$this->dry_run = false;
		}

		if ( ! empty( $assoc_args['post-type'] ) && in_array( $assoc_args['post-type'], array( 'page', 'project' ), true ) ) {

			$this->post_type = $assoc_args['post-type'];
		}

		if ( $this->dry_run ) {

			$this->write_log( '' );
			$this->warning( 'You have called the command sop-divi:migrate-shortcodes in dry run mode.' . "\n" );
		}

		$limit = -1;

		if ( ! empty( $assoc_args['limit'] ) ) {
			$limit = intval( $assoc_args['limit'] );
		}

		$post_status = 'publish';

		if ( ! empty( $assoc_args['status'] ) && 'draft' === $assoc_args['status'] ) {
			$post_status = 'draft';
		}

		$this->write_log( '' );
		$this->write_log( sprintf( 'Migrating the shortcodes from %s post type.', $this->post_type ) . "\n" );

		$args = array(
			'numberposts' => $limit,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'post_type'   => $this->post_type,
			'post_status' => $post_status,
		);

		$posts = get_posts( $args ); // @codingStandardsIgnoreLine: No need to maintain the caching here, so get_posts is okay to use.

		$total_found   = count( $posts );
		$success_count = 0;
		$fail_count    = 0;
		$detail_log    = array(
			array( 'post_id', 'shortcode_name', 'full_shortcode', 'status' ),
		);

		$this->write_log( sprintf( 'Found %d posts to be pass through migration', $total_found ) . "\n" );

		foreach ( $posts as $post ) {
			$post           = (array) $post;
			$migrate_status = $this->sop_divi_migrate_single_post_shortcode( $post, $detail_log );

			if ( $migrate_status ) {
				$success_count++;
			} else {
				$fail_count++;
			}
		}

		WP_CLI::line( '' );
		$this->create_log_file( sprintf( 'divi-shprtcode-logs-%s-%s.csv', $post_status, $this->post_type ), $detail_log );
		WP_CLI::line( '' );

		if ( $this->dry_run ) {

			$this->success( sprintf( 'Total %d posts will be processed.', $total_found ) );
			$this->warning( sprintf( 'Total %d posts will be failed to process.', $fail_count ) );

		} else {

			$this->success( sprintf( 'Total %d posts have been processed.', $success_count ) );
			$this->warning( sprintf( 'Total %d posts have been failed to process.', $fail_count ) );
		}

		WP_CLI::line( '' );
		WP_CLI::success( sprintf( 'Total time taken by this script: %s', human_time_diff( $start_time, time() ) ) . PHP_EOL . PHP_EOL );
	}

	/**
	 * To migrate the divi shortcodes for single post.
	 *
	 * @param array $post Post object.
	 * @param array $logs Detailed logs.
	 *
	 * @return bool
	 */
	private function sop_divi_migrate_single_post_shortcode( $post, &$logs ) {
		$post_content = $post['post_content'];

		$old_content = get_post_meta( $post['ID'], '_divi_post_content', true );
		if ( ! empty( $old_content ) ) {
			$post_content = $old_content;
		} else {
			update_post_meta( $post['ID'], '_divi_post_content', $post_content );
		}

		$regex = '/\[([a-zA-Z0-9_-]+) ?([^\]]+)?/';

		preg_match_all( $regex, $post_content, $matches, PREG_SET_ORDER );

		$matches = array_values( array_filter( $matches ) );

		foreach ( $matches as $match ) {
			$shortcode_name = $match[1];
			$shortcode      = $match[0] . '][/' . $shortcode_name . ']';
			$status         = 'failed';
			$result         = false;

			if ( in_array( $shortcode_name, $this->skippable_shortcodes, true ) ) {
				$status = 'skipped';
			} elseif ( in_array( $shortcode_name, $this->clearable_shortcodes, true ) ) {
				// Clear the shortcode.
				$status       = 'cleared';
				$post_content = str_replace( $match[0] . ']', '', $post_content );
				$post_content = str_replace( '[/' . $shortcode_name . ']', '', $post_content );
			} elseif ( in_array( $shortcode_name, $this->migratable_shortcodes, true ) ) {

				// Migrate the shortcodes.
				$attributes = shortcode_parse_atts( $match[0] );

				if ( 'et_pb_video' === $shortcode_name ) {
					// Youtube embeds.
					$src = $attributes['src'];
					if ( ! empty( $src ) && false !== strpos( $src, 'youtube.com' ) ) {
						$gb_youtube_block  = sprintf( '<!-- wp:core-embed/youtube {"url":"%s","type":"video","providerNameSlug":"youtube","className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->', $src );
						$gb_youtube_block .= PHP_EOL;
						$gb_youtube_block .= '<figure class="wp-block-embed-youtube wp-block-embed is-type-video is-provider-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">';
						$gb_youtube_block .= PHP_EOL;
						$gb_youtube_block .= $src;
						$gb_youtube_block .= PHP_EOL;
						$gb_youtube_block .= '</div></figure>';
						$gb_youtube_block .= PHP_EOL;
						$gb_youtube_block .= '<!-- /wp:core-embed/youtube -->';

						if ( false === strpos( $post_content, $shortcode ) ) {
							$shortcode = $match[0] . ']';
						}
						$post_content = str_replace( $shortcode, $gb_youtube_block, $post_content );
					} elseif ( empty( $src ) && ! empty( $attributes['src_webm'] ) ) {
						$src   = $attributes['src_webm'];
						$thumb = ( empty( $attributes['image_src'] ) ) ? '' : 'poster="' . $attributes['image_src'] . '"';

						$att_id = attachment_url_to_postid( $src );

						if ( ! empty( $att_id ) ) {
							$gb_video_block = sprintf( '<!-- wp:video {"id":%s} -->', $att_id );
						} else {
							$gb_video_block = '<!-- wp:video -->';
						}

						$gb_video_block .= PHP_EOL;
						$gb_video_block .= '<figure class="wp-block-video">';
						$gb_video_block .= PHP_EOL;
						$gb_video_block .= sprintf( '<video controls src="%s" %s></video>', $src, $thumb );
						$gb_video_block .= PHP_EOL;
						$gb_video_block .= '</figure>';
						$gb_video_block .= PHP_EOL;
						$gb_video_block .= '<!-- /wp:video -->';

						if ( false === strpos( $post_content, $shortcode ) ) {
							$shortcode = $match[0] . ']';
						}
						$post_content = str_replace( $shortcode, $gb_video_block, $post_content );
					}
					$status = 'migrated';
				} elseif ( 'et_pb_button' === $shortcode_name ) {

					$target_url = '';
					if ( ! empty( $attributes['url_new_window'] ) && 'on' === empty( $attributes['url_new_window'] ) ) {
						$target_url = 'target="_blank"';
					}

					/**
					 * @todo: @devik check if we can implement disable based on view point. i.e. for mobile,tablet,desktop
					 * Divi example : ["disabled_on"]=> "off|off|off" [M|T|D]
					 */
					if ( ! empty( $attributes['button_text_color'] ) && ! empty( $attributes['button_bg_color'] ) ) {
						$gb_button_block  = sprintf( '<!-- wp:button {"customBackgroundColor":"%s","customTextColor":"%s"} -->', $attributes['button_bg_color'], $attributes['button_text_color'] );
						$gb_button_block .= PHP_EOL;
						$gb_button_block .= sprintf( '<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background" href="%s" %s style="background-color:%s;color:%s">%s</a></div>', $attributes['button_url'], $target_url, $attributes['button_bg_color'], $attributes['button_text_color'], $attributes['button_text'] );
					} elseif ( ! empty( $attributes['button_text_color'] ) ) {
						$gb_button_block  = sprintf( '<!-- wp:button {"customTextColor":"%s"} -->', $attributes['button_text_color'] );
						$gb_button_block .= PHP_EOL;
						$gb_button_block .= sprintf( '<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background" href="%s" %s style="color:%s">%s</a></div>', $attributes['button_url'], $target_url, $attributes['button_text_color'], $attributes['button_text'] );
					} elseif ( ! empty( $attributes['button_bg_color'] ) ) {
						$gb_button_block  = sprintf( '<!-- wp:button {"customBackgroundColor":"%s"} -->', $attributes['button_bg_color'] );
						$gb_button_block .= PHP_EOL;
						$gb_button_block .= sprintf( '<div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background" href="%s" %s style="background-color:%s;">%s</a></div>', $attributes['button_url'], $target_url, $attributes['button_bg_color'], $attributes['button_text'] );
					} else {
						$gb_button_block  = '<!-- wp:button -->';
						$gb_button_block .= PHP_EOL;
						$gb_button_block .= sprintf( '<div class="wp-block-button"><a class="wp-block-button__link " href="%s" %s>%s</a></div>', $attributes['button_url'], $target_url, $attributes['button_text'] );
					}
					$gb_button_block .= PHP_EOL;
					$gb_button_block .= '<!-- /wp:button -->';

					if ( false === strpos( $post_content, $shortcode ) ) {
						$shortcode = $match[0] . ']';
					}
					$post_content = str_replace( $shortcode, $gb_button_block, $post_content );
					$status       = 'migrated';
				} elseif ( 'et_pb_image' === $shortcode_name || 'et_pb_fullwidth_image' === $shortcode_name ) {

					$att_id = attachment_url_to_postid( $attributes['src'] );

					/**
					 * Divi attributes: Skipped because of less support in Core gutenberg block.
					 * force_fullwidth="on" positioning="absolute"
					 * disabled_on="off|off|on"
					 * module_id="img-test-ID" module_class="img-test-class"
					 * border_color_all="#000000" border_width_all="3px"
					 * border_color_right="#000000" border_width_right="9px"
					 * custom_css_main_element="background:red;" custom_css_before="background:green;" custom_css_after="background:blue;"
					 */

					if ( $att_id ) {
						$gb_attr   = sprintf( '"id":%s,"sizeSlug":"medium",', $att_id );
						$style_str = '';

						if ( empty( $attributes['align'] ) ) {
							$attributes['align'] = 'left';
						}
						if ( empty( $attributes['alt'] ) ) {
							$attributes['alt'] = '';
						}
						$gb_attr .= sprintf( '"align":"%s",', $attributes['align'] );
						if ( ! empty( $attributes['max_width'] ) ) {
							$gb_attr   .= sprintf( '"width":"%s",', $attributes['max_width'] );
							$style_str .= sprintf( 'max-width:%s;', $attributes['max_width'] );
						}
						if ( ! empty( $attributes['max_height'] ) ) {
							$gb_attr   .= sprintf( '"height":"%s",', $attributes['max_height'] );
							$style_str .= sprintf( 'max-height:%s;', $attributes['max_height'] );
						}
						$gb_img_block  = sprintf( '<!-- wp:image {%s} -->', trim( $gb_attr, ',' ) );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= sprintf( '<div class="wp-block-image"><figure class="align%s size-medium"><img src="%s" alt="%s" class="wp-image-%s"/></figure></div>', $attributes['align'], $attributes['src'], $attributes['alt'], $att_id );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= '<!-- /wp:image -->';
						$status        = 'migrated';

						if ( false === strpos( $post_content, $shortcode ) ) {
							$shortcode = $match[0] . ']';
						}
						$post_content = str_replace( $shortcode, $gb_img_block, $post_content );

					} else {
						$gb_attr = sprintf( 'sizeSlug":"medium",', $att_id );

						if ( empty( $attributes['align'] ) ) {
							$attributes['align'] = 'left';
						}
						$gb_attr .= sprintf( '"align":"%s",', $attributes['align'] );

						$gb_img_block  = sprintf( '<!-- wp:html {%s} -->', trim( $gb_attr, ',' ) );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= sprintf( '<div class="wp-block-image"><figure class="align%s size-medium"><img src="%s" alt="%s"/></figure></div>', $attributes['align'], $attributes['src'], $attributes['alt'] );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= '<!-- /wp:html -->';
						$status        = 'migrated';

						if ( false === strpos( $post_content, $shortcode ) ) {
							$shortcode = $match[0] . ']';
						}
						$post_content = str_replace( $shortcode, $gb_img_block, $post_content );
					}
				}
			}
			$post_content = str_replace( '<!-- wp:divi/placeholder -->', '', $post_content );
			$post_content = str_replace( '<!-- /wp:divi/placeholder -->', '', $post_content );

			if ( $this->dry_run ) {
				$status = 'to be ' . $status;
			}

			if ( 'to be cleared' !== $status ) {
				$logs[] = array( $post['ID'], $shortcode_name, $shortcode, $status );
			}
		}

		if ( ! $this->dry_run ) {

			$new_post = array(
				'ID'                => $post['ID'],
				'post_content'      => $post_content,
				'post_modified'     => $post['post_modified'],
				'post_modified_gmt' => $post['post_modified_gmt'],
			);

			$result = wp_update_post( $new_post, true );
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Hook callback to alter the post modification time.
	 * This needs to be added to update the post_modified time while inserting or updating the post.
	 *
	 * @param array $data    Data.
	 * @param array $postarr Post array.
	 *
	 * @return mixed
	 */
	private function alter_post_modification_time( $data, $postarr ) {

		if ( ! empty( $postarr['post_modified'] ) && ! empty( $postarr['post_modified_gmt'] ) ) {
			$data['post_modified']     = $postarr['post_modified'];
			$data['post_modified_gmt'] = $postarr['post_modified_gmt'];
		}

		return $data;
	}

	/**
	 * Create log files.
	 *
	 * @param string $file_name File name.
	 * @param array  $logs      Log array.
	 */
	private function create_log_file( $file_name, $logs ) {

		$uploads     = wp_get_upload_dir();
		$source_file = $uploads['basedir'] . '/divi-migration-logs/';

		if ( ! file_exists( $source_file ) ) {
			mkdir( $source_file, 0777, true );
		}

		$file = fopen( $source_file . $file_name, 'w' ); // @codingStandardsIgnoreLine

		foreach ( $logs as $row ) {
			fputcsv( $file, $row );
		}

		$csv_generated = fclose( $file ); // @codingStandardsIgnoreLine

		if ( $csv_generated ) {
			$this->write_log( sprintf( 'Log created successfully - %s', $file_name ) );
		} else {
			$this->warning( sprintf( 'Failed to write the logs - %s', $file_name ) );
		}
	}

	/**
	 * Method to add a log entry and to output message on screen
	 *
	 * @param string $msg             Message to add to log and to outout on screen.
	 * @param int    $msg_type        Message type - 0 for normal line, -1 for error, 1 for success, 2 for warning.
	 * @param bool   $suppress_stdout If set to TRUE then message would not be shown on screen.
	 * @return void
	 */
	protected function write_log( $msg, $msg_type = 0, $suppress_stdout = false ) {

		// backward compatibility.
		if ( true === $msg_type ) {
			// its an error
			$msg_type = -1;
		} elseif ( true === $msg_type ) {
			// normal message
			$msg_type = 0;
		}

		$msg_type = intval( $msg_type );

		$msg_prefix = '';

		// Message prefix for use in log file
		switch ( $msg_type ) {

			case -1:
				$msg_prefix = 'Error: ';
				break;

			case 1:
				$msg_prefix = 'Success: ';
				break;

			case 2:
				$msg_prefix = 'Warning: ';
				break;

		}

		// If we don't want output shown on screen then
		// bail out.
		if ( true === $suppress_stdout ) {
			return;
		}

		switch ( $msg_type ) {

			case -1:
				WP_CLI::error( $msg );
				break;

			case 1:
				WP_CLI::success( $msg );
				break;

			case 2:
				WP_CLI::warning( $msg );
				break;

			case 0:
			default:
				WP_CLI::line( $msg );
				break;

		}

	}

	/**
	 * Method to log an error message and stop the script from running further
	 *
	 * @param string $msg Message to add to log and to outout on screen
	 * @return void
	 */
	protected function error( $msg ) {
		$this->write_log( $msg, -1 );
	}

	/**
	 * Method to log a success message
	 *
	 * @param string $msg Message to add to log and to outout on screen
	 * @return void
	 */
	protected function success( $msg ) {
		$this->write_log( $msg, 1 );
	}

	/**
	 * Method to log a warning message
	 *
	 * @param string $msg Message to add to log and to outout on screen
	 * @return void
	 */
	protected function warning( $msg ) {
		$this->write_log( $msg, 2 );
	}
}
