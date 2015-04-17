<?php
/*
 Plugin Name: Quizzlestick
 Plugin URI: http://interconnectit.com
 Description: Quizzlestick wrapper plugin to make creating quizzes within WordPress easy
 Version: 1.0
 Author: interconnect/it
 Author URI: http://interconnectit.com
*/

if ( ! defined( 'QUIZZLESTICK_DIR' ) )
	define( 'QUIZZLESTICK_DIR', dirname( __FILE__ ) );

if ( ! defined( 'QUIZZLESTICK_URL' ) )
	define( 'QUIZZLESTICK_URL', plugins_url( '', __FILE__ ) );

// Load ICIT core
require_once( 'core/core.php' );
// Make sure core knows where core is.
icit_core::instance()->set_core_url( QUIZZLESTICK_URL . '/core/' );

if ( ! class_exists( 'quizzlestick' ) && class_exists( 'icit_plugin' ) ) {

	add_action( 'plugins_loaded', array( 'quizzlestick', 'instance' ) );

	class quizzlestick extends icit_plugin {

		const DOM = __CLASS__;

		const FILE = __FILE__;

		/**
		 * @var string Post type for quizzes
		 */
		public $post_type = 'quiz';

		/**
		 * @var bool
		 */
		public $doing_excerpt = false;

		/**
		 * @var bool override settings page creation
		 */
		public $create_options_page = false;

		/**
		 * @var quizzlestick self
		 */
		protected static $instance = null;

		/**
		 * Create and return an instance of this class
		 *
		 * @return quizzlestick    An Singleton instance of self
		 */
		public static function instance( ) {
			null === self::$instance && self::$instance = new self;
			return self::$instance;
		}

		public function __construct( ) {
			parent::__construct( array(
				'title' => 'Quizzlestick',
				'menu_title' => 'Quizzes',
				'option_group' => 'quizzlestick',
			) );

			// load custom embedder class
			require_once( 'inc/wp-embed.php' );

			// load preview meta class
			//require_once( 'inc/wp-preview-meta.php' );

			// add meta boxes for our quiz
			// add custom fields to quiz pages
			add_action( 'add_meta_boxes', array( $this, 'meta_boxes' ), 10, 2 );
			add_action( 'save_post', array( $this, 'save_post' ), 10, 3 );

			// shortcode
			add_shortcode( 'quizzlestick', array( $this, 'shortcode' ) );

			// add quiz to content for quiz post type
			add_filter( 'the_content', array( $this, 'quiz_content' ), 10 );

			// ajax handler for the stats tracking
			add_action( 'wp_ajax_quizzlestick_api', array( $this, 'api' ) );
			add_action( 'wp_ajax_nopriv_quizzlestick_api', array( $this, 'api' ) );
			
			
			//add_filter( 'quizzlestick-quickfire-delay', array( $this, 'set_quiz_nextdelay' ), 10, 2 );
			
		}

		public function init() {

			// register post type
			$this->quiz_post_type();

			// default filters
			foreach( array( 'quizzlestick_question', 'quizzlestick_answer' ) as $filter ) {
				add_filter( $filter, 'wptexturize'        );
				add_filter( $filter, 'convert_smilies'    );
				add_filter( $filter, 'convert_chars'      );
				//add_filter( $filter, 'wpautop'          );
				add_filter( $filter, 'shortcode_unautop'  );
				// add autoembed to our new filters
				new WP_Custom_Embed( $filter );
			}

			foreach( array( 'quizzlestick_result', 'quizzlestick_graduated_result' ) as $filter ) {
				add_filter( $filter, 'wptexturize'        );
				add_filter( $filter, 'convert_smilies'    );
				add_filter( $filter, 'convert_chars'      );
				add_filter( $filter, 'wpautop'            );
				add_filter( $filter, 'shortcode_unautop'  );
				// add autoembed to our new filters
				new WP_Custom_Embed( $filter );
			}

			// add to stupid defaults
			add_filter( 'embed_cache_oembed_types', function( $types ) {
				$types[] = 'quiz';
				return $types;
			} );

		}

		public function enqueue_scripts() {


			if ( ! is_admin() ) {
				// Allow theme override of css
				if( !current_theme_supports( 'quizzlestick-css') ) {
					
					//wp_register_style( 'quizzlestick', QUIZZLESTICK_URL . '/js/quizzlestick/quizzlestick.css', array() );
					wp_register_style( 'quizzlestick-css', QUIZZLESTICK_URL . '/css/default.css', array() );
					wp_enqueue_style( 'quizzlestick-css' );
				
				}
				
				// But the js we *really* need to keep
				//$prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

				wp_register_script( 'quizzlestick', QUIZZLESTICK_URL . "/js/quizzlestick/quizzlestick.min.js", array( 'jquery' ) );
				wp_register_script( 'quizzlestick-wp', QUIZZLESTICK_URL . "/js/quizzlestick-wp.min.js", array( 'quizzlestick' ) );
				wp_localize_script( 'quizzlestick-wp', 'quizzlestickwp', array(
					'ajaxurl' => admin_url( '/admin-ajax.php' ),
					'nonce' => wp_create_nonce( 'quizzlestick' . AUTH_SALT )
				) );

				// load front end scripts				
				wp_enqueue_script( 'quizzlestick-wp' );

			}

		}

		public function admin_enqueue_scripts( $hook = '' ) {

			wp_enqueue_style( 'quizzlestick-admin', QUIZZLESTICK_URL . '/css/admin.css', array() );
			wp_enqueue_script( 'quizzlestick-admin', QUIZZLESTICK_URL . '/js/admin.js', array( 'jquery' ) );

		}

		/**
		 * Create a new post type for the quiz
		 * 
		 * @return string    Return the updated quiz message
		 */
		public function quiz_post_type() {
			
			register_post_type( 'quiz', array(
				'hierarchical'      => false,
				'public'            => true,
				'show_in_nav_menus' => true,
				'show_ui'           => true,
				'supports'          => array( 'title', 'excerpt', 'thumbnail' ),
				'has_archive'       => true,
				'query_var'         => true,
				'rewrite'           => true,
				'menu_icon' 		=> 'dashicons-welcome-learn-more',
				'labels'            => array(
					'name'                => __( 'Quizzes', $this::DOM ),
					'singular_name'       => __( 'Quiz', $this::DOM ),
					'all_items'           => __( 'Quizzes', $this::DOM ),
					'new_item'            => __( 'New Quiz', $this::DOM ),
					'add_new'             => __( 'Add New', $this::DOM ),
					'add_new_item'        => __( 'Add New Quiz', $this::DOM ),
					'edit_item'           => __( 'Edit Quiz', $this::DOM ),
					'view_item'           => __( 'View Quiz', $this::DOM ),
					'search_items'        => __( 'Search Quizzes', $this::DOM ),
					'not_found'           => __( 'No Quizzes found', $this::DOM ),
					'not_found_in_trash'  => __( 'No Quizzes found in trash', $this::DOM ),
					'parent_item_colon'   => __( 'Parent Quiz', $this::DOM ),
					'menu_name'           => __( 'Quizzes', $this::DOM ),
				),
			) );

			add_filter( 'post_updated_messages', function( $messages ) {
				global $post;

				$permalink = get_permalink( $post );

				$messages['quiz'] = array(
					0 => '', // Unused. Messages start at index 1.
					1 => sprintf( __('Quiz updated. <a target="_blank" href="%s">View Quiz</a>', $this::DOM), esc_url( $permalink ) ),
					2 => __('Custom field updated.', $this::DOM),
					3 => __('Custom field deleted.', $this::DOM),
					4 => __('Quiz updated.', $this::DOM),
					/* translators: %s: date and time of the revision */
					5 => isset($_GET['revision']) ? sprintf( __('Quiz restored to revision from %s', $this::DOM), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
					6 => sprintf( __('Quiz published. <a href="%s">View Quiz</a>', $this::DOM), esc_url( $permalink ) ),
					7 => __('Quiz saved.', $this::DOM),
					8 => sprintf( __('Quiz submitted. <a target="_blank" href="%s">Preview Quiz</a>', $this::DOM), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
					9 => sprintf( __('Quiz scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview Quiz</a>', $this::DOM),
					// translators: Publish box date format, see http://php.net/date
					date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( $permalink ) ),
					10 => sprintf( __('Quiz draft updated. <a target="_blank" href="%s">Preview Quiz</a>', $this::DOM), esc_url( add_query_arg( 'preview', 'true', $permalink ) ) ),
				);

				return $messages;
			} );

		}

		public function extract_meta( $key, $meta ) {
			if ( ! isset( $meta[ $key ] ) )
				return false;
			return maybe_unserialize( $meta[ $key ][ 0 ] );
		}


		/**
		 * Register shortcode
		 * 
		 * @param array $atts	ID of the quiz
		 * @param string $content The post content
		 * 
		 * @return quizzlestick    Returns the quiz found with the ID supplied
		 */
		public function shortcode( $atts, $content = '' ) {
			extract( shortcode_atts( array(
				'id' => false
			), $atts ) );
			
			if ( $id )
				$post = get_post( $id );
			
			if ( !( $post && $post->post_type === 'quiz' ) ) {
				return '<p>No Quiz ID supplied or failed to find quiz.</p>';
			}

			return $this->quiz( $post );
		}


		/**
		 * Create the quiz
		 * 
		 * @param WP_Post $post Quiz post type
		 * 
		 * @return array    Returns the quiz with processed questions and answers
		 */
		public function quiz( WP_Post $post ) {

			// prevent content filtering anywhere in the function
			remove_filter( 'the_content', array( $this, 'quiz_content' ), 10 );

			// fix alt attribute escaping
			add_filter( 'wp_get_attachment_image_attributes', array( $this, 'texturise_atts' ), 10, 2 );

			// main quiz config
			$config = array(
				'id' => 'quiz-' . $post->ID,
				'title' => get_the_title( $post->ID )
			);
			
			$result_title = get_post_meta( $post->ID, 'result_title', true );

			// add template modifications for the api data
			$config[ 'templates' ] = array();
			$config[ 'templates' ][ 'result' ] = '
				<div class="quizzlestick-result-text">
					<h3>' . (!empty($result_title) ? $result_title : __('Results')) . '</h3>
					{{helpers.getresult}}
				</div>
				{{templates.share}}';

			// fetch necessary elements to create our config
			$meta = get_post_meta( $post->ID );

			// handle type mapping
			$type = $this->extract_meta( 'type', $meta );
			$config[ 'type' ] = $type;
			if ( in_array( $type, array( /*'quickfire',*/ 'single' ) ) ) {
				$config[ 'type' ] = 'single';
			}

			// additional config
			switch( $type ) {
				//case 'quickfire':
				//	$config[ 'classname' ] = 'quizzlestick-quickfire';
				//	$config[ 'nextdelay' ] = apply_filters( 'quizzlestick-quickfire-delay', 500, $post->ID );
				//	$config[ 'templates' ][ 'answer' ] = '
				//		<li class="quizzlestick-answer">
				//			<a href="#">
				//				{{answer}}
				//			</a>
				//		</li>';
				//	$config[ 'templates' ][ 'question' ] = '
				//		<li class="quizzlestick-question">
				//			{{question}}
				//			<ul class="quizzlestick-answers">
				//				{{answers}}
				//			</ul>
				//			{{templates.toolbar}}
				//			<div class="quizzlestick-result quizzlestick-result-question quizzlestick-hidden"></div>
				//		</li>';
				//	break;
				case 'single':

					break;
				case 'which':

					break;
				//case 'poll':
				//	$config[ 'nextdelay' ] = apply_filters( 'quizzlestick-quickfire-delay', 750, $post->ID );
				//	$config[ 'templates' ][ 'scaffold' ] = '
				//		<div class="quizzlestick-description">
				//			{{templates.description}}
				//		</div>
				//		<div class="quizzlestick-timer">
				//			{{templates.timer}}
				//		</div>
				//		<div class="quizzlestick-start-screen">
				//			{{templates.timerstart}}
				//		</div>
				//		<ol class="quizzlestick-questions">
				//			{{templates.questions}}
				//		</ol>
				//		{{templates.toolbar}}
				//		<div class="quizzlestick-result quizzlestick-result-final quizzlestick-hidden">
				//			{{templates.result}}
				//		</div>';
				//	$config[ 'templates' ][ 'answer' ] = '
				//		<li class="quizzlestick-answer">
				//			<a href="#">
				//				{{answer}}
				//			</a>
				//			<span class="quizzlestick-answer-total">{{total}}</span>
				//		</li>';
				//	break;
			}

			// get general config
			$config[ 'description' ] = apply_filters( 'the_content', $post->post_excerpt );
			//$config[ 'timelimit' ] 	 = intval( $this->extract_meta( 'timelimit', $meta ) );

			// process questions and answers
			$questions = array();
			$meta_questions = $this->extract_meta( 'questions', $meta );
			if ( $meta_questions ) {
				foreach( $meta_questions as $i => &$question ) {

					foreach( $question[ 'answers' ] as $j => &$answer ) {

						if ( ! empty( $answer[ 'answer' ] ) ) {
							$answer[ 'answer' ] = '<div class="quizzlestick-answer-text">' . $answer[ 'answer' ] . '</div>';
							$answer_has_text = true;
						} else {
							$answer_has_text = false;
						}
							

						if ( isset( $answer[ 'image' ] ) && $answer[ 'image' ] ) {
							$answer_has_image = true;
							$answer_image_size = apply_filters( 'answer_image_size', 'large', $answer, $post );
							if( $answer_has_text ) {
								$class = array( 'class' => 'quizzlestick-answer-image' );
							} else {
								$class = array( 'class' => 'quizzlestick-answer-image no-text' );
							}
							
							$answer_image = wp_get_attachment_image( $answer[ 'image' ], $answer_image_size, false, $class );
							$answer[ 'answer' ] .= '<div class="quizzlestick-answer-image-wrap">' . $answer_image . '</div>';
						} else {
							$answer_has_image = false;
						}

						$total = $this->extract_meta( "total_{$i}_{$j}", $meta );
						if ( $total )
							$answer[ 'total' ] = $total;

						//if ( $type === 'poll' )
						//	$answer[ 'correct' ] = false;

						$answer[ 'answer' ] = apply_filters( 'quizzlestick_answer', $answer[ 'answer' ], $answer, $config, $post );
					}

					if ( ! empty( $question[ 'question' ] ) )
						$question[ 'question' ] = '<div class="quizzlestick-question-text">' . $question[ 'question' ] . '</div>';

					if ( isset( $question[ 'image' ] ) && $question[ 'image' ] ) {
						$question_image_size = apply_filters( 'question_image_size', 'large', $question, $post );
						$question_image = wp_get_attachment_image( $question[ 'image' ], $question_image_size, false, array( 'class' => 'quizzlestick-question-image' ) );
						$question[ 'question' ] .= '<div class="quizzlestick-question-image-wrap">' . $question_image . '</div>';
					}

					// edit question result template?
					//if ( $type === 'poll' ) {
					//
					//}

					// filter question
					$question[ 'question' ] = apply_filters( 'quizzlestick_question',  $question[ 'question' ], $question, $config, $post );

					$questions[ $i ] = $question;

				}
				$config[ 'questions' ] = $questions;
			}

			// results
			$meta_result  = apply_filters( 'quizzlestick_result', $this->extract_meta( 'result', $meta ), $config, $post );

			$result_description = apply_filters( 'quizzlestick_result', $this->extract_meta( 'result', $meta ), $config, $post );
			$result_introduction = apply_filters( 'quizzlestick_result_introduction', $this->extract_meta( 'result_introduction', $meta ), $config, $post );
			$result_heading = apply_filters( 'quizzlestick_result_heading', $this->extract_meta( 'result_heading', $meta ), $config, $post );
			
			$result_introduction = !empty( $result_introduction ) ? $result_introduction : __('Here are your results');
			$result_heading = !empty( $result_heading ) ? $result_heading : __('You scored ...');
			$result_description = !empty( $result_description ) ? $result_description : __('Congratulations on your score of <strong>{{state.points}}</strong> out of <strong>{{state.maxpoints}}</strong>!');
			
			// Graduated results
			$meta_results = $this->extract_meta( 'results', $meta );
			if ( is_array( $meta_results ) && ! empty( $meta_results ) ) {
				
				$results = array();
				
				foreach( $meta_results as $result ) {
					$template = '';
					if ( $result[ 'image' ] ) {
						$result_image_size = apply_filters( 'result_image_size', 'large', $result, $post );
						$result_image = wp_get_attachment_image( $result[ 'image' ], $result_image_size, false, array( 'class' => 'quizzlestick-result-image' ) );
						$result[ 'result' ] = '<div class="quizzlestick-result-image-wrap">' . $result_image . '</div> ' . $result[ 'result' ];
					}
					$result[ 'result' ] = apply_filters( 'quizzlestick_graduated_result', $result[ 'result' ], $result, $meta_result, $config, $post );
					
					if ( is_string( $result[ 'result' ] ) && ! empty( $result[ 'result' ] ) ) {
						// Add in parts here
						if( !empty($result['short']) ) {
							$template = '<div class="quizzlestick-result-intro">' . $result['short'] . '</div> ';
						} else {
							if( !empty( $result_introduction ) ) {
								$template = '<div class="quizzlestick-result-intro">' . $result_introduction . '</div> ';
							} else {
								$template = "";
							}
						}
						// We only want the score if it's not a Which is quiz
						if( $type != 'which' ) {
							$template .= '<div class="quizzlestick-result-score"><span class="heading">' . $result_heading .  '</span><span class="numeric-results"><span class="score">{{state.points}}</span>/<span class="total">{{state.maxpoints}}</span></span></div>';
						}
						
						if( !empty( $result[ 'result' ] ) ) {
							$template .= '<div class="quizzlestick-result-description">' . $result[ 'result' ] . '</div>';
						} else {
							if( !empty( $result_description ) ) {
								$template .= '<div class="quizzlestick-result-description">' . $result_description . '</div>';
							}
						}
						
					} else {
						// This should never be hit due to defaults
						$template = $meta_result;
					}
					
					$result[ 'template' ] = $template;
					unset( $result[ 'result' ] );
					$results[] = $result;
				}
				
				$config[ 'results' ] 	= $results;
				
			} elseif ( is_string( $result_description ) ) {
				
				// Add in the parts here
				if( !empty( $result_introduction ) ) {
					$template = '<div class="quizzlestick-result-intro">' . $result_introduction . '</div> ';
				} else {
					$template = "";
				}
				if( $type != 'which' /*&& $type != 'poll'*/ ) {
					$template .= '<div class="quizzlestick-result-score"><span class="heading">' . $result_heading .  '</span><span class="numeric-results"><span class="score">{{state.points}}</span>/<span class="total">{{state.maxpoints}}</span></span></div>';
				}
				
				//if( $type == 'poll' ) {
				//	$pollquestions = get_post_meta( $post->ID, 'questions', true );
				//	if( isset( $pollquestions[0]['answers'] ) && !empty( $pollquestions[0]['answers'] ) ) {
				//		$pollanswers = array();
				//		foreach( $pollquestions[0]['answers'] as $key => $pollanswer ) {
				//			$pollresult = get_post_meta( $post->ID, 'total_0_' . $key, true );
				//			$pollresults[$key] = ( !empty($pollresult) ) ? $pollresult : 0;
				//		}
				//		
				//		$totalresults = array_sum( $pollresults );
				//		
				//		$template .= '<div class="quizzlestick-result-score quizzlestick-poll-results" data-total="' . $totalresults . '">';
				//		if( $totalresults == 0) $totalresults = 1; // removes divide by zero below
				//		// And now loop again
				//		foreach( $pollquestions[0]['answers'] as $key => $pollanswer ) {
				//			
				//			$template .= '<div class="quizzlestick-poll-result-answer" id="quizzlestick-poll-result-answer-' . $key . '">';
				//			$template .= '<span class="answer-title">' . strip_tags( $pollanswer['answer'] ) . '</span>';
				//			$template .= '<div class="answer-bar-holder" id="answer-bar-holder-' . $key . '">';
				//				$template .= '<div class="answer-bar" data-total="' . $pollresults[$key] . '" data-percentagetotal="' . (int)( (100 / $totalresults) * (int)$pollresults[$key] ) . '" style="width: ' . (int)( (100 / $totalresults) * (int)$pollresults[$key] ) . '%;">';
				//				$template .= '</div>';
				//				$template .= '<span class="answer-value" id="answer-value-' . $key . '">' . (int)( (100 / $totalresults) * (int)$pollresults[$key] ) . '%</span>';
				//			$template .= '</div>';
				//			$template .= '</div>';
				//			
				//		}
				//		$template .= '</div>';
				//		
				//	}
				//}
				
				if( !empty( $result_description ) ) {
					$template .= '<div class="quizzlestick-result-description">' . $result_description . '</div>';
				}
				
				$config[ 'results' ] 	= $template;
				
			}

			// templates
			if ( $correct   = $this->extract_meta( 'correct', $meta ) ) {
				$config[ 'templates' ][ 'correct' ] 	= '<div class="quizzlestick-response-correct">' . $correct . '</div>';
			}
			
			if ( $incorrect = $this->extract_meta( 'incorrect', $meta ) ) {
				$config[ 'templates' ][ 'incorrect' ] 	= '<div class="quizzlestick-response-incorrect">' . $incorrect . '</div>';
			}

			// allow modification for share template etc...
			$config = apply_filters( 'quizzlestick_config', $config, $post );

			// add filter back
			add_filter( 'the_content', array( $this, 'quiz_content' ), 10 );

			// remove filter
			remove_filter( 'wp_get_attachment_image_attributes', array( $this, 'texturise_atts' ), 10 );

			$output = '
				<div id="quizzlestick-' . $post->ID . '"></div>
				<script>(function($){$("#quizzlestick-' . $post->ID . '").quizzlestick(' . json_encode( $config, JSON_UNESCAPED_SLASHES ). ')})(jQuery)</script>';
				
			return $output;
		}


		public function texturise_atts( $attr, $attachment ) {
			$attr[ 'alt' ] = wptexturize( $attr[ 'alt' ] );
			$attr[ 'alt' ] = convert_chars( $attr[ 'alt' ] );
			return $attr;
		}


		/**
		 * Populates the content of a quiz post with the quiz HTML tag
		 *
		 * @param string $content The post content
		 *
		 * @return string
		 */
		public function quiz_content( $content ) {
			global $post, $wp_query;

			if ( $content === '' && ! is_admin() && $post->post_type === $this->post_type )
				$content = $this->quiz( $post );

			return $content;
		}


		public function meta_boxes( $post_type, WP_Post $post ) {
			
			$type = get_post_meta( $post->ID, 'type', true );

			// settings
			add_meta_box( 'settings', __( 'Settings' ), array( $this, 'meta_box_settings' ), $this->post_type, 'normal', 'default' );

			// questions
			add_meta_box( 'questions', __( 'Questions' ), array( $this, 'meta_box_questions' ), $this->post_type, 'normal', 'default' );

			// results
			add_meta_box( 'results', __( 'Results' ), array( $this, 'meta_box_results' ), $this->post_type, 'normal', 'default' );
			
			// Add in our poll results metabox
			//if( $type == 'poll' ) {
			//	add_meta_box( 'pollresults', __( 'Poll Results' ), array( $this, 'meta_box_poll_results' ), $this->post_type, 'normal', 'default' );
			//}
			
			// embed box
			add_meta_box( 'embed', __( 'Embed' ), array( $this, 'meta_box_embed' ), $this->post_type, 'side', 'default' );

			// remove excerpt box
			remove_meta_box( 'postexcerpt', $this->post_type, 'normal' );
			
			// remove featured image box
			remove_meta_box( 'postimagediv', $this->post_type, 'side' );
			

		}

		public function meta_box_textarea( WP_Post $post, $meta_box ) {
			icit_fields::field_textarea( array(
				'name' => 'description',
				'value' => get_post_meta( $post->ID, $meta_box[ 'args' ][ 'name' ], true ),
				'description' =>  $meta_box[ 'args' ][ 'description' ],
				'tiny_mce' => true,
				'edit_args' => array(
					'media_buttons' => true,
					'teeny' => true,
					'textarea_rows' => 2,
					'wpautop' => true
				)
			) );
		}

		public function meta_box_settings( WP_Post $post, $meta_box ) {

			$type = get_post_meta( $post->ID, 'type', true );

			echo '
			<table class="form-table">
				<tbody>';

					echo '
					<tr class="quiz-field quiz-field-radio">
						<th><label>' . __( 'Type' ) . '</label></th>
						<td>';

					icit_fields::field_radio( array(
						'name' => 'type',
						'value' => $type,
						'options' => apply_filters( 'quizzlestick_question_types', array(
							//'quickfire' => __( 'Quickfire <span class="description"> quizzes submit answers immediately on click and automatically progress to the next question if available.</span><br /><br />' ),
							'single' => __( 'Single Answer <span class="description"> quizzes submit answers immediately on click and show the question result and a \'next\' button.</span><br /><br />' ),
							//'multi' => __( 'Multiple Answers <span class="description"> quizzes can have more than one correct answer per question which all have to be selected to get the question right.</span><br /><br />' ),
							'which' => __( 'Which are you / is it? <span class="description"> quizzes have no incorrect answers but must use graduated results based on the points scored.</span><br /><br />' ),
							//'poll' => __( 'Poll <span class="description"> are a single questions the results of which are shown immediately after.</span>' )
							) ),
						'description' => __( 'Choose the type of quiz' ),
						'default' => 'single'
					) );

					//<span class="instruction-quickfire">Quickfire quizzes submit answers immediately on click and automatically progress to the next question if available.</span><br />
					//		<span class="instruction-single">Single answer quizzes submit answers immediately on click and show the question result and a \'next\' button.</span><br />
					//		<span class="instruction-multi">Multiple answer quizzes can have more than one correct answer per question which all have to be selected to get the question right.</span><br />
					//		<span class="instruction-which">Which are you / is it quizzes have no incorrect answers but must use graduated results based on the points scored.</span><br />
					//		<span class="instruction-poll">Polls are a single questions the results of which are shown immediately after.</span>

					echo '
						</td>
					</tr>';

					echo '
					<tr class="quiz-field quiz-field-radio">
						<th><label>' . __( 'Description' ) . '</label></th>
						<td>';

					icit_fields::field_textarea( array(
						'name' => 'excerpt',
						'value' => html_entity_decode( $post->post_excerpt ),
						'description' => '<p>' . __( 'If the quiz needs an introduction or instructions add them here' ) . '</p>',
						'tiny_mce' => true,
						'edit_args' => array(
							'media_buttons' => false,
							'teeny' => true,
							'textarea_rows' => 2,
							'wpautop' => true
						)
					) );

					echo '
						</td>
					</tr>';

					if( !apply_filters( 'quizzlestick_enable_timelime', false ) ) {
						echo '<input type="hidden" name="timelimit" value="0" />';
					} //else {
						//if ( $type !== 'poll' ) {
							//echo '
							//<tr class="quiz-field quiz-field-number">
							//	<th><label>' . __( 'Time limit (seconds)' ) . '</label></th>
							//	<td>';
							//
							//icit_fields::field_numeric( array(
							//	'name' => 'timelimit',
							//	'value' => get_post_meta( $post->ID, 'timelimit', true ),
							//	'description' => __( 'The time limit for this quiz in seconds. Zero means there is no time limit.' ),
							//	'default' => 0,
							//	'min' => 0
							//) );
							//
							//echo '
							//	</td>
							//</tr>';
						//}
					//}
					
					//if ( $type == 'quickfire' ) {
					//	echo '
					//	<tr class="quiz-field quiz-field-number">
					//		<th><label>' . __( 'Delay before next question (miliseconds)' ) . '</label></th>
					//		<td>';
					//
					//	icit_fields::field_numeric( array(
					//		'name' => 'nextdelay',
					//		'value' => get_post_meta( $post->ID, 'nextdelay', true ),
					//		'description' => __( 'The delay before moving to the next question after answering default is 500 miliseconds.' ),
					//		'default' => 500,
					//		'min' => 0
					//	) );
					//
					//	echo '
					//		</td>
					//	</tr>';
					//}

					//echo '
					//<tr class="quiz-field quiz-field-number">
					//	<th><label>' . __( 'Next question delay (seconds)' ) . '</label></th>
					//	<td>';
					//
					//icit_fields::field_numeric( array(
					//	'name' => 'nextdelay',
					//	'value' => get_post_meta( $post->ID, 'nextdelay', true ),
					//	'description' => __( 'If set then the quiz will automatically advance after a question has been answered. You can set this to a decimal value eg 0.5' ),
					//	'default' => 0,
					//	'min' => 0
					//) );
					//
					//echo '
					//	</td>
					//</tr>';

					if ( ! in_array( $type, array( 'which'/*, 'poll'*/ ) ) ) {

						echo '
						<tr class="quiz-field quiz-field-number">
							<th><label>' . __( 'Text to show for correct answer' ) . '</label></th>
							<td>';

						$correct = get_post_meta( $post->ID, 'correct', true );
						icit_fields::field_text( array(
							'name' => 'correct',
							'value' => $correct ? $correct : __( 'Correct!' ),
							'default' => __( 'Correct!' )
						) );

						echo '
							</td>
						</tr>';

						echo '
						<tr class="quiz-field quiz-field-number">
							<th><label>' . __( 'Text to show for incorrect answer' ) . '</label></th>
							<td>';

						$incorrect = get_post_meta( $post->ID, 'incorrect', true );
						icit_fields::field_text( array(
							'name' => 'incorrect',
							'value' => $incorrect ? $incorrect : __( 'Wrong!' ),
							'default' => __( 'Wrong!' )
						) );

						echo '
							</td>
						</tr>';

					}

					do_action( 'quiz_settings_metabox', $post, $meta_box );

			echo '
				</tbody>
			</table>';

			echo '<p><input type="submit" class="button button-primary" name="updatesettings" value="' . __( 'Update settings' ) . '" /></p>';

		}

		public function meta_box_questions( WP_Post $post, $meta_box ) {

			$quiz_type = get_post_meta( $post->ID, 'type', true );

			$questions = get_post_meta( $post->ID, 'questions', true );

			if ( ! $questions )
				$questions = array(
					array()
				);

			// add empty one for js
			$questions[ '__i__' ] = array();
			$question_num = 0;
			$qid = $aid = 0;

			foreach( $questions as $i => $question ) {

				if ( $i !== '__i__' )
					$qid = $i;

				// only 1 question for polls
				//if ( $quiz_type === 'poll' && $i > 0 )
				//	break;

				$question = wp_parse_args( $question, array(
					'question' => '',
					'image' => false,
					'result' => '',
					'resultcorrect' => '',
					'resultincorrect' => '',
					'answers' => array( array() ),
					'total' => 0
				) );

				echo '
				<div class="postbox question-field"' . ( $i === '__i__' ? ' style="display:none" id="question-template"' : '' ) . '>
					<table class="form-table">
						<tbody>';

					// question content
					// input & or image
					echo '
							<tr>
								<th class="quiz-field"><label>' . __( 'Question' ) . ' ' . (++$question_num) . '</label><br /> <span class="description">' . __( 'You can also use an embeddable URL' ) . '</span></th>
								<td>';
					icit_fields::field_text( array(
						'name' => "questions[$i][question]",
						'value' => $question[ 'question' ],
						'class' => 'widefat'
					) );
					icit_fields::field_image( array(
						'name' => "questions[$i][image]",
						'value' => $question[ 'image' ],
						'size' => apply_filters( 'question_image_size', 'large', $question, $post ),
						'post_parent' => $post->ID
					) );
					echo '
								</td>
							</tr>';

					if ( ! in_array( $quiz_type, array( 'which'/*, 'poll'*/ ) ) ) {

					echo '
							<tr>
								<th><label>' . __( 'Result text (optional)' ) . '</label></th>
								<td>';

						echo '<div class="question-result">';
							echo '<div class="question-result-default">';
							icit_fields::field_textarea( array(
								'name' => "questions_{$i}_result",
								'value' => $question[ 'result' ],
								'tiny_mce' => $i === '__i__' ? false : true,
								'edit_args' => array(
									'media_buttons' => false,
									'teeny' => true,
									'textarea_rows' => 2,
									'wpautop' => true
								)
							) );
							echo '</div>';
							echo '<div class="question-result-correct hide-if-js"><label>' . __( 'Result text if correct (optional)' ) . '</label>';
							icit_fields::field_textarea( array(
								'name' => "questions_{$i}_resultcorrect",
								'value' => $question[ 'resultcorrect' ],
								'tiny_mce' => $i === '__i__' ? false : true,
								'edit_args' => array(
									'media_buttons' => false,
									'teeny' => true,
									'textarea_rows' => 2,
									'wpautop' => true
								)
							) );
							echo '</div>';
							echo '<div class="question-result-incorrect hide-if-js"><label>' . __( 'Result text if incorrect (optional)' ) . '</label>';
							icit_fields::field_textarea( array(
								'name' => "questions_{$i}_resultincorrect",
								'value' => $question[ 'resultincorrect' ],
								'tiny_mce' => $i === '__i__' ? false : true,
								'edit_args' => array(
									'media_buttons' => false,
									'teeny' => true,
									'textarea_rows' => 2,
									'wpautop' => true
								)
							) );
							echo '</div>';
						echo '</div>';

					echo '
								</td>
							</tr>';

					}

					echo '
							<tr class="question-answers">
								<th><label>Answers</label></th>
								<td>';

					// add empty answer template for js
					if ( $i === '__i__' )
						$question[ 'answers' ] = array( '__j__' => array() );

					foreach( $question[ 'answers' ] as $j => $answer ) {

						if ( $j !== '__j__' )
							$aid = $j;

						$answer = wp_parse_args( $answer, array(
							'answer' => '',
							'image' => false,
							'points' => 0,
							'correct' => false,
							'result' => '',
							'resultcorrect' => '',
							'resultincorrect' => '',
							'total' => 0
						) );

						echo '
						<div class="postbox answer-field"' . ( $j === '__j__' ? ' id="answer-template"' : '' ) . '>
							<table class="form-table">
								<tbody>';

							// answer content
							// input & or image
							echo '
									<tr>
										<th><label>' . __( 'Answer' ) . '</label><br /> <span class="description">' . __( 'You can also use an embeddable URL' ) . '</span></th>
										<td>';
							icit_fields::field_text( array(
								'name' => "questions[$i][answers][$j][answer]",
								'value' => $answer[ 'answer' ],
								'class' => 'widefat'
							) );
							
							do_action( 'quizzlestick_answers_preimage', $i, $j, $question, "questions[$i][answers][$j][answer]", $post );
							
							icit_fields::field_image( array(
								'name' => "questions[$i][answers][$j][image]",
								'value' => $answer[ 'image' ],
								'size' => apply_filters( 'answer_image_size', 'large', $answer, $post ),
								'post_parent' => $post->ID
							) );
							echo '
										</td>
									</tr>';
							
							do_action( 'quizzlestick_answers_postimage', $i, $j, $question, "questions[$i][answers][$j][answer]", $post );
									
							//if( $quiz_type == 'poll' ) {
							//	
							//	echo '
							//			<tr>
							//				<th><label>' . __( 'Long Answer' ) . '</label><br /> <span class="description">' . __( 'For a longer, more complete answer' ) . '</span></th>
							//				<td>';
							//
							//				icit_fields::field_textarea( array(
							//					'name' => "answer_{$j}_answer_description",
							//					'value' => $question[ 'answers' ][$j]['description'],
							//					'tiny_mce' => $i !== '__i__',
							//					'edit_args' => array(
							//						'media_buttons' => false,
							//						'teeny' => true,
							//						'textarea_rows' => 4,
							//						'wpautop' => true
							//					)
							//				) );
							//
							//	echo '
							//				</td>
							//			</tr>';
							//	
							//} else {
								if ( $quiz_type !== 'which' ) {
									echo '
										<tr>
											<th><label>' . __( 'Correct answer?' ) . '</label></th>
											<td>';
									icit_fields::field_boolean( array(
										'name' => "questions[$i][answers][$j][correct]",
										'value' => $answer[ 'correct' ]
									) );
									echo '
											</td>
										</tr>';
								}

								echo '
									<tr>
										<th><label>' . __( 'Points' ) . '</label></th>
										<td>';
								icit_fields::field_numeric( array(
									'name' => "questions[$i][answers][$j][points]",
									'min' => 0,
									'value' => $answer[ 'points' ]
								) );
								echo '
										</td>
									</tr>';
							//}

							echo '
								</tbody>
							</table>';

							if ( $j === '__j__' || $j > 0 )
								echo '<p><input type="submit" name="deleteanswer[' . $i . '][' . $j . ']" class="button deletion" value="' . __( 'Delete answer' ) . '" /></p>';

						echo '
						</div>';

					}

					echo '<p><input type="submit" name="addanswer[' . $i . ']" class="button" value="' . __( 'Add another answer' ) . '" data-aid="' . $aid . '" data-qid="' . ( $i === '__i__' ? $i : $qid ) . '" /></p>';

					echo '
								</td>
							</tr>
						</tbody>
					</table>';

				if ( $i === '__i__' || $i > 0 )
					echo '<p><input type="submit" name="deletequestion[' . $i . ']" class="button deletion" value="' . __( 'Delete question' ) . '" /></p>';

				echo '
				</div>';

			}

			//if ( $quiz_type !== 'poll' )
				echo '<p><input type="submit" name="addquestion" class="button" value="' . __( 'Add another question' ) . '" data-qid="' . $qid . '" /></p>';

		}
		
		public function meta_box_poll_results( WP_Post $post, $meta_box ) {
			
			$quiz_type = get_post_meta( $post->ID, 'type', true );
		
			echo '
			<table class="form-table">
				<tbody>';
				
			$questions = get_post_meta( $post->ID, 'questions', true );
		
			if ( empty( $questions ) ) {
				_e( 'Coming Soon' );
			} else {
				?>
				<ul>
				<?php
				foreach( $questions as $q => $question ) {
					?>
					<li><strong><?php _e( esc_html( $question[ 'question' ] ) ); ?></strong><br/><br/>
					<?php 
					if( !empty( $question[ 'answers' ] ) ) {
						?>
						<ol>
						<?php
						$total = 0;
						foreach( $question[ 'answers' ] as $a => $answer ) {
							$question[ 'answers' ][$a]['result'] = get_post_meta( $post->ID, 'total_' . $q . '_' . $a , true );
							$total += (int) $question[ 'answers' ][$a]['result'];
					
						}
						foreach( $question[ 'answers' ] as $a => $answer ) {
							$votes = get_post_meta( $post->ID, 'total_' . $q . '_' . $a , true );
							?>
							<li><?php 
								echo __( esc_html( $answer[ 'answer' ] ) ) . ' - ';
								if( $total < 1 ) {
									echo sprintf( '<strong>%d</strong> Vote(s) which is roughly <strong>%d%%</strong> ', 0, 0 );
								} else {
									echo sprintf( '<strong>%d</strong> Vote(s) which is roughly <strong>%.2f%%</strong> ', $answer[ 'result' ], (100 / $total) * $answer[ 'result' ] ); 
								}
							?>
							</li>
							<?php
						}
						?>
						</ol>
						<?php
					}
					?>
					</li>
					<?php
				}
				?>
				</ul>
				<?php
			}
				
			echo '
				</tbody>
			</table>';
			
		}

		public function meta_box_results( WP_Post $post, $meta_box ) {

			$quiz_type = get_post_meta( $post->ID, 'type', true );

			echo '
			<table class="form-table">
				<tbody>';

			// results if not a poll type
			//if ( $quiz_type !== 'poll' ) {
				
				$result_title = get_post_meta( $post->ID, 'result_title', true );
				echo '
						<tr>
							<th><label>' . __( 'Results title' ) . '</label><br/><span class="description">' . __( 'This is the text shown at the very top of the results panel' ) . '</span></th>
							<td>';
							icit_fields::field_text( array(
								'name' => "result_title",
								'value' => $result_title ? $result_title : 'Results',
								'class' => 'widefat',
								'default' => 'Results'
							) );
				echo '
							</td>
						</tr>';

				// result text or intro result text
				echo '
						<tr class="quiz-result-default">
							<th><label>' . __( 'Results introduction (default)' ) . '</label><br/><span class="description">' . __( 'This is shown below the title and can be overridden by any Introduction text set in graduated results (below)' ) . '</span></th>
							<td>';
							$result_introduction = get_post_meta( $post->ID, 'result_introduction', true );
							icit_fields::field_text( array(
								'name' => 'result_introduction',
								'value' => $result_introduction ? $result_introduction : '<p>' . __( 'Here are your results' ) . '</p>',
								'class' => 'widefat',
								'default' => 'Here are your results'
							) );
				echo '
							</td>
						</tr>';
				
				if( $quiz_type !== 'which' ) {
					// You scored text
					$result_heading = get_post_meta( $post->ID, 'result_heading', true );
					echo '
							<tr>
								<th><label>' . __( 'Score heading' ) . '</label><br/><span class="description">' . __( 'This is the text displayed directly above the quiz score' ) . '</span></th>
								<td>';
								icit_fields::field_text( array(
									'name' => "result_heading",
									'value' => $result_heading ? $result_heading : 'You scored ...',
									'class' => 'widefat',
									'default' => 'You scored ...'
								) );
					echo '
								</td>
							</tr>';
				}
			
				// Default base text
				echo '
						<tr class="quiz-result-default">
							<th><label>' . __( 'Results text (default)' ) . '</label><br/><span class="description">' . __( 'This is displayed underneath the score and can be overridden by any results text set in graduated results (below)' ) . '</span></th>
							<td>';
							$result = get_post_meta( $post->ID, 'result', true );
							icit_fields::field_textarea( array(
								'name' => 'result',
								'value' => $result ? $result : '<p>' . __( 'Congratulations on your score of <strong>{{state.points}}</strong> out of <strong>{{state.maxpoints}}</strong>!' ) . '</p>',
								'tiny_mce' => true,
								'edit_args' => array(
									'media_buttons' => true,
									'teeny' => true,
									'textarea_rows' => 2,
									'wpautop' => true
								),
								'default' => '<p>Congratulations on your score of <strong>{{state.points}}</strong> out of <strong>{{state.maxpoints}}</strong>!</p>'
							) );
				echo '
							</td>
						</tr>';


				$results = get_post_meta( $post->ID, 'results', true );

				// default results array
				if ( ! $results ) {
					$results = array();
					if ( $quiz_type === 'which' ) {
						$results = array(
							array(
								'points' => 0,
								'image' => false,
								'result' => '<p>You scored <strong>{{state.points}}</strong> out of <strong>{{state.maxpoints}}</strong>!</p>',
								'short' => ''
							),
							array(
								'points' => 1,
								'image' => false,
								'result' => '<p>You scored <strong>{{state.points}}</strong> out of <strong>{{state.maxpoints}}</strong>!</p>',
								'short' => ''
							)
						);
					}
				}

				// placeholder for js
				$results[ '__i__' ] = array();

				echo '
						<tr>
							<th>
								<h2>' . __( 'Graduated Results' ) . '</h2>
								<div class="description">
									<p class="description">' . __( 'You can show custom results based on the number of points reached.' ) . '</p>
								</div>
							</th>
							<td>';

				$prev_points = 0;
				foreach( $results as $i => $result ) {

					$min = ($i > 0 ? $prev_points+1 : $prev_points);

					$result = wp_parse_args( $result, array(
						'points' => $min,
						'image' => false,
						'result' => '<p>Congratulations on your score of <strong>{{state.points}}</strong> out of <strong>{{state.maxpoints}}</strong>!</p>',
						'short' => ''
					) );

					// make sure the points make sense
					$result[ 'points' ] = max( $min, $result[ 'points' ] );

					echo '
								<div class="multiple-results result-field postbox"' . ( $i === '__i__' ? ' style="display:none" id="result-template"' : '' ) . '>
									<table class="form-table">
										<tbody>';

					echo '
											<tr class="quiz-result quiz-result-points">
												<th>' . __( 'Points' ) . '</th>
												<td>From <strong>' . $min . '</strong> to ';
					icit_fields::field_numeric( array(
						'name' => "results[$i][points]",
						'value' => $result[ 'points' ],
						'min' => $min
					) );
					echo '
												</td>
											</tr>';
				
					echo '
											<tr>
												<th><label>' . __( 'Results title' ) . '</label><br/><span class="description">' . __( 'The result title (overrides default text)' ) . '</span></th>
												<td>';
					icit_fields::field_text( array(
						'name' => "results[$i][short]",
						'value' => $result[ 'short' ],
						'class' => 'widefat'
					) );
					echo '
												</td>
											</tr>';
				
					echo '
											<tr class="quiz-result">
												<th>' . __( 'Image' ) . '</th>
												<td>';
					icit_fields::field_image( array(
						'name' => "results[$i][image]",
						'value' => $result[ 'image' ],
						'size' => 'large'
					) );
					echo '
												</td>
											</tr>';

					echo '
											<tr>
												<th><label>' . __( 'Results text' ) . '</label><br/><span class="description">' . __( 'Overrides default text' ) . '</span></th>
												<td>';
					icit_fields::field_textarea( array(
						'name' => "results_{$i}_result",
						'value' => $result[ 'result' ],
						'tiny_mce' => $i !== '__i__' ? true : false,
						'edit_args' => array(
							'media_buttons' => false,
							'teeny' => true,
							'textarea_rows' => 4,
							'wpautop' => true
						)
					) );
					echo '
												</td>
											</tr>';
					

					echo '
										</tbody>
									</table>';

					if ( ( $i !== 0 && $quiz_type === 'which' ) || $i === '__i__' || $quiz_type !== 'which' )
						echo '<p><input type="submit" name="deleteresult[' . $i . ']" class="button deletion" value="' . __( 'Delete Result' ) . '" /></p>';

					echo '
								</div>';

					$prev_points = $result[ 'points' ];
				}


				echo '
								<p><input type="submit" class="button" name="addresult" value="' . __( 'Add new result' ) . '" /></p>';
				echo '
							</td>
						</tr>';

			// poll results
			//} else {
			//
			//	$result_title = get_post_meta( $post->ID, 'result_title', true );
			//	echo '
			//			<tr>
			//				<th><label>' . __( 'Results title' ) . '</label><br/><span class="description">' . __( 'This is the text shown at the very top of the results panel' ) . '</span></th>
			//				<td>';
			//				icit_fields::field_text( array(
			//					'name' => "result_title",
			//					'value' => $result_title ? $result_title : 'Thank you',
			//					'class' => 'widefat',
			//					'default' => 'Thank you'
			//				) );
			//	echo '
			//				</td>
			//			</tr>';
			//
			//	// result text or intro result text
			//	echo '
			//			<tr class="quiz-result-default">
			//				<th><label>' . __( 'Results introduction (default)' ) . '</label><br/><span class="description">' . __( 'This is shown below the title' ) . '</span></th>
			//				<td>';
			//				$result_introduction = get_post_meta( $post->ID, 'result_introduction', true );
			//				icit_fields::field_textarea( array(
			//					'name' => 'result_introduction',
			//					'value' => $result_introduction ? $result_introduction : '<p>' . __( 'Here are the current results' ) . '</p>',
			//					'tiny_mce' => false,
			//					'edit_args' => array(
			//						'media_buttons' => false,
			//						'teeny' => false,
			//						'textarea_rows' => 2,
			//						'wpautop' => true
			//					),
			//					'default' => 'Here are the current results'
			//				) );
			//	echo '
			//				</td>
			//			</tr>';
			//			
			//	// Default base text
			//	echo '
			//			<tr class="quiz-result-default">
			//				<th><label>' . __( 'Results text (default)' ) . '</label><br/><span class="description">' . __( 'This is displayed underneath the results panel' ) . '</span></th>
			//				<td>';
			//				$result = get_post_meta( $post->ID, 'result', true );
			//				icit_fields::field_textarea( array(
			//					'name' => 'result',
			//					'value' => $result ? $result : '<p>' . __( 'Please come back and be a part of our polls in the future.' ) . '</p>',
			//					'tiny_mce' => true,
			//					'edit_args' => array(
			//						'media_buttons' => true,
			//						'teeny' => true,
			//						'textarea_rows' => 2,
			//						'wpautop' => true
			//					),
			//					'default' => '<p>Please come back and be a part of our polls in the future.</p>'
			//				) );
			//
			//}

			echo '
				</tbody>
			</table>';

		}

		public function meta_box_embed( WP_Post $post, $meta_box ) {

			echo '<p>' . __( 'You can embed this quiz anywhere on your site using the following shortcode:' ) . '</p>';

			echo '
				<p>
					<input class="widefat select-on-click" type="text" value="[quizzlestick id=' . $post->ID . ']" readonly />
				</p>';

		}
		

		public function save_post( $post_id, WP_Post $post, $update ) {

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX )
				return $post_id;
			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
				return $post_id;
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return $post_id;

			// settings
			if ( isset( $_POST[ 'type' ] ) )
				update_post_meta( $post_id, 'type', icit_fields::validate_text( $_POST[ 'type' ] ) );
			//if ( isset( $_POST[ 'timelimit' ] ) )
			//	update_post_meta( $post_id, 'timelimit', icit_fields::validate_numeric( $_POST[ 'timelimit' ] ) );
			if ( isset( $_POST[ 'nextdelay' ] ) )
				update_post_meta( $post_id, 'nextdelay', icit_fields::validate_numeric( $_POST[ 'nextdelay' ] ) );
			if ( isset( $_POST[ 'correct' ] ) )
				update_post_meta( $post_id, 'correct', icit_fields::validate_text( $_POST[ 'correct' ] ) );
			if ( isset( $_POST[ 'incorrect' ] ) )
				update_post_meta( $post_id, 'incorrect', icit_fields::validate_text( $_POST[ 'incorrect' ] ) );

			// questions
			if ( isset( $_POST[ 'questions' ] ) ) {

				$questions = is_array( $_POST[ 'questions' ] ) ? $_POST[ 'questions' ] : array();

				// remove js placeholder from data
				if ( isset( $questions[ '__i__' ] ) )
					unset( $questions[ '__i__' ] );

				// add a new empty question to array
				if ( isset( $_POST[ 'addquestion' ] ) )
					$questions[] = array();

				// remove by index
				if ( isset( $_POST[ 'deletequestion' ] ) )
					unset( $questions[ array_pop( array_keys( $_POST[ 'deletequestion' ] ) ) ] );

				// process & validate questions
				if ( $questions )
					array_walk( $questions, function( &$q, $i ) {

						// add a new empty answer to question
						if ( isset( $_POST[ 'addanswer' ] ) && isset( $_POST[ 'addanswer' ][ $i ] ) )
							$q[ 'answers' ][] = array();

						// remove by index
						if ( isset( $_POST[ 'deleteanswer' ] ) && isset( $_POST[ 'deleteanswer' ][ $i ] ) )
							unset( $q[ 'answers' ][ array_pop( array_keys( $_POST[ 'deleteanswer' ][ $i ] ) ) ] );

						// fix the ridiculous lack of square brackets in names for editors
						$q[ 'result' ] 			= isset( $_POST[ "questions_{$i}_result" 			] ) ? $_POST[ "questions_{$i}_result" 			] : '';
						$q[ 'resultcorrect' ] 	= isset( $_POST[ "questions_{$i}_resultcorrect" 	] ) ? $_POST[ "questions_{$i}_resultcorrect" 	] : '';
						$q[ 'resultincorrect' ] = isset( $_POST[ "questions_{$i}_resultincorrect" 	] ) ? $_POST[ "questions_{$i}_resultincorrect" 	] : '';

						foreach( $q['answers'] as $a => $answer ) {
							$q[ 'answers' ][$a]['description'] = isset( $_POST[ "answer_{$a}_answer_description" ] ) ? $_POST[ "answer_{$a}_answer_description" ] : '';
						}
						
						$q = apply_filters( 'quizzlestick_save_questions', $q, $i );

						return $q;
					} );

				update_post_meta( $post_id, 'questions', $questions );
			}

			// results
			if ( isset( $_POST[ 'result_introduction' ] ) )
				update_post_meta( $post_id, 'result_introduction', icit_fields::validate_textarea( $_POST[ 'result_introduction' ] ) );
			
			if ( isset( $_POST[ 'result_heading' ] ) )
				update_post_meta( $post_id, 'result_heading', icit_fields::validate_text( $_POST[ 'result_heading' ] ) );
			
			if ( isset( $_POST[ 'result' ] ) )
				update_post_meta( $post_id, 'result', icit_fields::validate_textarea( $_POST[ 'result' ] ) );
			
			if ( isset( $_POST[ 'result_title' ] ) )
				update_post_meta( $post_id, 'result_title', icit_fields::validate_text( $_POST[ 'result_title' ] ) );
			
			
			if ( isset( $_POST[ 'results' ] ) || isset( $_POST[ 'addresult' ] ) ) {

				$results = is_array( $_POST[ 'results' ] ) ? $_POST[ 'results' ] : array();

				// remove js placeholder from data
				if ( isset( $results[ '__i__' ] ) )
					unset( $results[ '__i__' ] );

				// order by points
				usort( $results, function( $a, $b ) {
					return $a[ 'points' ] > $b[ 'points' ];
				} );

				// add a new empty answer to question
				if ( isset( $_POST[ 'addresult' ] ) )
					$results[] = array();

				// remove by index
				if ( isset( $_POST[ 'deleteresult' ] ) )
					unset( $results[ array_pop( array_keys( $_POST[ 'deleteresult' ] ) ) ] );

				// process & validate questions
				if ( $results )
					array_walk( $results, function( &$r, $i ) {

						// fix the ridiculous lack of square brackets in names for editors
						$r[ 'result' ] = isset( $_POST[ "results_{$i}_result" ] ) ? $_POST[ "results_{$i}_result" ] : '';

						return $r;
					} );

				update_post_meta( $post_id, 'results', $results );
			}

		}


		public function api() {
			global $wpdb;

			check_ajax_referer( 'quizzlestick' . AUTH_SALT, '_qsnonce' );

			// get quiz
			if ( ! isset( $_POST[ 'id' ] ) )
				return json_encode( array( 'error' => __( 'No quiz ID given' ) ) );

			// extract post ID
			$id = intval( preg_replace( '/\D+/', '', $_POST[ 'id' ] ) );

			$post = get_post( $id );

			if ( ! $post || is_wp_error( $post ) )
				return json_encode( array( 'error' => __( 'Unable to find a quiz matching that ID' ) ) );

			// $response object to return
			$response = array();

			if ( isset( $_POST[ 'qsaction' ] ) ) {

				$action = sanitize_key( $_POST[ 'qsaction' ] );

				switch( $action ) {

					case 'answer':

						$state 	= $_POST[ 'state' ];
						$qid 	= intval( $_POST[ 'question' ] );

						$totals = array();

						foreach( $state[ 'answers' ][ $qid ] as $a ) {

							$meta_key = "total_{$qid}_{$a}";

							$total = intval( get_post_meta( $post->ID, $meta_key, true ) );
							update_post_meta( $post->ID, $meta_key, ++$total );

							$totals[ "$a" ] = $total;

						}

						break;

					case 'complete':

						$state 	= $_POST[ 'state' ];
						$totals = array();

						foreach( (array)$state[ 'answers' ] as $qid => $answers ) {

							$totals[ "$qid" ] = array();

							if ( ! is_array( $answers ) )
								continue;

							foreach( $answers as $a ) {

								$meta_key = "total_{$qid}_{$a}";

								$total = intval( get_post_meta( $post->ID, $meta_key, true ) );
								update_post_meta( $post->ID, $meta_key, ++$total );

								$totals[ "$qid" ][ "$a" ] = $total;

							}

						}

						$response = array( 'success' => __( 'Updated answer totals' ), 'totals' => $totals );

						break;

				}

				do_action( 'quizzlestick_api', $post, $action );

			}

			// no response necessary
			exit;

			echo json_encode( $response );
			exit;
		}
		
		function set_quiz_nextdelay( $delay = 500, $post_id = false ) {
			
			if( !$post_id ) {
				return $delay;
			}
			
			$stored_delay = get_post_meta( $post_id, 'nextdelay', true );
			if( is_numeric( $stored_delay ) && $stored_delay > 0 ) {
				$delay = $stored_delay;
			}
			
			return $delay;
			
		}

	}
}
