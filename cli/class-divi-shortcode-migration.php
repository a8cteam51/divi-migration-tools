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

	private $migratable_shortcodes = array( 'et_pb_video', 'et_pb_button', 'et_pb_image', 'et_pb_fullwidth_image', 'et_pb_post_title', 'et_pb_divider', 'et_pb_blurb' );
	private $clearable_shortcodes  = array( 'et_pb_section', 'et_pb_row', 'et_pb_column', 'et_pb_text', 'et_pb_fullwidth_header', 'et_pb_code', 'et_pb_cta', 'et_pb_row_inner', 'et_pb_column_inner', 'et_pb_sidebar', 'et_pb_slider', 'et_pb_slide', 'et_pb_line_break_holder', 'et_pb_toggle', 'et_pb_fullwidth_code' );
	private $skippable_shortcodes  = array( 'et_social_follow', 'embed', 'caption', 'toc', 'Sarcastic', 'gallery', 'Tweet', 'Proof', 'et_pb_social_media_follow', 'et_pb_social_media_follow_network', 'et_pb_testimonial', 'et_pb_contact_form', 'et_pb_contact_field', 'et_pb_blog', 'et_pb_pricing_tables', 'et_pb_video_slider', 'et_pb_video_slider_item', 'et_pb_team_member', 'et_pb_tabs', 'et_pb_tab' );
	private $migratable_divi5_blocks = array(
		'divi/video'           => 'et_pb_video',
		'divi/button'          => 'et_pb_button',
		'divi/image'           => 'et_pb_image',
		'divi/fullwidth-image' => 'et_pb_fullwidth_image',
		'divi/post-title'      => 'et_pb_post_title',
		'divi/divider'         => 'et_pb_divider',
		'divi/blurb'           => 'et_pb_blurb',
	);
	private $clearable_divi5_blocks = array( 'divi/placeholder', 'divi/section', 'divi/row', 'divi/column', 'divi/text', 'divi/fullwidth-header', 'divi/code', 'divi/cta', 'divi/row-inner', 'divi/column-inner', 'divi/sidebar', 'divi/slider', 'divi/slide', 'divi/line-break-holder', 'divi/toggle', 'divi/fullwidth-code' );
	private $skippable_divi5_blocks = array( 'divi/social-media-follow', 'divi/social-media-follow-item', 'divi/testimonial', 'divi/contact-form', 'divi/contact-field', 'divi/blog', 'divi/pricing-tables', 'divi/video-slider', 'divi/video-slider-item', 'divi/team-member', 'divi/tabs', 'divi/tab' );

	/**
	 * To reset Divi post Content.
	 *
	 * ## EXAMPLES
	 *
	 *   wp divi-cli reset-divi-content
	 *
	 * @subcommand reset-divi-content
	 *
	 * @param array $args Store all the positional arguments.
	 * @param array $assoc_args Store all the associative arguments.
	 *
	 * @throws \Exception If any errors.
	 */
	public function reset_divi_post_content( $args, $assoc_args ) {

		if ( ! empty( $assoc_args['post-type'] ) && ! in_array( $assoc_args['post-type'], get_post_types( array( 'public' => true ) ), true ) ) {
			$this->error( 'You have called the command divi-cli:migrate-shortcodes with wrong/unsupported post-type.' . "\n" );
		}

		// Starting time of the script.
		$start_time = time();

		// To enable WP_IMPORTING.
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		if ( ! empty( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {

			$this->dry_run = false;
		}

		if ( ! empty( $assoc_args['post-type'] ) ) {

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
					$this->set_divi_builder_editor_mode( $post['ID'], true );
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
	public function divi_migrate_shortcodes( $args, $assoc_args ) {

		if ( ! empty( $assoc_args['post-type'] ) && ! in_array( $assoc_args['post-type'], get_post_types( array( 'public' => true ) ), true ) ) {
			$this->error( 'You have called the command divi-cli:migrate-shortcodes with wrong/unsupported post-type.' . "\n" );
		}

		// Starting time of the script.
		$start_time = time();

		// To enable WP_IMPORTING.
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		if ( ! empty( $assoc_args['dry-run'] ) && 'false' === $assoc_args['dry-run'] ) {

			$this->dry_run = false;
		}

		if ( ! empty( $assoc_args['post-type'] ) ) {

			$this->post_type = $assoc_args['post-type'];
		}

		if ( $this->dry_run ) {

			$this->write_log( '' );
			$this->warning( 'You have called the command divi-cli:migrate-shortcodes in dry run mode.' . "\n" );
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
			$migrate_status = $this->divi_migrate_single_post_shortcode( $post, $detail_log );

			if ( ! is_wp_error( $migrate_status ) ) {
				$success_count++;
			} else {
				$fail_count++;
			}
		}

		WP_CLI::line( '' );
		$this->create_log_file( sprintf( 'divi-shortcode-logs-%s-%s.csv', $post_status, $this->post_type ), $detail_log );
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
	private function divi_migrate_single_post_shortcode( $post, &$logs ) {
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

				if ( 'et_pb_blurb' === $shortcode_name ) {

					$gb_img_block = '';
					if ( ! empty( $attributes['image'] ) ) {
						$att_id = attachment_url_to_postid( $attributes['image'] );

						if ( $att_id ) {
							$gb_attr   = sprintf( '"id":%s,"sizeSlug":"medium",', $att_id );
							$style_str = '';

							if ( empty( $attributes['icon_alignment'] ) ) {
								$attributes['align'] = 'left';
							}
							$gb_attr .= sprintf( '"align":"%s",', $attributes['icon_alignment'] );

							$gb_img_block  = sprintf( '<!-- wp:image {%s} -->', trim( $gb_attr, ',' ) );
							$gb_img_block .= PHP_EOL;
							$gb_img_block .= sprintf( '<figure class="wp-block-image align%s size-medium"><img src="%s" class="wp-image-%s"/></figure>', $attributes['icon_alignment'], $attributes['image'], $att_id );
							$gb_img_block .= PHP_EOL;
							$gb_img_block .= '<!-- /wp:image -->';

						} else {
							$gb_attr = sprintf( 'sizeSlug":"medium",' );

							if ( empty( $attributes['align'] ) ) {
								$attributes['align'] = 'left';
							}
							$gb_attr .= sprintf( '"align":"%s",', $attributes['align'] );

							$gb_img_block  = sprintf( '<!-- wp:image {%s} -->', trim( $gb_attr, ',' ) );
							$gb_img_block .= PHP_EOL;
							$gb_img_block .= sprintf( '<figure class="wp-block-image align%s size-medium"><img src="%s"/></figure>', $attributes['icon_alignment'], $attributes['image'] );
							$gb_img_block .= PHP_EOL;
							$gb_img_block .= '<!-- /wp:image -->';
						}
					}

					$gb_heading_block = '';
					if ( ! empty( $attributes['title'] ) ) {
						// Title block
						$gb_attr   = '';
						$style_str = '';
						$h_classes = '';
						$h_level   = 'h4';
						$h_text    = $attributes['title'];

						if ( ! empty( $attributes['header_level'] ) && in_array( $attributes['header_level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
							$h_level = $attributes['header_level'];

							if ( 'h2' !== $attributes['header_level'] ) {
								$gb_attr = sprintf( '"level":%s,', str_replace( 'h', '', $attributes['header_level'] ) );
							}
						}

						if ( ! empty( $attributes['url'] ) ) {
							$target_url = '';
							if ( ! empty( $attributes['url_new_window'] ) && 'on' === $attributes['url_new_window'] ) {
								$target_url = 'target="_blank"';
							}
							$h_text = sprintf( '<a href="%s" %s>%s</a>', $attributes['url'], $target_url, $h_text );
						}

						$gb_heading_block  = sprintf( '<!-- wp:heading %s -->', $gb_attr );
						$gb_heading_block .= PHP_EOL;
						$gb_heading_block .= sprintf( '<%s>', $h_level, $h_classes, $style_str );
						$gb_heading_block .= PHP_EOL;
						$gb_heading_block .= $h_text;
						$gb_heading_block .= PHP_EOL;
						$gb_heading_block .= sprintf( '</%s>', $h_level );
						$gb_heading_block .= PHP_EOL;
						$gb_heading_block .= '<!-- /wp:heading -->';
					}

					// Bind blurb block
					$gb_blurb_block = '';
					if ( ! empty( $gb_img_block ) ) {
						$gb_blurb_block = $gb_img_block;
					}

					if ( ! empty( $gb_heading_block ) ) {

						if ( ! empty( $gb_blurb_block ) ) {
							$gb_blurb_block .= PHP_EOL;
						}

						$gb_blurb_block .= $gb_heading_block;
						$gb_blurb_block .= PHP_EOL;
					}

					if ( ! empty( $gb_blurb_block ) ) {

						if ( false === strpos( $post_content, $shortcode ) ) {
							$shortcode = $match[0] . ']';
						}

						$post_content = str_replace( $shortcode, $gb_blurb_block, $post_content );
						$post_content = str_replace( '[/et_pb_blurb]', '', $post_content );

						$status = 'migrated';
					}
				} elseif ( 'et_pb_divider' === $shortcode_name ) {
					$class_str = 'wp-block-separator is-style-wide';
					$gb_attr   = '{"className":"is-style-wide"}';
					$hr_style  = '';

					if ( ! empty( $attributes['color'] ) ) {
						$class_str .= ' has-text-color has-background';
						$hr_style   = sprintf( 'style="background-color:%s;color:%s"', $attributes['color'], $attributes['color'] );
						$gb_attr    = sprintf( '{"customColor":"%s","className":"is-style-wide"}', $attributes['color'] );
					}

					$gb_divider_block  = sprintf( '<!-- wp:separator %s -->', $gb_attr );
					$gb_divider_block .= PHP_EOL;
					$gb_divider_block .= sprintf( '<hr class="%s" %s/>', $class_str, $hr_style );
					$gb_divider_block .= PHP_EOL;
					$gb_divider_block .= '<!-- /wp:separator -->';

					if ( false === strpos( $post_content, $shortcode ) ) {
						$shortcode = $match[0] . ']';
					}
					$post_content = str_replace( $shortcode, $gb_divider_block, $post_content );

					$status = 'migrated';

				} elseif ( 'et_pb_post_title' === $shortcode_name ) {

					$gb_title_block = '';
					if ( 'off' !== $attributes['title'] ) {
						// Title block
						$gb_attr   = '';
						$style_str = '';
						$h_classes = '';
						$h_level   = 'h2';
						$h_text    = $post['post_title'];

						if ( ! empty( $attributes['title_level'] ) && in_array( $attributes['title_level'], array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
							$h_level = $attributes['title_level'];

							if ( 'h2' !== $attributes['title_level'] ) {
								$gb_attr = sprintf( '"level":%s,', str_replace( 'h', '', $attributes['title_level'] ) );
							}
						}

						if ( ! empty( $attributes['title_font'] ) ) {
							$font_split = explode( '|', $attributes['title_font'] );

							$attributes['title_font'] = $font_split[0];

							$gb_attr   .= sprintf( '"fontFamily":"%s",', trim( $attributes['title_font'], '|' ) );
							$h_classes .= 'has-custom-font ';
							$style_str .= sprintf( 'font-family:%s;', trim( $attributes['title_font'], '|' ) );
						}
						if ( ! empty( $attributes['title_text_color'] ) ) {
							$gb_attr   .= sprintf( '"customTextColor":"%s",', $attributes['title_text_color'] );
							$h_classes .= 'has-text-color ';
							$style_str .= sprintf( 'color:%s;', $attributes['title_text_color'] );
						}
						if ( ! empty( $attributes['title_font_size'] ) ) {
							$gb_attr   .= sprintf( '"customFontSize":%s,', str_replace( 'px', '', $attributes['title_font_size'] ) );
							$style_str .= sprintf( 'font-size:%s;', $attributes['title_font_size'] );
						}
						if ( ! empty( $attributes['title_line_height'] ) ) {
							$gb_attr   .= sprintf( '"lineHeight":%s,', str_replace( 'em', '', $attributes['title_line_height'] ) );
							$h_classes .= 'has-custom-lineheight ';
							$style_str .= sprintf( 'line-height:%s;', $attributes['title_line_height'] );
						}

						if ( ! empty( $gb_attr ) ) {
							$gb_attr .= '"className":"' . trim( $h_classes ) . '",';
							$gb_attr  = '{' . trim( $gb_attr, ',' ) . '}';
						}

						if ( ! empty( $h_classes ) ) {
							$h_classes = 'class="' . trim( $h_classes ) . '"';
						}

						if ( ! empty( $style_str ) ) {
							$style_str = 'style="' . trim( $style_str, ';' ) . '"';
						}

						if ( ! empty( $attributes['link_option_url'] ) ) {
							$target_url = '';
							if ( ! empty( $attributes['link_option_url_new_window'] ) && 'on' === $attributes['link_option_url_new_window'] ) {
								$target_url = 'target="_blank"';
							}
							$h_text = sprintf( '<a href="%s" %s>%s</a>', $attributes['link_option_url'], $target_url, $h_text );
						}

						$gb_title_block  = sprintf( '<!-- wp:heading %s -->', $gb_attr );
						$gb_title_block .= PHP_EOL;
						$gb_title_block .= sprintf( '<%s %s %s>', $h_level, $h_classes, $style_str );
						$gb_title_block .= PHP_EOL;
						$gb_title_block .= $h_text;
						$gb_title_block .= PHP_EOL;
						$gb_title_block .= sprintf( '</%s>', $h_level );
						$gb_title_block .= PHP_EOL;
						$gb_title_block .= '<!-- /wp:heading -->';
					}

					$gb_author_line_block = '';
					if ( 'off' !== $attributes['meta'] ) {
						// Aithor by line: paragraph block

						$author_line = '';
						if ( 'off' !== $attributes['author'] ) {
							$author      = get_user_by( 'id', $post['post_author'] );
							$author_line = sprintf( 'by <a href="%s">%s</a>', get_author_posts_url( $post['post_author'] ), $author->display_name );
						}
						if ( 'off' !== $attributes['date'] ) {
							if ( ! empty( $author_line ) ) {
								$author_line .= ' | ';
							}
							$author_line .= date( 'M j, Y', strtotime( $post['post_date'] ) );
						}
						if ( 'off' !== $attributes['categories'] ) {
							if ( ! empty( $author_line ) ) {
								$author_line .= ' | ';
							}

							$terms_string = '';

							$terms = get_the_terms( $post['ID'], 'category' );
							if ( ! empty( $terms ) ) {
								foreach ( $terms as $term ) {
									$terms_string .= sprintf( '<a href="%s">%s</a>, ', get_term_link( $term->term_id ), $term->name );
								}
							}

							$author_line .= $terms_string;
						}
						if ( 'off' !== $attributes['comments'] ) {
							if ( ! empty( $author_line ) ) {
								$author_line .= ' | ';
							}
							$comment_status = '';
							$comment_count  = get_comment_count( $post['ID'] );

							if ( $comment_count['approved'] > 0 ) {
								$comment_status = $comment_count['approved'] . ' Comments';
							}

							$author_line .= $comment_status;
						}

						if ( ! empty( $author_line ) ) {
							$gb_author_line_block  = sprintf( '<!-- wp:paragraph -->' );
							$gb_author_line_block .= PHP_EOL;
							$gb_author_line_block .= '<p>' . $author_line . '</p>';
							$gb_author_line_block .= PHP_EOL;
							$gb_author_line_block .= '<!-- /wp:paragraph -->';
						}
					}

					$gb_img_block = '';
					if ( 'off' !== $attributes['featured_image'] ) {
						// Featured image block
						$att_id = get_post_meta( $post['ID'], '_thumbnail_id', true );

						$gb_attr   = sprintf( '"id":%s,"sizeSlug":"medium",', $att_id );
						$style_str = '';

						$gb_img_block  = sprintf( '<!-- wp:image {%s} -->', trim( $gb_attr, ',' ) );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= sprintf( '<figure class="wp-block-image align-left size-medium"><img src="%s" alt="" class="wp-image-%s"/></figure>', wp_get_attachment_url( $att_id ), $att_id );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= '<!-- /wp:image -->';
					}

					// Bind group block
					$gb_attr   = '';
					$style_str = '';
					$div_class = 'class="wp-block-group"';

					if ( ! empty( $attributes['background_color'] ) ) {
						$gb_attr   .= sprintf( '{"customBackgroundColor":"%s"} ', $attributes['background_color'] );
						$style_str .= sprintf( 'style="background-color:%s;"', $attributes['background_color'] );
						$div_class  = 'class="wp-block-group has-background"';
					}

					$gb_group_block  = sprintf( '<!-- wp:group %s-->', $gb_attr );
					$gb_group_block .= PHP_EOL;
					$gb_group_block .= sprintf( '<div %s %s><div class="wp-block-group__inner-container">', $div_class, $style_str );
					$gb_group_block .= PHP_EOL;

					if ( ! empty( $gb_img_block ) && 'above' === $attributes['featured_placement'] ) {
						$gb_group_block .= $gb_img_block;
						$gb_group_block .= PHP_EOL;
					}

					if ( ! empty( $gb_title_block ) ) {
						$gb_group_block .= $gb_title_block;
						$gb_group_block .= PHP_EOL;
					}

					if ( ! empty( $gb_author_line_block ) ) {
						$gb_group_block .= $gb_author_line_block;
						$gb_group_block .= PHP_EOL;
					}

					if ( ! empty( $gb_img_block ) && 'above' !== $attributes['featured_placement'] ) {
						$gb_group_block .= $gb_img_block;
						$gb_group_block .= PHP_EOL;
					}

					$gb_group_block .= '</div></div>';
					$gb_group_block .= PHP_EOL;
					$gb_group_block .= '<!-- /wp:group -->';

					if ( false === strpos( $post_content, $shortcode ) ) {
						$shortcode = $match[0] . ']';
					}
					$post_content = str_replace( $shortcode, $gb_group_block, $post_content );

					$status = 'migrated';

				} elseif ( 'et_pb_video' === $shortcode_name ) {
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

						$status = 'migrated';
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

						$status = 'migrated';
					}
				} elseif ( 'et_pb_button' === $shortcode_name ) {

					$target_url = '';
					if ( ! empty( $attributes['url_new_window'] ) && 'on' === $attributes['url_new_window'] ) {
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
						$gb_img_block .= sprintf( '<figure class="wp-block-image align%s size-medium"><img src="%s" alt="%s" class="wp-image-%s"/></figure>', $attributes['align'], $attributes['src'], $attributes['alt'], $att_id );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= '<!-- /wp:image -->';
						$status        = 'migrated';

						if ( false === strpos( $post_content, $shortcode ) ) {
							$shortcode = $match[0] . ']';
						}
						$post_content = str_replace( $shortcode, $gb_img_block, $post_content );

					} else {
						$gb_attr = sprintf( 'sizeSlug":"medium",' );

						if ( empty( $attributes['align'] ) ) {
							$attributes['align'] = 'left';
						}
						$gb_attr .= sprintf( '"align":"%s",', $attributes['align'] );

						$gb_img_block  = sprintf( '<!-- wp:image {%s} -->', trim( $gb_attr, ',' ) );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= sprintf( '<figure class="wp-block-image align%s size-medium"><img src="%s" alt="%s"/></figure>', $attributes['align'], $attributes['src'], $attributes['alt'] );
						$gb_img_block .= PHP_EOL;
						$gb_img_block .= '<!-- /wp:image -->';
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

		$post_content = $this->divi_migrate_post_divi5_blocks( $post_content, $post, $logs );
		$post_content = str_replace( '<!-- wp:divi/placeholder -->', '', $post_content );
		$post_content = str_replace( '<!-- /wp:divi/placeholder -->', '', $post_content );

		if ( ! $this->dry_run ) {

			$new_post = array(
				'ID'                => $post['ID'],
				'post_content'      => $post_content,
				'post_modified'     => $post['post_modified'],
				'post_modified_gmt' => $post['post_modified_gmt'],
			);

			$result = wp_update_post( $new_post, true );
			if ( ! is_wp_error( $result ) ) {
				$this->set_divi_builder_editor_mode( $post['ID'], false );
			}
		} else {
			$result = true;
		}

		return $result;
	}

	/**
	 * Toggle Divi builder metadata so the editor matches the stored content.
	 *
	 * @param int  $post_id          Post ID.
	 * @param bool $use_divi_builder Whether the post should open in Divi Builder.
	 *
	 * @return void
	 */
	private function set_divi_builder_editor_mode( $post_id, $use_divi_builder ) {
		update_post_meta( $post_id, '_et_pb_use_builder', $use_divi_builder ? 'on' : 'off' );

		$content = get_post_field( 'post_content', $post_id );
		update_post_meta( $post_id, '_et_pb_use_divi_5', $use_divi_builder && false !== strpos( $content, '<!-- wp:divi/' ) ? 'on' : 'off' );
	}

	/**
	 * Convert supported Divi 5 blocks in post content to Gutenberg blocks.
	 *
	 * @param string $post_content Post content.
	 * @param array  $post         Post object array.
	 * @param array  $logs         Detailed logs.
	 *
	 * @return string
	 */
	private function divi_migrate_post_divi5_blocks( $post_content, $post, &$logs ) {
		if ( false === strpos( $post_content, '<!-- wp:divi/' ) ) {
			return $post_content;
		}

		$blocks = parse_blocks( $post_content );

		return $this->serialize_migrated_divi5_blocks( $blocks, $post, $logs );
	}

	/**
	 * Serialize a list of blocks after recursively converting supported Divi 5 blocks.
	 *
	 * @param array $blocks Blocks.
	 * @param array $post   Post object array.
	 * @param array $logs   Detailed logs.
	 *
	 * @return string
	 */
	private function serialize_migrated_divi5_blocks( $blocks, $post, &$logs ) {
		$content = '';

		foreach ( $blocks as $block ) {
			$content .= $this->serialize_migrated_divi5_block( $block, $post, $logs );
		}

		return $content;
	}

	/**
	 * Serialize a single block after applying Divi 5 conversion rules.
	 *
	 * @param array $block Block.
	 * @param array $post  Post object array.
	 * @param array $logs  Detailed logs.
	 *
	 * @return string
	 */
	private function serialize_migrated_divi5_block( $block, $post, &$logs ) {
		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : null;

		if ( empty( $block_name ) ) {
			return isset( $block['innerHTML'] ) ? $block['innerHTML'] : '';
		}

		if ( in_array( $block_name, $this->clearable_divi5_blocks, true ) ) {
			if ( ! empty( $block['innerBlocks'] ) ) {
				return $this->serialize_migrated_divi5_blocks( $block['innerBlocks'], $post, $logs );
			}

			return $this->get_divi5_clearable_block_content( $block );
		}

		if ( isset( $this->migratable_divi5_blocks[ $block_name ] ) ) {
			$status      = 'failed';
			$replacement = $this->convert_divi5_block_to_gutenberg( $block, $post );

			if ( '' !== $replacement ) {
				$status = 'migrated';
			} else {
				$replacement = serialize_block( $block );
			}

			if ( $this->dry_run ) {
				$status = 'to be ' . $status;
			}

			$logs[] = array( $post['ID'], $block_name, serialize_block( $block ), $status );

			return $replacement;
		}

		if ( in_array( $block_name, $this->skippable_divi5_blocks, true ) ) {
			$status = $this->dry_run ? 'to be skipped' : 'skipped';
			$logs[] = array( $post['ID'], $block_name, serialize_block( $block ), $status );

			return serialize_block( $block );
		}

		if ( 0 === strpos( $block_name, 'divi/' ) ) {
			$status = $this->dry_run ? 'to be failed' : 'failed';
			$logs[] = array( $post['ID'], $block_name, serialize_block( $block ), $status );

			if ( empty( $block['innerBlocks'] ) ) {
				return serialize_block( $block );
			}

			return $this->serialize_block_with_migrated_inner_blocks( $block, $post, $logs );
		}

		if ( empty( $block['innerBlocks'] ) ) {
			return serialize_block( $block );
		}

		return $this->serialize_block_with_migrated_inner_blocks( $block, $post, $logs );
	}

	/**
	 * Serialize a non-Divi block while allowing Divi child blocks to be migrated.
	 *
	 * @param array $block Block.
	 * @param array $post  Post object array.
	 * @param array $logs  Detailed logs.
	 *
	 * @return string
	 */
	private function serialize_block_with_migrated_inner_blocks( $block, $post, &$logs ) {
		if ( empty( $block['innerContent'] ) || ! function_exists( 'get_comment_delimited_block_content' ) ) {
			return serialize_block( $block );
		}

		$inner_content = '';
		$inner_index   = 0;

		foreach ( $block['innerContent'] as $chunk ) {
			if ( null === $chunk ) {
				if ( isset( $block['innerBlocks'][ $inner_index ] ) ) {
					$inner_content .= $this->serialize_migrated_divi5_block( $block['innerBlocks'][ $inner_index ], $post, $logs );
				}

				$inner_index++;
				continue;
			}

			$inner_content .= $chunk;
		}

		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();

		return get_comment_delimited_block_content( $block['blockName'], $attrs, $inner_content );
	}

	/**
	 * Convert a supported Divi 5 block to Gutenberg markup.
	 *
	 * @param array $block Block.
	 * @param array $post  Post object array.
	 *
	 * @return string
	 */
	private function convert_divi5_block_to_gutenberg( $block, $post ) {
		$block_name = isset( $block['blockName'] ) ? $block['blockName'] : '';
		$attrs      = isset( $block['attrs'] ) ? $block['attrs'] : array();

		switch ( $block_name ) {
			case 'divi/blurb':
				return $this->convert_divi5_blurb_block( $attrs );

			case 'divi/divider':
				$line = $this->get_divi5_attribute( $attrs, array( 'divider', 'advanced', 'line' ), array() );
				$color = is_array( $line ) && ! empty( $line['color'] ) ? $line['color'] : '';

				return $this->get_gutenberg_divider_block( $color );

			case 'divi/post-title':
				return $this->convert_divi5_post_title_block( $attrs, $post );

			case 'divi/video':
				return $this->convert_divi5_video_block( $attrs );

			case 'divi/button':
				$button = $this->get_divi5_attribute( $attrs, array( 'button', 'innerContent' ), array() );
				if ( ! is_array( $button ) ) {
					$button = array();
				}

				$font       = $this->get_divi5_attribute( $attrs, array( 'button', 'decoration', 'font', 'font' ), array() );
				$background = $this->get_divi5_attribute( $attrs, array( 'button', 'decoration', 'background' ), array() );
				$text_color = is_array( $font ) && ! empty( $font['color'] ) ? $font['color'] : '';
				$bg_color   = is_array( $background ) && ! empty( $background['color'] ) ? $background['color'] : '';

				return $this->get_gutenberg_button_block(
					isset( $button['linkUrl'] ) ? $button['linkUrl'] : '',
					! empty( $button['linkTarget'] ) && 'on' === $button['linkTarget'],
					isset( $button['text'] ) ? $button['text'] : '',
					$text_color,
					$bg_color
				);

			case 'divi/image':
			case 'divi/fullwidth-image':
				$image         = $this->get_divi5_attribute( $attrs, array( 'image', 'innerContent' ), array() );
				$sizing        = $this->get_divi5_attribute( $attrs, array( 'module', 'advanced', 'sizing' ), array() );
				$default_align = 'divi/fullwidth-image' === $block_name ? 'full' : '';
				if ( 'divi/image' === $block_name && is_array( $sizing ) && ! empty( $sizing['forceFullwidth'] ) && 'off' !== $sizing['forceFullwidth'] ) {
					$default_align = 'full';
				}
				$align         = $this->get_divi5_attribute( $attrs, array( 'module', 'advanced', 'align' ), $default_align );

				if ( ! is_array( $image ) || empty( $image['src'] ) ) {
					return '';
				}

				return $this->get_gutenberg_image_block(
					$image['src'],
					$this->get_divi5_image_alt( $attrs, isset( $image['alt'] ) ? $image['alt'] : '' ),
					! empty( $align ) ? $align : $default_align,
					is_array( $sizing ) && ! empty( $sizing['maxWidth'] ) ? $sizing['maxWidth'] : '',
					is_array( $sizing ) && ! empty( $sizing['maxHeight'] ) ? $sizing['maxHeight'] : ''
				);
		}

		return '';
	}

	/**
	 * Convert a Divi 5 video block.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private function convert_divi5_video_block( $attrs ) {
		$video = $this->get_divi5_attribute( $attrs, array( 'video', 'innerContent' ), array() );

		if ( ! is_array( $video ) ) {
			return '';
		}

		$src = isset( $video['src'] ) ? $video['src'] : '';

		if ( ! empty( $src ) && false !== strpos( $src, 'youtube.com' ) ) {
			return $this->get_gutenberg_youtube_block( $src );
		}

		$webm = isset( $video['webm'] ) ? $video['webm'] : '';
		if ( empty( $src ) && ! empty( $webm ) ) {
			$thumbnail = $this->get_divi5_attribute( $attrs, array( 'thumbnail', 'innerContent' ), array() );
			$poster    = is_array( $thumbnail ) && ! empty( $thumbnail['src'] ) ? $thumbnail['src'] : '';

			return $this->get_gutenberg_video_block( $webm, $poster );
		}

		return '';
	}

	/**
	 * Convert a Divi 5 blurb block.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string
	 */
	private function convert_divi5_blurb_block( $attrs ) {
		$image_icon = $this->get_divi5_attribute( $attrs, array( 'imageIcon', 'innerContent' ), array() );
		$alignment  = $this->get_divi5_attribute( $attrs, array( 'imageIcon', 'advanced', 'alignment' ), '' );
		if ( empty( $alignment ) ) {
			$sizing    = $this->get_divi5_attribute( $attrs, array( 'imageIcon', 'decoration', 'sizing' ), array() );
			$alignment = is_array( $sizing ) && ! empty( $sizing['alignment'] ) ? $sizing['alignment'] : '';
		}

		$gb_blurb_block = '';

		if ( is_array( $image_icon ) && ! empty( $image_icon['src'] ) ) {
			$gb_blurb_block = $this->get_gutenberg_image_block(
				$image_icon['src'],
				$this->get_divi5_image_alt( $attrs, isset( $image_icon['alt'] ) ? $image_icon['alt'] : '', array( 'imageIcon' ) ),
				! empty( $alignment ) ? $alignment : ''
			);
		}

		$title = $this->get_divi5_attribute( $attrs, array( 'title', 'innerContent' ), array() );
		if ( is_array( $title ) && ! empty( $title['text'] ) ) {
			$font        = $this->get_divi5_attribute( $attrs, array( 'title', 'decoration', 'font', 'font' ), array() );
			$level       = is_array( $font ) && ! empty( $font['headingLevel'] ) ? $font['headingLevel'] : 'h4';
			$heading_url = ! empty( $title['url'] ) ? $title['url'] : '';
			$new_window  = ! empty( $title['target'] ) && 'on' === $title['target'];

			if ( '' !== $gb_blurb_block ) {
				$gb_blurb_block .= PHP_EOL;
			}

			$gb_blurb_block .= $this->get_gutenberg_heading_block( $title['text'], $level, $heading_url, $new_window );
			$gb_blurb_block .= PHP_EOL;
		}

		$content = $this->get_divi5_attribute( $attrs, array( 'content', 'innerContent' ), '' );
		if ( is_array( $content ) && isset( $content['text'] ) ) {
			$content = $content['text'];
		}

		if ( is_string( $content ) && '' !== trim( $content ) ) {
			$gb_blurb_block .= $this->get_gutenberg_paragraph_block( $content );
		}

		return $gb_blurb_block;
	}

	/**
	 * Convert a Divi 5 post title block.
	 *
	 * @param array $attrs Block attributes.
	 * @param array $post  Post object array.
	 *
	 * @return string
	 */
	private function convert_divi5_post_title_block( $attrs, $post ) {
		$show_title = $this->get_divi5_attribute( $attrs, array( 'title', 'advanced', 'showTitle' ), 'on' );
		$show_meta  = $this->get_divi5_attribute( $attrs, array( 'meta', 'advanced', 'showMeta' ), 'on' );
		$show_image = $this->get_divi5_attribute( $attrs, array( 'featuredImage', 'advanced', 'enabled' ), 'on' );
		$placement  = $this->get_divi5_attribute( $attrs, array( 'featuredImage', 'advanced', 'placement' ), 'below' );

		$gb_title_block = '';
		if ( 'off' !== $show_title ) {
			$font = $this->get_divi5_attribute( $attrs, array( 'title', 'decoration', 'font', 'font' ), array() );
			if ( ! is_array( $font ) ) {
				$font = array();
			}

			$link = $this->get_divi5_attribute( $attrs, array( 'module', 'advanced', 'link' ), array() );
			if ( ! is_array( $link ) ) {
				$link = array();
			}

			$gb_title_block = $this->get_gutenberg_post_title_heading_block(
				$post,
				isset( $font['headingLevel'] ) ? $font['headingLevel'] : 'h2',
				isset( $font['fontFamily'] ) ? $font['fontFamily'] : '',
				isset( $font['color'] ) ? $font['color'] : '',
				isset( $font['size'] ) ? $font['size'] : '',
				isset( $font['lineHeight'] ) ? $font['lineHeight'] : '',
				isset( $link['url'] ) ? $link['url'] : '',
				! empty( $link['target'] ) && 'on' === $link['target']
			);
		}

		$gb_author_line_block = '';
		if ( 'off' !== $show_meta ) {
			$gb_author_line_block = $this->get_gutenberg_post_title_meta_block(
				$post,
				'off' !== $this->get_divi5_attribute( $attrs, array( 'meta', 'advanced', 'showAuthor' ), 'on' ),
				'off' !== $this->get_divi5_attribute( $attrs, array( 'meta', 'advanced', 'showDate' ), 'on' ),
				'off' !== $this->get_divi5_attribute( $attrs, array( 'meta', 'advanced', 'showCategories' ), 'on' ),
				'off' !== $this->get_divi5_attribute( $attrs, array( 'meta', 'advanced', 'showCommentsCount' ), 'on' )
			);
		}

		$gb_img_block = '';
		if ( 'off' !== $show_image ) {
			$att_id = get_post_meta( $post['ID'], '_thumbnail_id', true );
			if ( ! empty( $att_id ) ) {
				$gb_img_block = $this->get_gutenberg_featured_image_block( $att_id );
			}
		}

		$background       = $this->get_divi5_attribute( $attrs, array( 'module', 'decoration', 'background' ), array() );
		$background_color = is_array( $background ) && ! empty( $background['color'] ) ? $background['color'] : '';

		return $this->get_gutenberg_post_title_group_block(
			$gb_title_block,
			$gb_author_line_block,
			$gb_img_block,
			$placement,
			$background_color
		);
	}

	/**
	 * Get content stored in Divi 5 blocks that the legacy command clears.
	 *
	 * @param array $block Block.
	 *
	 * @return string
	 */
	private function get_divi5_clearable_block_content( $block ) {
		$attrs = isset( $block['attrs'] ) ? $block['attrs'] : array();
		$value = $this->get_divi5_attribute( $attrs, array( 'content', 'innerContent' ), '' );

		if ( is_array( $value ) && isset( $value['text'] ) ) {
			$value = $value['text'];
		}

		if ( is_string( $value ) ) {
			if ( isset( $block['blockName'] ) && 'divi/text' === $block['blockName'] ) {
				return $this->get_gutenberg_paragraph_block( $value );
			}

			return $value;
		}

		return '';
	}

	/**
	 * Read a Divi 5 nested attribute and unwrap its desktop value.
	 *
	 * @param array $attrs   Attributes.
	 * @param array $path    Attribute path.
	 * @param mixed $default Default value.
	 *
	 * @return mixed
	 */
	private function get_divi5_attribute( $attrs, $path, $default = '' ) {
		$value = $attrs;

		foreach ( $path as $part ) {
			if ( ! is_array( $value ) || ! array_key_exists( $part, $value ) ) {
				return $default;
			}

			$value = $value[ $part ];
		}

		return $this->unwrap_divi5_responsive_value( $value, $default );
	}

	/**
	 * Unwrap Divi 5 responsive attribute containers.
	 *
	 * @param mixed $value   Value.
	 * @param mixed $default Default value.
	 *
	 * @return mixed
	 */
	private function unwrap_divi5_responsive_value( $value, $default = '' ) {
		if ( is_array( $value ) && array_key_exists( 'desktop', $value ) && is_array( $value['desktop'] ) && array_key_exists( 'value', $value['desktop'] ) ) {
			return $value['desktop']['value'];
		}

		if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
			return $value['value'];
		}

		return null === $value ? $default : $value;
	}

	/**
	 * Read image alt text from either legacy or current Divi 5 image attributes.
	 *
	 * @param array        $attrs           Block attributes.
	 * @param string       $default_alt     Default alt text.
	 * @param string|array $target_elements Target element names.
	 *
	 * @return string
	 */
	private function get_divi5_image_alt( $attrs, $default_alt = '', $target_elements = array( 'image' ) ) {
		$target_elements = (array) $target_elements;
		$decorative_attributes = $this->get_divi5_attribute( $attrs, array( 'module', 'decoration', 'attributes' ), array() );

		if ( is_array( $decorative_attributes ) && isset( $decorative_attributes['attributes'] ) && is_array( $decorative_attributes['attributes'] ) ) {
			$decorative_attributes = $decorative_attributes['attributes'];
		}

		if ( is_array( $decorative_attributes ) ) {
			foreach ( $decorative_attributes as $attribute ) {
				if (
					is_array( $attribute )
					&& isset( $attribute['targetElement'], $attribute['name'], $attribute['value'] )
					&& in_array( $attribute['targetElement'], $target_elements, true )
					&& 'alt' === $attribute['name']
				) {
					return $attribute['value'];
				}
			}
		}

		return $default_alt;
	}

	/**
	 * Build one or more Gutenberg paragraph blocks.
	 *
	 * @param string $content Paragraph content.
	 *
	 * @return string
	 */
	private function get_gutenberg_paragraph_block( $content ) {
		$content = trim( $this->normalize_divi5_html_content( $content ) );

		if ( '' === $content ) {
			return '';
		}

		if ( preg_match_all( '/<p\b[^>]*>.*?<\/p>/is', $content, $matches ) ) {
			$outside_paragraphs = trim( str_replace( $matches[0], '', $content ) );
			if ( '' === $outside_paragraphs ) {
				$blocks = array();
				foreach ( $matches[0] as $paragraph ) {
					$blocks[] = '<!-- wp:paragraph -->' . PHP_EOL . wp_kses_post( trim( $paragraph ) ) . PHP_EOL . '<!-- /wp:paragraph -->';
				}

				return implode( PHP_EOL, $blocks );
			}
		}

		$paragraph  = '<!-- wp:paragraph -->';
		$paragraph .= PHP_EOL;
		$paragraph .= '<p>' . wp_kses_post( $content ) . '</p>';
		$paragraph .= PHP_EOL;
		$paragraph .= '<!-- /wp:paragraph -->';

		return $paragraph;
	}

	/**
	 * Normalize HTML stored in Divi 5 inner content values.
	 *
	 * @param string $content Content.
	 *
	 * @return string
	 */
	private function normalize_divi5_html_content( $content ) {
		if ( ! is_string( $content ) ) {
			return '';
		}

		return str_replace(
			array( '\\u003c', '\\u003e', '\\u0026', '\\u0022', '\\u0027', '\\u002F', '\\u003d', 'u003c', 'u003e', 'u0026', 'u0022', 'u0027', 'u002F', 'u003d' ),
			array( '<', '>', '&', '"', "'", '/', '=', '<', '>', '&', '"', "'", '/', '=' ),
			$content
		);
	}

	/**
	 * Build a Gutenberg button block.
	 *
	 * @param string $url        Button URL.
	 * @param bool   $new_window Whether to open in a new window.
	 * @param string $text       Button text.
	 * @param string $text_color Text color.
	 * @param string $bg_color   Background color.
	 *
	 * @return string
	 */
	private function get_gutenberg_button_block( $url, $new_window, $text, $text_color = '', $bg_color = '' ) {
		$attrs      = array();
		$classes    = array( 'wp-block-button__link' );
		$style_bits = array();

		if ( $new_window ) {
			$attrs['linkTarget'] = '_blank';
		}

		if ( ! empty( $text_color ) ) {
			$attrs['style']['color']['text'] = $text_color;
			$classes[] = 'has-text-color';
			$style_bits[] = sprintf( 'color:%s', $text_color );
		}

		if ( ! empty( $bg_color ) ) {
			$attrs['style']['color']['background'] = $bg_color;
			$classes[] = 'has-background';
			$style_bits[] = sprintf( 'background-color:%s', $bg_color );
		}

		$classes[] = 'wp-element-button';

		$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );
		$href     = empty( $url ) ? '' : sprintf( ' href="%s"', esc_url( $url ) );
		$target   = $new_window ? ' target="_blank"' : '';
		$style    = empty( $style_bits ) ? '' : ' style="' . esc_attr( implode( ';', $style_bits ) ) . '"';

		$gb_button_block  = sprintf( '<!-- wp:button%s -->', $attr_json );
		$gb_button_block .= PHP_EOL;
		$gb_button_block .= sprintf( '<div class="wp-block-button"><a class="%s"%s%s%s>%s</a></div>', esc_attr( implode( ' ', $classes ) ), $href, $target, $style, wp_kses_post( $text ) );
		$gb_button_block .= PHP_EOL;
		$gb_button_block .= '<!-- /wp:button -->';

		return $gb_button_block;
	}

	/**
	 * Build a Gutenberg image block.
	 *
	 * @param string $src    Image URL.
	 * @param string $alt    Image alt text.
	 * @param string $align  Image alignment.
	 * @param string $width  Image width.
	 * @param string $height Image height.
	 *
	 * @return string
	 */
	private function get_gutenberg_image_block( $src, $alt = '', $align = '', $width = '', $height = '' ) {
		$att_id      = attachment_url_to_postid( $src );
		$attrs       = array(
			'sizeSlug'        => 'medium',
			'linkDestination' => 'none',
		);
		$style_bits  = array();
		$figure_bits = array( 'wp-block-image' );

		if ( $att_id ) {
			$attrs['id'] = $att_id;
		}

		if ( ! empty( $align ) ) {
			$attrs['align'] = $align;
			$figure_bits[]  = 'align' . $align;
		}

		if ( ! empty( $width ) ) {
			$attrs['width'] = $width;
			$style_bits[]   = sprintf( 'width:%s', $width );
		}

		if ( ! empty( $height ) ) {
			$attrs['height'] = $height;
			$style_bits[]    = sprintf( 'height:%s', $height );
		}

		$figure_bits[] = 'size-medium';
		if ( ! empty( $width ) || ! empty( $height ) ) {
			$figure_bits[] = 'is-resized';
		}

		$img_class = $att_id ? sprintf( ' class="wp-image-%s"', esc_attr( $att_id ) ) : '';
		$style     = ! empty( $style_bits ) ? ' style="' . esc_attr( implode( ';', $style_bits ) ) . '"' : '';

		$gb_img_block  = sprintf( '<!-- wp:image %s -->', wp_json_encode( $attrs ) );
		$gb_img_block .= PHP_EOL;
		$gb_img_block .= sprintf( '<figure class="%s"><img src="%s" alt="%s"%s%s/></figure>', esc_attr( implode( ' ', $figure_bits ) ), esc_url( $src ), esc_attr( $alt ), $img_class, $style );
		$gb_img_block .= PHP_EOL;
		$gb_img_block .= '<!-- /wp:image -->';

		return $gb_img_block;
	}

	/**
	 * Build a Gutenberg heading block.
	 *
	 * @param string $text       Heading text.
	 * @param string $level      Heading level.
	 * @param string $url        Heading link.
	 * @param bool   $new_window Whether to open in a new window.
	 *
	 * @return string
	 */
	private function get_gutenberg_heading_block( $text, $level = 'h4', $url = '', $new_window = false ) {
		if ( ! in_array( $level, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$level = 'h4';
		}

		$attrs = array();
		if ( 'h2' !== $level ) {
			$attrs['level'] = (int) str_replace( 'h', '', $level );
		}

		if ( ! empty( $url ) ) {
			$target = $new_window ? ' target="_blank"' : '';
			$text   = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $target, wp_kses_post( $text ) );
		}

		$attr_json         = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );
		$gb_heading_block  = sprintf( '<!-- wp:heading%s -->', $attr_json );
		$gb_heading_block .= PHP_EOL;
		$gb_heading_block .= sprintf( '<%s class="wp-block-heading">', $level );
		$gb_heading_block .= wp_kses_post( $text );
		$gb_heading_block .= sprintf( '</%s>', $level );
		$gb_heading_block .= PHP_EOL;
		$gb_heading_block .= '<!-- /wp:heading -->';

		return $gb_heading_block;
	}

	/**
	 * Build a Gutenberg separator block.
	 *
	 * @param string $color Separator color.
	 *
	 * @return string
	 */
	private function get_gutenberg_divider_block( $color = '' ) {
		$class_str = 'wp-block-separator has-alpha-channel-opacity is-style-wide';
		$gb_attr   = array(
			'opacity'   => 'alpha-channel',
			'className' => 'is-style-wide',
		);
		$hr_style  = '';

		if ( ! empty( $color ) ) {
			$class_str = 'wp-block-separator has-text-color has-alpha-channel-opacity has-background is-style-wide';
			$hr_style   = sprintf( ' style="background-color:%s;color:%s"', esc_attr( $color ), esc_attr( $color ) );
			$gb_attr    = array(
				'opacity'   => 'alpha-channel',
				'className' => 'is-style-wide',
				'style'     => array(
					'color' => array(
						'background' => $color,
					),
				),
			);
		}

		$gb_divider_block  = sprintf( '<!-- wp:separator %s -->', wp_json_encode( $gb_attr ) );
		$gb_divider_block .= PHP_EOL;
		$gb_divider_block .= sprintf( '<hr class="%s"%s/>', esc_attr( $class_str ), $hr_style );
		$gb_divider_block .= PHP_EOL;
		$gb_divider_block .= '<!-- /wp:separator -->';

		return $gb_divider_block;
	}

	/**
	 * Build a Gutenberg YouTube embed block.
	 *
	 * @param string $src Video URL.
	 *
	 * @return string
	 */
	private function get_gutenberg_youtube_block( $src ) {
		$attrs = array(
			'url'              => $src,
			'type'             => 'video',
			'providerNameSlug' => 'youtube',
			'responsive'       => true,
			'className'        => 'wp-embed-aspect-16-9 wp-has-aspect-ratio',
		);

		$gb_youtube_block  = sprintf( '<!-- wp:embed %s -->', wp_json_encode( $attrs ) );
		$gb_youtube_block .= PHP_EOL;
		$gb_youtube_block .= '<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">';
		$gb_youtube_block .= PHP_EOL;
		$gb_youtube_block .= esc_url( $src );
		$gb_youtube_block .= PHP_EOL;
		$gb_youtube_block .= '</div></figure>';
		$gb_youtube_block .= PHP_EOL;
		$gb_youtube_block .= '<!-- /wp:embed -->';

		return $gb_youtube_block;
	}

	/**
	 * Build a Gutenberg video block.
	 *
	 * @param string $src    Video URL.
	 * @param string $poster Poster URL.
	 *
	 * @return string
	 */
	private function get_gutenberg_video_block( $src, $poster = '' ) {
		$att_id = attachment_url_to_postid( $src );
		$attrs  = $att_id ? array( 'id' => $att_id ) : array();
		$poster = empty( $poster ) ? '' : ' poster="' . esc_url( $poster ) . '"';

		$gb_video_block  = empty( $attrs ) ? '<!-- wp:video -->' : sprintf( '<!-- wp:video %s -->', wp_json_encode( $attrs ) );
		$gb_video_block .= PHP_EOL;
		$gb_video_block .= '<figure class="wp-block-video">';
		$gb_video_block .= PHP_EOL;
		$gb_video_block .= sprintf( '<video controls src="%s"%s></video>', esc_url( $src ), $poster );
		$gb_video_block .= PHP_EOL;
		$gb_video_block .= '</figure>';
		$gb_video_block .= PHP_EOL;
		$gb_video_block .= '<!-- /wp:video -->';

		return $gb_video_block;
	}

	/**
	 * Build the heading block used by post title conversion.
	 *
	 * @param array  $post       Post object array.
	 * @param string $level      Heading level.
	 * @param string $font       Font family.
	 * @param string $color      Text color.
	 * @param string $size       Font size.
	 * @param string $lineheight Line height.
	 * @param string $url        Link URL.
	 * @param bool   $new_window Whether to open in a new window.
	 *
	 * @return string
	 */
	private function get_gutenberg_post_title_heading_block( $post, $level = 'h2', $font = '', $color = '', $size = '', $lineheight = '', $url = '', $new_window = false ) {
		if ( ! in_array( $level, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$level = 'h2';
		}

		$attrs      = array();
		$classes    = array( 'wp-block-heading' );
		$style_bits = array();

		if ( 'h2' !== $level ) {
			$attrs['level'] = (int) str_replace( 'h', '', $level );
		}

		if ( ! empty( $font ) ) {
			$attrs['style']['typography']['fontFamily'] = $font;
			$style_bits[] = sprintf( 'font-family:%s', $font );
		}

		if ( ! empty( $color ) ) {
			$attrs['style']['color']['text'] = $color;
			$classes[]                      = 'has-text-color';
			$style_bits[] = sprintf( 'color:%s', $color );
		}

		if ( ! empty( $size ) ) {
			$attrs['style']['typography']['fontSize'] = $size;
			$classes[]                                = 'has-custom-font-size';
			$style_bits[] = sprintf( 'font-size:%s', $size );
		}

		if ( ! empty( $lineheight ) ) {
			$attrs['style']['typography']['lineHeight'] = $lineheight;
			$style_bits[] = sprintf( 'line-height:%s', $lineheight );
		}

		$h_text = $post['post_title'];
		if ( ! empty( $url ) ) {
			$target = $new_window ? ' target="_blank"' : '';
			$h_text = sprintf( '<a href="%s"%s>%s</a>', esc_url( $url ), $target, esc_html( $h_text ) );
		} else {
			$h_text = esc_html( $h_text );
		}

		$class_attr = ' class="' . esc_attr( implode( ' ', array_unique( $classes ) ) ) . '"';
		$style_attr = ! empty( $style_bits ) ? ' style="' . esc_attr( implode( ';', $style_bits ) ) . '"' : '';
		$attr_json  = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );

		$gb_title_block  = sprintf( '<!-- wp:heading%s -->', $attr_json );
		$gb_title_block .= PHP_EOL;
		$gb_title_block .= sprintf( '<%s%s%s>', $level, $class_attr, $style_attr );
		$gb_title_block .= $h_text;
		$gb_title_block .= sprintf( '</%s>', $level );
		$gb_title_block .= PHP_EOL;
		$gb_title_block .= '<!-- /wp:heading -->';

		return $gb_title_block;
	}

	/**
	 * Build the meta paragraph used by post title conversion.
	 *
	 * @param array $post            Post object array.
	 * @param bool  $include_author  Include author.
	 * @param bool  $include_date    Include date.
	 * @param bool  $include_terms   Include categories.
	 * @param bool  $include_comment Include comments.
	 *
	 * @return string
	 */
	private function get_gutenberg_post_title_meta_block( $post, $include_author, $include_date, $include_terms, $include_comment ) {
		$author_line = '';

		if ( $include_author ) {
			$author = get_user_by( 'id', $post['post_author'] );
			if ( $author ) {
				$author_line = sprintf( 'by <a href="%s">%s</a>', esc_url( get_author_posts_url( $post['post_author'] ) ), esc_html( $author->display_name ) );
			}
		}

		if ( $include_date ) {
			if ( ! empty( $author_line ) ) {
				$author_line .= ' | ';
			}
			$author_line .= esc_html( date( 'M j, Y', strtotime( $post['post_date'] ) ) );
		}

		if ( $include_terms ) {
			if ( ! empty( $author_line ) ) {
				$author_line .= ' | ';
			}

			$terms = get_the_terms( $post['ID'], 'category' );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$author_line .= sprintf( '<a href="%s">%s</a>, ', esc_url( get_term_link( $term->term_id ) ), esc_html( $term->name ) );
				}
			}
		}

		if ( $include_comment ) {
			if ( ! empty( $author_line ) ) {
				$author_line .= ' | ';
			}

			$comment_count = get_comment_count( $post['ID'] );
			if ( $comment_count['approved'] > 0 ) {
				$author_line .= esc_html( $comment_count['approved'] . ' Comments' );
			}
		}

		if ( empty( $author_line ) ) {
			return '';
		}

		$gb_author_line_block  = '<!-- wp:paragraph -->';
		$gb_author_line_block .= PHP_EOL;
		$gb_author_line_block .= '<p>' . $author_line . '</p>';
		$gb_author_line_block .= PHP_EOL;
		$gb_author_line_block .= '<!-- /wp:paragraph -->';

		return $gb_author_line_block;
	}

	/**
	 * Build featured image block used by post title conversion.
	 *
	 * @param int $att_id Attachment ID.
	 *
	 * @return string
	 */
	private function get_gutenberg_featured_image_block( $att_id ) {
		$src = wp_get_attachment_url( $att_id );
		if ( empty( $src ) ) {
			return '';
		}

		return $this->get_gutenberg_image_block( $src, '', '' );
	}

	/**
	 * Build post title group block.
	 *
	 * @param string $title_block      Title block.
	 * @param string $meta_block       Meta block.
	 * @param string $image_block      Image block.
	 * @param string $image_placement  Featured image placement.
	 * @param string $background_color Background color.
	 *
	 * @return string
	 */
	private function get_gutenberg_post_title_group_block( $title_block, $meta_block, $image_block, $image_placement, $background_color = '' ) {
		$attrs      = array();
		$style_attr = '';
		$div_class  = 'wp-block-group';

		if ( ! empty( $background_color ) ) {
			$attrs['style']['color']['background'] = $background_color;
			$style_attr = sprintf( ' style="background-color:%s;"', esc_attr( $background_color ) );
			$div_class .= ' has-background';
		}

		$attr_json = empty( $attrs ) ? '' : ' ' . wp_json_encode( $attrs );

		$gb_group_block  = sprintf( '<!-- wp:group%s -->', $attr_json );
		$gb_group_block .= PHP_EOL;
		$gb_group_block .= sprintf( '<div class="%s"%s>', esc_attr( $div_class ), $style_attr );
		$gb_group_block .= PHP_EOL;

		if ( ! empty( $image_block ) && 'above' === $image_placement ) {
			$gb_group_block .= $image_block . PHP_EOL;
		}

		if ( ! empty( $title_block ) ) {
			$gb_group_block .= $title_block . PHP_EOL;
		}

		if ( ! empty( $meta_block ) ) {
			$gb_group_block .= $meta_block . PHP_EOL;
		}

		if ( ! empty( $image_block ) && 'above' !== $image_placement ) {
			$gb_group_block .= $image_block . PHP_EOL;
		}

		$gb_group_block .= '</div>';
		$gb_group_block .= PHP_EOL;
		$gb_group_block .= '<!-- /wp:group -->';

		return $gb_group_block;
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

		$source_file_url  = str_replace( $uploads['subdir'], '', $uploads['url'] );
		$source_file_url .= '/divi-migration-logs/' . $file_name;

		if ( $csv_generated ) {
			$this->write_log( sprintf( 'Log created successfully - %s', $source_file_url ) );
		} else {
			$this->warning( sprintf( 'Failed to write the logs - %s', $source_file_url ) );
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
