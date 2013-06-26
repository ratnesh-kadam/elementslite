<?php
/**
 * Twitter bar element.
 *
 * Please do not edit this file. This file is part of the CyberChimps Framework and all modifications
 * should be made in a child theme.
 *
 * @category CyberChimps Framework
 * @package  Framework
 * @since    1.0
 * @author   CyberChimps
 * @license  http://www.opensource.org/licenses/gpl-license.php GPL v2.0 (or later)
 * @link     http://www.cyberchimps.com/
 */

// Don't load directly
if( !defined( 'ABSPATH' ) ) {
    die( '-1' );
}

if( !class_exists( 'CyberChimpsTwitterBar' ) ) {
    class CyberChimpsTwitterBar {

        protected static $instance;
        public $options;
        public $twitter_errors;
        private $query_arg;
        private $query_auth;

        /* Static Singleton Factory Method */
        public static function instance() {
            if( !isset( self::$instance ) ) {
                $className      = __CLASS__;
                self::$instance = new $className;
            }

            return self::$instance;
        }

        /**
         * Initializes plugin variables and sets up WordPress hooks/actions.
         *
         * @return void
         */
        protected function __construct() {

            // Set up hooks, options and filters
            add_action( 'twitterbar_section', array( $this, 'render_display' ) );
            $this->options = get_option( 'cyberchimps_options' );
            add_action( 'cyberchimps_twitter_api_section_before', array( $this, 'show_twitter_errors' ) );
            add_action( 'cyberchimps_options_before_save', array( $this, 'twitter_change_fields' ), 10, 1 );
            add_filter( 'cyberchimps_sections_filter', array( $this, 'twitter_option_section' ), 1 );
            add_filter( 'cyberchimps_field_filter', array( $this, 'twitter_option_fields' ), 1 );

            // set up auth and query variables
            $this->query_arg['count']              = 1;
            $this->query_arg['exclude_replies']    = true;
            $this->query_arg['include_rts']        = false;
            $this->auth_arg['access_token']        = $this->options['twitter_access_token'];
            $this->auth_arg['access_token_secret'] = $this->options['twitter_access_token_secret'];
            $this->auth_arg['consumer_key']        = $this->options['twitter_consumer_key'];
            $this->auth_arg['consumer_secret']     = $this->options['twitter_consumer_secret'];
        }

        /**
         * Display the Tweets
         */
        public function render_display() {

            // Get twitter handle.
            $screen_name              		= $this->options['twitter_handle'];
            $this->query_arg['screen_name'] = ( $screen_name != '' ) ? $screen_name : 'CyberChimps';

            $latest_tweet = self::cyberchimps_get_tweets( $this->query_arg, $this->auth_arg );

            // Display error message if there is an error retrieving tweets
            if( !$latest_tweet || is_string( $latest_tweet ) || array_key_exists( 'errors', $latest_tweet ) ) {
                ?>
                <div id="twitter-container" class="row-fluid">
                    <div id="twitter-bar" class="span12">
                        <div id="twitter-text">
                            <?php _e( 'Error retrieving tweets', 'cyberchimps_elements' ); ?>
                        </div>
                    </div>
                </div>
            <?php
            }
            elseif( is_wp_error( $latest_tweet ) ) {
                echo $latest_tweet->get_error_code() . ' - ' . $latest_tweet->get_error_message();
            }
            else {
                // Set variables
                $image_url = get_template_directory_uri() . '/elements/lib/images/twitter/twitterbird.png';
                ?>
                <div id="twitter-container" class="row-fluid">
                    <div id="twitter-bar" class="span12">
                        <div id="twitter-text">
                            <?php
                            if( $latest_tweet ) {
                                // get the tweet text
                                $tweet_text = $latest_tweet[0]['text'];
                                // look for a twitter shortened url and turn it into a link
                                $tweet_text      = preg_replace( "/[^^](http:\/\/+[\S]*)/", '<a href="$0">$0</a>', $tweet_text );
                                $screen_name     = $latest_tweet[0]['user']['screen_name'];
                                $user_permalink  = 'http://twitter.com/#!/' . $screen_name;
                                $tweet_permalink = 'http://twitter.com/#!/' . $screen_name . '/status/' . $latest_tweet[0]['id_str'];
                                echo '<img src="' . esc_url( $image_url ) . '" />';
                                echo '<p><a href="' . esc_url( $user_permalink ) . '"> ';
                                echo $screen_name . '</a> - ' . wp_kses( $tweet_text, array( 'a' => array( 'href' => array() ) ) ) . ' <small><a href="' . $tweet_permalink . '">' . human_time_diff( strtotime( $latest_tweet[0]['created_at'] ), current_time( 'timestamp' ) ) . ' ago</a></small></p>';
							}
                            else {
                                echo apply_filters( 'cyberchimps_tweets_empty_message', '<p>' . __( 'No tweets to display', 'cyberchimps_elements' ) . '</p>' );
                            }
                            ?>
                        </div>
                        <!-- #twitter-text .span12 -->
                    </div>
                    <!-- #twitter-bar -->
                </div><!-- .row-fluid -->
            <?php
            }
        }

        /**
         * Get Tweets from cache or Twitter
         *
         * @param $query_arg
         * @param $auth_arg
         *
         * @return string
         */
        function cyberchimps_get_tweets( $query_arg, $auth_arg ) {

            // Build request URL
            $request_url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
            $request_url = add_query_arg( $query_arg, $request_url );
            $request_url = add_query_arg( $auth_arg, $request_url );

            // Generate key
            $key = 'cyberchimps_twitter_' . md5( $request_url );

            // expires every hour
            $expiration = 60 * 60;

            $transient = get_transient( $key );
            if( false === $transient ) {
                // Hard expiration
                $data = self::retrieve_remote_tweets( $query_arg, $auth_arg );

                if( !is_wp_error( $data ) ) {
                    // Update transient
                    self::set_twitter_transient( $key, $data, $expiration );
                }

                return $data;

            }
            else {
                // Soft expiration. $transient = array( expiration time, data)
                if( $transient[0] !== 0 && $transient[0] <= time() ) {

                    // Expiration time passed, attempt to get new data
                    $new_data = self::retrieve_remote_tweets( $query_arg, $auth_arg );

                    if( !is_wp_error( $new_data ) ) {
                        // If successful return update transient and new data
                        self::set_twitter_transient( $key, $new_data, $expiration );
                        $transient[1] = $new_data;
                    }
                }

                return $transient[1];
            }
        }

        /**
         * Retrieve data from Twitter
         *
         * @param $query_arg
         * @param $auth_arg
         *
         * @return string
         */
        protected function retrieve_remote_tweets( $query_arg, $auth_arg ) {

            // Include codebird library.
            if( !class_exists( 'Codebird' ) ) {
                require_once( get_template_directory() . '/elements/includes/codebird.php' );
            }

            // Create instance of codebird class by supplying account credentials.
            Codebird::setConsumerKey( $auth_arg['consumer_key'], $auth_arg['consumer_secret'] );
            $codebird_instance = Codebird::getInstance();
            $codebird_instance->setToken( $auth_arg['access_token'], $auth_arg['access_token_secret'] );

            // Set return data type.
            $codebird_instance->setReturnFormat( CODEBIRD_RETURNFORMAT_ARRAY );

            // Get tweets by supplying query arguments.
            try {
                $twitter_data = $codebird_instance->statuses_userTimeline( $query_arg );
            }
            catch( Exception $e ) {
                return __( 'Error retrieving tweets', 'cyberchimps_elements' );
            }

            return $twitter_data;
        }

        /**
         * Set transient
         *
         * @param $key
         * @param $data
         * @param $expiration
         */
        protected function set_twitter_transient( $key, $data, $expiration ) {
            // Time when transient expires
            $expire = time() + $expiration;
            set_transient( $key, array( $expire, $data ) );
        }

        /**
         * @param $orig array of options sections
         *
         * @return array of newly merged sections
         */
        public function twitter_option_section( $orig ) {

            // Create new option section for Twitter API
            $new_section[][3] = array(
                'id'      => 'cyberchimps_twitter_api_section',
                'label'   => __( 'Twitter API Options', 'cyberchimps_core' ),
                'heading' => 'cyberchimps_blog_heading'
            );

            $new_sections = cyberchimps_array_section_organizer( $orig, $new_section );

            return $new_sections;
        }

        /**
         * @param $orig array of option fields
         *
         * @return array of newly merged fields
         */
        public function twitter_option_fields( $orig ) {

            $new_field[][1] = array(
                'name'    => __( 'Edit Twitter API', 'cyberchimps_elements' ),
                'id'      => 'twitter_edit',
                'type'    => 'toggle',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );

            $help_text = '<ol id="twitter_help">';
            $help_text .= '<li>';
            $help_text .= sprintf( '<a href="https://dev.twitter.com/apps/new" title="%1$s">%1$s</a>',
                                   __( 'Create your Twitter App here', 'cyberchimps_elements' ) );
            $help_text .= '</li>';
            $help_text .= '<li>' . __( 'Complete all parts of the form and create your application', 'cyberchimps_elements' ) . '</li>';
            $help_text .= '<li>' . __( 'On the next page click on the button Create my access token', 'cyberchimps_elements' ) . '</li>';
            $help_text .= '<li>' . __( 'Now take the details and fill out the form below', 'cyberchimps_elements' ) . '</li>';
            $help_text .= '<li>' . __( 'Save your options', 'cyberchimps_elements' ) . '</li>';
            $help_text .= '</ol>';

            $new_field[][2] = array(
                'name'    => __( 'Create a Twitter App', 'cyberchimps_elements' ),
                'id'      => 'twitter_app_help',
                'class'   => 'twitter_edit_toggle',
                'desc'    => $help_text,
                'type'    => 'info',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );

            $new_field[][3] = array(
                'name'    => __( 'Consumer Key', 'cyberchimps_elements' ),
                'id'      => 'twitter_consumer_key',
                'class'   => 'twitter_edit_toggle',
                'std'     => '',
                'type'    => 'text',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );

            $new_field[][4] = array(
                'name'    => __( 'Consumer Secret', 'cyberchimps_elements' ),
                'id'      => 'twitter_consumer_secret',
                'class'   => 'twitter_edit_toggle',
                'std'     => '',
                'type'    => 'text',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );

            $new_field[][5] = array(
                'name'    => __( 'Access Token', 'cyberchimps_elements' ),
                'id'      => 'twitter_access_token',
                'class'   => 'twitter_edit_toggle',
                'std'     => '',
                'type'    => 'text',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );

            $new_field[][6] = array(
                'name'    => __( 'Access Token Secret', 'cyberchimps_elements' ),
                'id'      => 'twitter_access_token_secret',
                'class'   => 'twitter_edit_toggle',
                'std'     => '',
                'type'    => 'text',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );

            $new_field[][7] = array(
                'name'    => __( 'Twitter Handle', 'cyberchimps_elements' ),
                'id'      => 'twitter_handle',
                'std'     => apply_filters( 'cyberchimps_twitter_handle_filter', 'CyberChimps' ),
                'type'    => 'text',
                'section' => 'cyberchimps_twitter_api_section',
                'heading' => 'cyberchimps_blog_heading'
            );
			
            $new_fields = cyberchimps_array_field_organizer( $orig, $new_field );

            return $new_fields;
        }

        /**
         * Watches the twitter values compared to the posted types and deletes the transient if they are different, allowing it to show the error message
         * We use this so that it doesn't keep checking the Twitter API, only on changes and when the transient runs out
         *
         * @param $post is the $_POST data before theme options are saved
         *
         * @uses cyberchimps_options_before_save hook
         */
        public function twitter_change_fields( $post ) {

            if( $post['twitter_consumer_key'] != $this->options['twitter_consumer_key'] || $post['twitter_consumer_secret'] != $this->options['twitter_consumer_secret']
                || $post['twitter_access_token'] != $this->options['twitter_access_token'] || $post['twitter_access_token_secret'] != $this->options['twitter_access_token_secret'] ) {
                delete_transient( 'cyberchimps_twitter_success' );
            }

        }
        /**
         * Get errors returned from Twitter
         *
         * @return array
         */
        protected function get_twitter_errors() {
            // Make sure the user wants to set up Twitter
            if( $this->options['twitter_consumer_key'] != '' || $this->options['twitter_consumer_secret'] != ''
                || $this->options['twitter_access_token'] != '' || $this->options['twitter_access_token_secret'] != '' ) {

                // If there is no twitter transient set then retest for errors
                if( !get_transient( 'cyberchimps_twitter_success' ) ) {

                    // Connect to Twitter to check we are connected
                    $errors = self::cyberchimps_get_tweets( $this->query_arg, $this->auth_arg );

                    // If the Twitter call returns something
                    if( is_array( $errors ) ) {

                        // If it returns and error return these errors
                        if( array_key_exists( 'errors', $errors ) ) {
                            return $errors;
                        }
                        // Else we have success and set the transient so we check the API once a day
                        else {
                            set_transient( 'cyberchimps_twitter_success', 'connected', 60 * 60 * 24 );
                        }
                    }
                }
            }

        }

        /**
         * Echos the error message if there is one
         */
        public function show_twitter_errors() {
            $errors = $this->get_twitter_errors();

            if( $errors ) {
                $display = '<div class="alert alert-error">';
                $display .= '<button type="button" class="close" data-dismiss="alert">&times;</button>';
                $display .= '<h4>' . __( 'Error', 'cyberchimps_elements' ) . '</h4>';
                $display .= '<ul>';
                foreach( $errors['errors'] as $error ) {
                    $display .= '<li>' . esc_html( $error['message'] ) . '</li>';
                }
                $display .= '</ul>';
                $display .= '</div>';

                echo $display;
            }
        }

    }
}
CyberChimpsTwitterBar::instance();