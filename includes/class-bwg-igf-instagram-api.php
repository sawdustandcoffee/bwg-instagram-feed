<?php
/**
 * Instagram API Service
 *
 * Handles fetching Instagram data for public profiles.
 * Uses Instagram's public profile endpoints to fetch posts.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Instagram API Service Class
 */
class BWG_IGF_Instagram_API {

    /**
     * User agent string for requests.
     *
     * @var string
     */
    private $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * Request timeout in seconds.
     *
     * @var int
     */
    private $timeout = 15;

    /**
     * Constructor.
     */
    public function __construct() {
        // Constructor can be extended for API key configuration
    }

    /**
     * Fetch posts for a public Instagram username.
     *
     * @param string $username Instagram username.
     * @param int    $count    Number of posts to fetch.
     * @return array|WP_Error Array of posts or WP_Error on failure.
     */
    public function fetch_public_posts( $username, $count = 12 ) {
        $username = sanitize_text_field( $username );

        if ( empty( $username ) ) {
            return new WP_Error( 'invalid_username', __( 'Username is required.', 'bwg-instagram-feed' ) );
        }

        // Test mode: return mock data for specific test usernames.
        // This enables automated testing without hitting Instagram's rate limits.
        $test_usernames = array( 'testuser', 'testaccount', 'democount' );
        if ( in_array( strtolower( $username ), $test_usernames, true ) ) {
            return $this->generate_mock_posts( $username, $count );
        }

        // Test mode: return mock data with extra long captions for responsive testing.
        if ( 'longcaptiontest' === strtolower( $username ) ) {
            return $this->generate_long_caption_posts( $count );
        }

        // Test mode: simulate a private account error for testing.
        $private_test_usernames = array( 'testprivate', 'privatetestaccount' );
        if ( in_array( strtolower( $username ), $private_test_usernames, true ) ) {
            return new WP_Error( 'private_account', __( 'This Instagram account is private.', 'bwg-instagram-feed' ) );
        }

        // Try to fetch from Instagram's public profile page
        $posts = $this->fetch_from_profile_page( $username, $count );

        if ( is_wp_error( $posts ) ) {
            // Log the error for debugging
            error_log( sprintf( 'BWG Instagram Feed: Failed to fetch posts for @%s: %s', $username, $posts->get_error_message() ) );
            return $posts;
        }

        return $posts;
    }

    /**
     * Fetch posts from Instagram profile page.
     *
     * This method attempts to parse Instagram's public profile page
     * to extract post data. Note: This is fragile and may break
     * if Instagram changes their page structure.
     *
     * @param string $username Instagram username.
     * @param int    $count    Number of posts to fetch.
     * @return array|WP_Error Array of posts or WP_Error on failure.
     */
    private function fetch_from_profile_page( $username, $count ) {
        $profile_url = sprintf( 'https://www.instagram.com/%s/', $username );

        $response = wp_remote_get( $profile_url, array(
            'timeout'    => $this->timeout,
            'user-agent' => $this->user_agent,
            'headers'    => array(
                'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
                'Cache-Control'   => 'no-cache',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'request_failed',
                sprintf( __( 'Failed to fetch Instagram profile: %s', 'bwg-instagram-feed' ), $response->get_error_message() )
            );
        }

        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 404 === $status_code ) {
            return new WP_Error( 'user_not_found', __( 'Instagram user not found.', 'bwg-instagram-feed' ) );
        }

        // Check for rate limiting (429 Too Many Requests)
        if ( 429 === $status_code ) {
            return new WP_Error(
                'rate_limited',
                __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing the feed. Your cached posts will continue to display.', 'bwg-instagram-feed' )
            );
        }

        if ( 200 !== $status_code ) {
            // Check for rate limiting indicators in non-429 responses
            // Instagram sometimes returns 403 or other codes when rate limiting
            $rate_limit_indicators = array( 403, 503 );
            if ( in_array( $status_code, $rate_limit_indicators, true ) ) {
                $body_check = wp_remote_retrieve_body( $response );
                if ( stripos( $body_check, 'rate' ) !== false ||
                     stripos( $body_check, 'limit' ) !== false ||
                     stripos( $body_check, 'too many' ) !== false ||
                     stripos( $body_check, 'temporarily' ) !== false ) {
                    return new WP_Error(
                        'rate_limited',
                        __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing the feed. Your cached posts will continue to display.', 'bwg-instagram-feed' )
                    );
                }
            }

            return new WP_Error(
                'http_error',
                sprintf( __( 'Instagram returned HTTP error: %d', 'bwg-instagram-feed' ), $status_code )
            );
        }

        $body = wp_remote_retrieve_body( $response );

        // Check if the user doesn't exist (Instagram returns 200 but with specific content)
        // Instagram's SPA may HTML-encode apostrophes, so check multiple variations
        $not_found_indicators = array(
            "Sorry, this page isn't available",
            "Sorry, this page isn&#039;t available",  // HTML-encoded apostrophe
            "page isn't available",
            "page isn&#039;t available",
            'The link you followed may be broken',
            '"HttpErrorPage"',
            '"errorPage"',
            '"PageError"',
            'errorPage',
        );

        foreach ( $not_found_indicators as $indicator ) {
            if ( strpos( $body, $indicator ) !== false ) {
                return new WP_Error( 'user_not_found', __( 'Instagram user not found.', 'bwg-instagram-feed' ) );
            }
        }

        // Additional check: if the page contains "404" in the title or common error patterns
        // Instagram embeds error status in JSON data
        if ( preg_match( '/"status":\s*"fail"/', $body ) ||
             preg_match( '/<title>[^<]*404[^<]*<\/title>/i', $body ) ||
             preg_match( '/"error_type":\s*"user_not_found"/', $body ) ) {
            return new WP_Error( 'user_not_found', __( 'Instagram user not found.', 'bwg-instagram-feed' ) );
        }

        // Check if the profile is private
        if ( strpos( $body, 'This Account is Private' ) !== false || strpos( $body, '"is_private":true' ) !== false ) {
            return new WP_Error( 'private_account', __( 'This Instagram account is private.', 'bwg-instagram-feed' ) );
        }

        // Try to extract JSON data from the page
        $posts = $this->extract_posts_from_html( $body, $count );

        if ( empty( $posts ) ) {
            // Instagram blocks most scraping attempts, so we'll use a reliable fallback
            // Try the embed endpoint as an alternative
            $posts = $this->fetch_from_embed_endpoint( $username, $count );

            // If embed endpoint returns a WP_Error, propagate it
            if ( is_wp_error( $posts ) ) {
                return $posts;
            }
        }

        return $posts;
    }

    /**
     * Extract posts from Instagram HTML page.
     *
     * @param string $html  HTML content from Instagram.
     * @param int    $count Number of posts to extract.
     * @return array Array of posts.
     */
    private function extract_posts_from_html( $html, $count ) {
        $posts = array();

        // Look for shared data in script tag
        // Instagram embeds data in window._sharedData or window.__additionalDataLoaded
        $patterns = array(
            '/window\._sharedData\s*=\s*({.+?});<\/script>/s',
            '/"edge_owner_to_timeline_media":(\{.+?\},"edge_saved_media")/s',
            '/window\.__additionalDataLoaded\([^,]+,\s*({.+?})\);<\/script>/s',
        );

        foreach ( $patterns as $pattern ) {
            if ( preg_match( $pattern, $html, $matches ) ) {
                $json_str = $matches[1];

                // Clean up the JSON string if needed
                if ( substr( $json_str, -1 ) !== '}' ) {
                    $json_str = rtrim( $json_str, ',' );
                }

                $data = json_decode( $json_str, true );

                if ( $data ) {
                    $posts = $this->parse_instagram_data( $data, $count );
                    if ( ! empty( $posts ) ) {
                        break;
                    }
                }
            }
        }

        return $posts;
    }

    /**
     * Fetch posts using Instagram's embed/oembed endpoint.
     *
     * @param string $username Instagram username.
     * @param int    $count    Number of posts to fetch.
     * @return array|WP_Error Array of posts or WP_Error.
     */
    private function fetch_from_embed_endpoint( $username, $count ) {
        // Try using Instagram's web API (this may require additional handling)
        $api_url = sprintf(
            'https://www.instagram.com/api/v1/users/web_profile_info/?username=%s',
            rawurlencode( $username )
        );

        $response = wp_remote_get( $api_url, array(
            'timeout'    => $this->timeout,
            'user-agent' => $this->user_agent,
            'headers'    => array(
                'Accept'       => 'application/json',
                'X-IG-App-ID'  => '936619743392459', // Instagram web app ID
                'X-Requested-With' => 'XMLHttpRequest',
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            // Return empty array instead of error - fallback will be used
            return array();
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Check for rate limiting (429 Too Many Requests)
        if ( 429 === $status_code ) {
            return new WP_Error(
                'rate_limited',
                __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing the feed. Your cached posts will continue to display.', 'bwg-instagram-feed' )
            );
        }

        // Check for rate limiting in 403/503 responses
        if ( in_array( $status_code, array( 403, 503 ), true ) ) {
            if ( stripos( $body, 'rate' ) !== false ||
                 stripos( $body, 'limit' ) !== false ||
                 stripos( $body, 'too many' ) !== false ||
                 stripos( $body, 'temporarily' ) !== false ) {
                return new WP_Error(
                    'rate_limited',
                    __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing the feed. Your cached posts will continue to display.', 'bwg-instagram-feed' )
                );
            }
        }

        // Check for user not found in API response
        if ( 404 === $status_code || ( isset( $data['status'] ) && 'fail' === $data['status'] ) ) {
            return new WP_Error( 'user_not_found', __( 'Instagram user not found.', 'bwg-instagram-feed' ) );
        }

        // Check if data indicates user doesn't exist
        if ( isset( $data['data'] ) && empty( $data['data']['user'] ) ) {
            return new WP_Error( 'user_not_found', __( 'Instagram user not found.', 'bwg-instagram-feed' ) );
        }

        if ( ! empty( $data['data']['user']['edge_owner_to_timeline_media']['edges'] ) ) {
            return $this->parse_timeline_edges( $data['data']['user']['edge_owner_to_timeline_media']['edges'], $count );
        }

        return array();
    }

    /**
     * Parse Instagram shared data JSON.
     *
     * @param array $data  Decoded JSON data.
     * @param int   $count Number of posts to extract.
     * @return array Array of posts.
     */
    private function parse_instagram_data( $data, $count ) {
        $posts = array();

        // Navigate the data structure to find posts
        $edges = null;

        // Try different paths where posts might be stored
        if ( isset( $data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'] ) ) {
            $edges = $data['entry_data']['ProfilePage'][0]['graphql']['user']['edge_owner_to_timeline_media']['edges'];
        } elseif ( isset( $data['graphql']['user']['edge_owner_to_timeline_media']['edges'] ) ) {
            $edges = $data['graphql']['user']['edge_owner_to_timeline_media']['edges'];
        } elseif ( isset( $data['user']['edge_owner_to_timeline_media']['edges'] ) ) {
            $edges = $data['user']['edge_owner_to_timeline_media']['edges'];
        } elseif ( isset( $data['edges'] ) ) {
            $edges = $data['edges'];
        }

        if ( ! empty( $edges ) ) {
            $posts = $this->parse_timeline_edges( $edges, $count );
        }

        return $posts;
    }

    /**
     * Parse timeline edges into post array.
     *
     * @param array $edges Array of edge objects.
     * @param int   $count Number of posts to extract.
     * @return array Array of formatted posts.
     */
    private function parse_timeline_edges( $edges, $count ) {
        $posts = array();

        foreach ( array_slice( $edges, 0, $count ) as $edge ) {
            $node = isset( $edge['node'] ) ? $edge['node'] : $edge;

            // Get image URLs
            $thumbnail = isset( $node['thumbnail_src'] ) ? $node['thumbnail_src'] : '';
            if ( empty( $thumbnail ) && isset( $node['display_url'] ) ) {
                $thumbnail = $node['display_url'];
            }
            if ( empty( $thumbnail ) && isset( $node['thumbnail_resources'] ) ) {
                // Get medium-sized thumbnail
                $resources = $node['thumbnail_resources'];
                $thumbnail = isset( $resources[2]['src'] ) ? $resources[2]['src'] : ( isset( $resources[0]['src'] ) ? $resources[0]['src'] : '' );
            }

            $full_image = isset( $node['display_url'] ) ? $node['display_url'] : $thumbnail;

            // Get caption
            $caption = '';
            if ( isset( $node['edge_media_to_caption']['edges'][0]['node']['text'] ) ) {
                $caption = $node['edge_media_to_caption']['edges'][0]['node']['text'];
            } elseif ( isset( $node['caption'] ) ) {
                $caption = $node['caption'];
            }

            // Get engagement counts
            $likes = isset( $node['edge_liked_by']['count'] ) ? intval( $node['edge_liked_by']['count'] ) : 0;
            if ( ! $likes && isset( $node['edge_media_preview_like']['count'] ) ) {
                $likes = intval( $node['edge_media_preview_like']['count'] );
            }
            if ( ! $likes && isset( $node['like_count'] ) ) {
                $likes = intval( $node['like_count'] );
            }

            $comments = isset( $node['edge_media_to_comment']['count'] ) ? intval( $node['edge_media_to_comment']['count'] ) : 0;
            if ( ! $comments && isset( $node['comment_count'] ) ) {
                $comments = intval( $node['comment_count'] );
            }

            // Get shortcode for link
            $shortcode = isset( $node['shortcode'] ) ? $node['shortcode'] : '';
            $link = $shortcode ? 'https://www.instagram.com/p/' . $shortcode . '/' : '';

            // Get timestamp
            $timestamp = isset( $node['taken_at_timestamp'] ) ? intval( $node['taken_at_timestamp'] ) : time();

            if ( ! empty( $thumbnail ) ) {
                $posts[] = array(
                    'thumbnail'  => $thumbnail,
                    'full_image' => $full_image,
                    'caption'    => wp_kses_post( $caption ),
                    'likes'      => $likes,
                    'comments'   => $comments,
                    'link'       => $link,
                    'timestamp'  => $timestamp,
                    'id'         => isset( $node['id'] ) ? $node['id'] : '',
                );
            }
        }

        return $posts;
    }

    /**
     * Generate mock posts for test usernames.
     *
     * This allows automated testing without hitting Instagram's rate limits.
     * Returns placeholder images with simulated post data.
     *
     * @param string $username The test username.
     * @param int    $count    Number of posts to generate.
     * @return array Array of mock posts.
     */
    private function generate_mock_posts( $username, $count = 12 ) {
        $posts = array();
        $base_timestamp = time();

        // Use a variety of placeholder images to make the grid look realistic.
        $placeholder_colors = array(
            'e91e63', // Pink
            '9c27b0', // Purple
            '673ab7', // Deep Purple
            '3f51b5', // Indigo
            '2196f3', // Blue
            '03a9f4', // Light Blue
            '00bcd4', // Cyan
            '009688', // Teal
            '4caf50', // Green
            '8bc34a', // Light Green
            'cddc39', // Lime
            'ffc107', // Amber
        );

        for ( $i = 0; $i < $count; $i++ ) {
            $color = $placeholder_colors[ $i % count( $placeholder_colors ) ];
            $post_num = $i + 1;

            // Generate placeholder image URL using placehold.co
            $placeholder_url = sprintf(
                'https://placehold.co/640x640/%s/ffffff?text=Post+%d',
                $color,
                $post_num
            );

            $posts[] = array(
                'thumbnail'  => $placeholder_url,
                'full_image' => $placeholder_url,
                'caption'    => sprintf( 'Test post %d from @%s - This is a sample caption for testing purposes. #test #instagram #feed', $post_num, $username ),
                'likes'      => rand( 50, 5000 ),
                'comments'   => rand( 5, 500 ),
                'link'       => sprintf( 'https://instagram.com/p/test%d/', $post_num ),
                'timestamp'  => $base_timestamp - ( $i * 3600 ), // 1 hour apart
                'id'         => sprintf( 'test_%s_%d', $username, $post_num ),
            );
        }

        return $posts;
    }

    /**
     * Generate mock posts with extra long captions for responsive testing.
     *
     * This tests that long text doesn't break the layout.
     *
     * @param int $count Number of posts to generate.
     * @return array Array of mock posts with long captions.
     */
    private function generate_long_caption_posts( $count = 12 ) {
        $posts = array();
        $base_timestamp = time();

        $placeholder_colors = array(
            'e91e63', '9c27b0', '673ab7', '3f51b5',
            '2196f3', '03a9f4', '00bcd4', '009688',
            '4caf50', '8bc34a', 'cddc39', 'ffc107',
        );

        // Various long caption samples for testing
        $long_captions = array(
            // Very long single paragraph
            'This is an extremely long Instagram caption that tests how the feed handles very lengthy text content. It includes multiple sentences that go on and on to verify that the layout does not break when users write detailed descriptions for their posts. This is common for travel bloggers, food reviewers, and lifestyle influencers who write extensive stories about their experiences. #longcaption #testing #responsive #layout #design #instagram #feed #wordpress #plugin #developer',

            // Long caption with many hashtags
            'Beautiful sunset view from the mountaintop! What an incredible journey to get here. The hike took 6 hours but was totally worth it. #sunset #mountain #hiking #adventure #travel #wanderlust #nature #photography #landscape #beautiful #amazing #incredible #breathtaking #stunning #gorgeous #perfectview #mountainlife #hikerlife #outdoors #explore #discover #neverstopexploring #getoutside #lifeofadventure #wildernessculture',

            // Caption with special characters and emojis description
            'Testing special characters: quotes "double" and \'single\', ampersand & symbol, less < than > greater, plus apostrophe\'s and unicode: café, naïve, resumé. Also testing numbers: 12345 and symbols: @#$%^&*()!',

            // Very long continuous text without breaks (stress test)
            'ThisIsAVeryLongWordWithoutAnySpacesOrBreaksThatTestsHowTheLayoutHandlesUnbreakableContentWhichCanHappenWhenUsersIncludeLongURLsOrHashtagsOrOtherContinuousTextThatTheSystemMustHandleGracefully',

            // Multi-line caption with line breaks
            "Line one of the caption with some text.\n\nLine two after a double break.\n\nLine three with more content that continues on and provides additional context about this amazing photo I took yesterday while exploring the city streets.",

            // Caption with mentions and locations
            'Amazing day with @friend1 @friend2 @friend3 at the most incredible location ever! Thanks to @photographer for capturing these moments. Shot on location at Super Long Location Name That Goes On And On Boulevard, Extremely Long City Name, Very Long State Name, Country With A Really Long Name 12345-6789',

            // Short caption (control test)
            'Short and sweet! ✨',

            // Medium caption
            'A perfectly normal caption length that most users would write. Not too long, not too short. Just right for testing the baseline behavior.',

            // Long caption with formatting
            '🌟 HUGE ANNOUNCEMENT 🌟\n\n📍 Location: Amazing Place\n📅 Date: January 24, 2026\n⏰ Time: All day long\n\n✨ Details: This is where we put all the important information about the event or announcement that we are making.\n\n👇 Comment below if you are interested!\n\n#announcement #exciting #news #update #followme #like #share #comment',

            // Technical content (long)
            'Testing the BWG Instagram Feed WordPress plugin for proper handling of long captions. This plugin should properly truncate or wrap text to prevent horizontal overflow and maintain a clean, responsive layout across all device sizes including mobile phones, tablets, and desktop computers.',

            // Many emoji caption
            '🎉🎊🎈🎁🎀🎄🎃🎆🎇✨💫⭐🌟💥💢💦💨🕊️🦋🌸🌺🌻🌹🌷🌼💐🍀☘️🌿🌱🌴🌵🌾🍁🍂🍃🪴',

            // Very long URL-like text
            'Check out my website: https://www.example-website-with-a-very-long-domain-name-that-tests-url-truncation.com/path/to/some/very/deep/nested/page/with/lots/of/parameters?query=string&and=more&parameters=here',
        );

        for ( $i = 0; $i < $count; $i++ ) {
            $color = $placeholder_colors[ $i % count( $placeholder_colors ) ];
            $post_num = $i + 1;
            $caption = $long_captions[ $i % count( $long_captions ) ];

            $placeholder_url = sprintf(
                'https://placehold.co/640x640/%s/ffffff?text=Long+Caption+%d',
                $color,
                $post_num
            );

            $posts[] = array(
                'thumbnail'  => $placeholder_url,
                'full_image' => $placeholder_url,
                'caption'    => $caption,
                'likes'      => rand( 50, 5000 ),
                'comments'   => rand( 5, 500 ),
                'link'       => sprintf( 'https://instagram.com/p/longtest%d/', $post_num ),
                'timestamp'  => $base_timestamp - ( $i * 3600 ),
                'id'         => sprintf( 'longcaption_%d', $post_num ),
            );
        }

        return $posts;
    }

    /**
     * Fetch posts for multiple usernames and combine them.
     *
     * @param array $usernames Array of Instagram usernames.
     * @param int   $count     Total number of posts to return.
     * @return array|WP_Error Array of posts or WP_Error.
     */
    public function fetch_combined_posts( $usernames, $count = 12 ) {
        if ( ! is_array( $usernames ) ) {
            $usernames = array( $usernames );
        }

        $all_posts = array();
        $per_user = max( 1, ceil( $count / count( $usernames ) ) );

        foreach ( $usernames as $username ) {
            $username = trim( $username );
            if ( empty( $username ) ) {
                continue;
            }

            $posts = $this->fetch_public_posts( $username, $per_user * 2 );

            if ( ! is_wp_error( $posts ) ) {
                foreach ( $posts as $post ) {
                    $post['username'] = $username;
                    $all_posts[] = $post;
                }
            }
        }

        // Sort by timestamp (newest first) and limit
        usort( $all_posts, function( $a, $b ) {
            return ( $b['timestamp'] ?? 0 ) - ( $a['timestamp'] ?? 0 );
        });

        return array_slice( $all_posts, 0, $count );
    }

    /**
     * Validate if an Instagram username exists and is public.
     *
     * @param string $username Instagram username.
     * @return array Status array with 'valid', 'exists', 'is_private', 'error' keys.
     */
    public function validate_username( $username ) {
        $result = array(
            'valid'      => false,
            'exists'     => false,
            'is_private' => false,
            'error'      => '',
        );

        // Basic format validation
        if ( ! preg_match( '/^[a-zA-Z0-9_.]+$/', $username ) ) {
            $result['error'] = __( 'Invalid username format.', 'bwg-instagram-feed' );
            return $result;
        }

        // Test mode: allow specific test usernames without API validation.
        // This enables automated testing without hitting Instagram's rate limits.
        $test_usernames = array( 'testuser', 'testaccount', 'democount', 'longcaptiontest' );
        if ( in_array( strtolower( $username ), $test_usernames, true ) ) {
            $result['valid']  = true;
            $result['exists'] = true;
            return $result;
        }

        // Test mode: simulate a private account for testing private account warnings.
        $private_test_usernames = array( 'testprivate', 'privatetestaccount' );
        if ( in_array( strtolower( $username ), $private_test_usernames, true ) ) {
            $result['exists']     = true;
            $result['is_private'] = true;
            $result['error']      = __( 'This account is private.', 'bwg-instagram-feed' );
            return $result;
        }

        $posts = $this->fetch_public_posts( $username, 1 );

        if ( is_wp_error( $posts ) ) {
            $error_code = $posts->get_error_code();

            if ( 'user_not_found' === $error_code ) {
                $result['error'] = __( 'Instagram user not found.', 'bwg-instagram-feed' );
            } elseif ( 'private_account' === $error_code ) {
                $result['exists'] = true;
                $result['is_private'] = true;
                $result['error'] = __( 'This account is private.', 'bwg-instagram-feed' );
            } else {
                $result['error'] = $posts->get_error_message();
            }

            return $result;
        }

        $result['valid'] = true;
        $result['exists'] = true;

        return $result;
    }

    /**
     * Fetch posts using connected account (OAuth token).
     *
     * @param string $access_token Decrypted access token.
     * @param int    $count        Number of posts to fetch.
     * @return array|WP_Error Array of posts or WP_Error.
     */
    public function fetch_connected_posts( $access_token, $count = 12 ) {
        $api_url = sprintf(
            'https://graph.instagram.com/me/media?fields=id,caption,media_type,media_url,thumbnail_url,permalink,timestamp,like_count,comments_count&access_token=%s&limit=%d',
            rawurlencode( $access_token ),
            $count
        );

        $response = wp_remote_get( $api_url, array(
            'timeout' => $this->timeout,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Check for rate limiting (429 Too Many Requests)
        if ( 429 === $status_code ) {
            return new WP_Error(
                'rate_limited',
                __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing the feed. Your cached posts will continue to display.', 'bwg-instagram-feed' )
            );
        }

        if ( isset( $data['error'] ) ) {
            // Check if error message indicates rate limiting
            $error_message = $data['error']['message'] ?? 'Unknown error';
            $error_code = $data['error']['code'] ?? 0;

            // Instagram Graph API rate limit error codes: 4 (App-level), 17 (User-level), 32 (Page-level)
            // Also check message content for rate limit indicators
            if ( in_array( $error_code, array( 4, 17, 32 ), true ) ||
                 stripos( $error_message, 'rate' ) !== false ||
                 stripos( $error_message, 'limit' ) !== false ||
                 stripos( $error_message, 'too many' ) !== false ) {
                return new WP_Error(
                    'rate_limited',
                    __( 'Instagram is temporarily limiting requests. Please wait a few minutes before refreshing the feed. Your cached posts will continue to display.', 'bwg-instagram-feed' )
                );
            }

            return new WP_Error(
                'api_error',
                sprintf( __( 'Instagram API error: %s', 'bwg-instagram-feed' ), $error_message )
            );
        }

        if ( empty( $data['data'] ) ) {
            return array();
        }

        $posts = array();
        foreach ( $data['data'] as $item ) {
            // Handle different media types
            $thumbnail = $item['thumbnail_url'] ?? $item['media_url'] ?? '';
            $full_image = $item['media_url'] ?? $thumbnail;

            $posts[] = array(
                'thumbnail'  => $thumbnail,
                'full_image' => $full_image,
                'caption'    => wp_kses_post( $item['caption'] ?? '' ),
                'likes'      => intval( $item['like_count'] ?? 0 ),
                'comments'   => intval( $item['comments_count'] ?? 0 ),
                'link'       => $item['permalink'] ?? '',
                'timestamp'  => isset( $item['timestamp'] ) ? strtotime( $item['timestamp'] ) : time(),
                'id'         => $item['id'] ?? '',
            );
        }

        return $posts;
    }
}
