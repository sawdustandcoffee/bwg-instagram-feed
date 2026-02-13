<?php
/**
 * Image Proxy REST API with Local Caching
 *
 * Provides a REST API endpoint to proxy Instagram images, bypassing CORS restrictions.
 * Downloads Instagram images and stores them in wp-content/uploads/bwg-igf-cache/
 * directory with unique filenames based on URL hash.
 *
 * @package BWG_Instagram_Feed
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Image Proxy Class
 *
 * Registers and handles the REST API endpoint for proxying Instagram images.
 * Implements local caching to reduce external requests and improve performance.
 */
class BWG_IGF_Image_Proxy {

    /**
     * REST API namespace.
     *
     * @var string
     */
    const API_NAMESPACE = 'bwg-igf/v1';

    /**
     * REST API route.
     *
     * @var string
     */
    const API_ROUTE = '/proxy/image';

    /**
     * Cache directory name within uploads folder.
     *
     * @var string
     */
    const CACHE_DIR_NAME = 'bwg-igf-cache';

    /**
     * Cache duration in seconds (default: 7 days).
     *
     * @var int
     */
    const CACHE_DURATION = 604800;

    /**
     * WP Cron hook name for cache cleanup.
     *
     * @var string
     */
    const CRON_HOOK = 'bwg_igf_proxy_cache_cleanup';

    /**
     * Allowed Instagram CDN domains.
     *
     * @var array
     */
    private static $allowed_domains = array(
        'cdninstagram.com',
        'instagram.com',
        'fbcdn.net',
        'scontent.cdninstagram.com',
        'scontent-iad3-1.cdninstagram.com',
        'scontent-iad3-2.cdninstagram.com',
        'scontent-lga3-1.cdninstagram.com',
        'scontent-lga3-2.cdninstagram.com',
        'instagram.fath1-1.fna.fbcdn.net',
        'instagram.fath1-2.fna.fbcdn.net',
        // Picsum.photos for placeholder images
        'picsum.photos',
        'fastly.picsum.photos',
        // Placehold.co for test mode images
        'placehold.co',
    );

    /**
     * Initialize the image proxy.
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );

        // Register the cron hook for cache cleanup.
        add_action( self::CRON_HOOK, array( __CLASS__, 'cleanup_expired' ) );
    }

    /**
     * Schedule the cache cleanup cron job.
     *
     * Called on plugin activation to set up periodic cleanup of expired cache files.
     */
    public static function schedule_cleanup() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time(), 'daily', self::CRON_HOOK );
        }
    }

    /**
     * Unschedule the cache cleanup cron job.
     *
     * Called on plugin deactivation to remove the scheduled event.
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled( self::CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::CRON_HOOK );
        }
    }

    /**
     * Check if the cron job is scheduled.
     *
     * @return bool True if cron job is scheduled.
     */
    public static function is_cleanup_scheduled() {
        return (bool) wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Get the next scheduled cleanup time.
     *
     * @return int|false Unix timestamp of next scheduled run, or false if not scheduled.
     */
    public static function get_next_cleanup_time() {
        return wp_next_scheduled( self::CRON_HOOK );
    }

    /**
     * Register REST API routes.
     */
    public static function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            self::API_ROUTE,
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'proxy_image' ),
                'permission_callback' => '__return_true', // Public endpoint - images need to be accessible
                'args'                => array(
                    'url' => array(
                        'required'          => true,
                        'type'              => 'string',
                        'description'       => 'The Instagram image URL to proxy',
                        'sanitize_callback' => 'esc_url_raw',
                        'validate_callback' => array( __CLASS__, 'validate_image_url' ),
                    ),
                ),
            )
        );
    }

    /**
     * Validate the image URL parameter.
     *
     * @param string          $url   The URL to validate.
     * @param WP_REST_Request $request The request object.
     * @param string          $param The parameter name.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    public static function validate_image_url( $url, $request, $param ) {
        if ( empty( $url ) ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Image URL is required.', 'bwg-instagram-feed' ),
                array( 'status' => 400 )
            );
        }

        // Decode URL if it's encoded
        $decoded_url = urldecode( $url );

        // Parse the URL to check the domain
        $parsed = wp_parse_url( $decoded_url );

        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Invalid image URL format.', 'bwg-instagram-feed' ),
                array( 'status' => 400 )
            );
        }

        // Check if the domain is allowed
        $host = strtolower( $parsed['host'] );
        $allowed = false;

        foreach ( self::$allowed_domains as $domain ) {
            // Check if the host ends with the allowed domain
            if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
                $allowed = true;
                break;
            }
        }

        if ( ! $allowed ) {
            return new WP_Error(
                'rest_invalid_param',
                __( 'Image URL must be from Instagram CDN.', 'bwg-instagram-feed' ),
                array( 'status' => 400 )
            );
        }

        return true;
    }

    /**
     * Proxy an Instagram image with local caching.
     *
     * Downloads and stores images in wp-content/uploads/bwg-igf-cache/ directory
     * with unique filenames based on URL hash. Serves cached versions on subsequent requests.
     *
     * @param WP_REST_Request $request The request object.
     * @return WP_REST_Response|WP_Error The image response or error.
     */
    public static function proxy_image( WP_REST_Request $request ) {
        $url = $request->get_param( 'url' );

        // Decode URL if it's encoded
        $decoded_url = urldecode( $url );

        // Generate cache path based on URL hash
        $cache_path = self::get_cache_path( $decoded_url );
        $cache_meta_path = $cache_path . '.meta';

        // Check if image is cached and not expired
        if ( self::is_cache_valid( $cache_path, $cache_meta_path ) ) {
            return self::serve_cached_image( $cache_path, $cache_meta_path );
        }

        // Fetch the image from remote using native cURL to avoid Instagram's bot detection.
        // WordPress wp_remote_get uses HTTP/1.0 which Instagram blocks with 429.
        if ( function_exists( 'curl_init' ) ) {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $decoded_url );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
            curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Accept: image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Referer: https://www.instagram.com/',
                'Sec-Fetch-Site: cross-site',
                'Sec-Fetch-Mode: no-cors',
                'Sec-Fetch-Dest: image',
            ) );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
            curl_setopt( $ch, CURLOPT_HEADER, true );

            $response    = curl_exec( $ch );
            $status_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            $header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
            $curl_error  = curl_error( $ch );
            curl_close( $ch );

            if ( ! empty( $curl_error ) ) {
                error_log( 'BWG IGF Image Proxy: cURL error - ' . $curl_error );
                return self::return_placeholder_image();
            }

            if ( 200 !== $status_code ) {
                error_log( 'BWG IGF Image Proxy: Image returned status ' . $status_code . ' for URL: ' . $decoded_url );
                return self::return_placeholder_image();
            }

            $headers_str = substr( $response, 0, $header_size );
            $body        = substr( $response, $header_size );

            if ( empty( $body ) ) {
                error_log( 'BWG IGF Image Proxy: Empty response body for URL: ' . $decoded_url );
                return self::return_placeholder_image();
            }

            // Extract content-type from headers
            $content_type = '';
            if ( preg_match( '/content-type:\s*([^\r\n]+)/i', $headers_str, $matches ) ) {
                $content_type = trim( $matches[1] );
            }
        } else {
            // Fallback to wp_remote_get if cURL is not available
            $response = wp_remote_get(
                $decoded_url,
                array(
                    'timeout'     => 30,
                    'httpversion' => '1.1',
                    'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'headers'     => array(
                        'Accept'          => 'image/webp,image/apng,image/*,*/*;q=0.8',
                        'Accept-Language' => 'en-US,en;q=0.5',
                        'Referer'         => 'https://www.instagram.com/',
                    ),
                )
            );

            if ( is_wp_error( $response ) ) {
                error_log( 'BWG IGF Image Proxy: Failed to fetch image - ' . $response->get_error_message() );
                return self::return_placeholder_image();
            }

            $status_code = wp_remote_retrieve_response_code( $response );

            if ( 200 !== $status_code ) {
                error_log( 'BWG IGF Image Proxy: Image returned status ' . $status_code . ' for URL: ' . $decoded_url );
                return self::return_placeholder_image();
            }

            $body = wp_remote_retrieve_body( $response );

            if ( empty( $body ) ) {
                error_log( 'BWG IGF Image Proxy: Empty response body for URL: ' . $decoded_url );
                return self::return_placeholder_image();
            }

            $content_type = wp_remote_retrieve_header( $response, 'content-type' );
        }

        if ( empty( $content_type ) ) {
            // Try to detect from magic bytes
            $content_type = self::detect_image_type( $body );
        }

        // Validate it's actually an image
        if ( ! self::is_valid_image_type( $content_type ) ) {
            error_log( 'BWG IGF Image Proxy: Invalid content type: ' . $content_type );
            return self::return_placeholder_image();
        }

        // Save to local cache
        $cached = self::save_to_cache( $decoded_url, $body, $content_type, $cache_path, $cache_meta_path );

        if ( $cached ) {
            // Serve from newly cached file
            return self::serve_cached_image( $cache_path, $cache_meta_path );
        }

        // If caching failed, serve directly from memory
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Length: ' . strlen( $body ) );
        header( 'Cache-Control: public, max-age=86400' ); // Cache for 24 hours
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-BWG-IGF-Cache: MISS' );

        // Output the image directly and exit
        echo $body;
        exit;
    }

    /**
     * Get the cache directory path.
     *
     * @return string Full path to the cache directory.
     */
    public static function get_cache_directory() {
        $upload_dir = wp_upload_dir();
        return trailingslashit( $upload_dir['basedir'] ) . self::CACHE_DIR_NAME;
    }

    /**
     * Generate a unique cache filename based on URL hash.
     *
     * @param string $url The image URL.
     * @return string The cache filename (hash.extension).
     */
    public static function generate_cache_filename( $url ) {
        // Create MD5 hash of the URL for unique filename
        $hash = md5( $url );

        // Try to determine file extension from URL
        $extension = 'jpg'; // Default extension
        $path = wp_parse_url( $url, PHP_URL_PATH );

        if ( $path ) {
            $path_extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
            if ( in_array( $path_extension, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ), true ) ) {
                $extension = $path_extension;
            }
        }

        return $hash . '.' . $extension;
    }

    /**
     * Get the full cache path for a URL.
     *
     * @param string $url The image URL.
     * @return string Full path to the cached file.
     */
    public static function get_cache_path( $url ) {
        $cache_dir = self::get_cache_directory();
        $filename = self::generate_cache_filename( $url );
        return trailingslashit( $cache_dir ) . $filename;
    }

    /**
     * Check if cache is valid (exists and not expired).
     *
     * @param string $cache_path      Path to the cached image.
     * @param string $cache_meta_path Path to the cache metadata file.
     * @return bool True if cache is valid.
     */
    public static function is_cache_valid( $cache_path, $cache_meta_path ) {
        if ( ! file_exists( $cache_path ) || ! file_exists( $cache_meta_path ) ) {
            return false;
        }

        $meta = self::read_cache_metadata( $cache_meta_path );

        if ( ! $meta || ! isset( $meta['expires_at'] ) ) {
            return false;
        }

        return $meta['expires_at'] > time();
    }

    /**
     * Read cache metadata from file.
     *
     * @param string $cache_meta_path Path to the metadata file.
     * @return array|false Metadata array or false on failure.
     */
    public static function read_cache_metadata( $cache_meta_path ) {
        if ( ! file_exists( $cache_meta_path ) ) {
            return false;
        }

        $content = file_get_contents( $cache_meta_path );
        if ( false === $content ) {
            return false;
        }

        return json_decode( $content, true );
    }

    /**
     * Serve a cached image file.
     *
     * @param string $cache_path      Path to the cached image.
     * @param string $cache_meta_path Path to the cache metadata file.
     */
    private static function serve_cached_image( $cache_path, $cache_meta_path ) {
        $meta = self::read_cache_metadata( $cache_meta_path );
        $content_type = isset( $meta['content_type'] ) ? $meta['content_type'] : 'image/jpeg';

        // Set headers
        header( 'Content-Type: ' . $content_type );
        header( 'Content-Length: ' . filesize( $cache_path ) );
        header( 'Cache-Control: public, max-age=' . self::CACHE_DURATION );
        header( 'Expires: ' . gmdate( 'D, d M Y H:i:s', time() + self::CACHE_DURATION ) . ' GMT' );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-BWG-IGF-Cache: HIT' );

        // Output the cached file and exit
        readfile( $cache_path );
        exit;
    }

    /**
     * Save an image to the local cache.
     *
     * @param string $url             Original image URL.
     * @param string $body            Image binary data.
     * @param string $content_type    Image content type.
     * @param string $cache_path      Path to save the image.
     * @param string $cache_meta_path Path to save the metadata.
     * @return bool True on success, false on failure.
     */
    private static function save_to_cache( $url, $body, $content_type, $cache_path, $cache_meta_path ) {
        $cache_dir = self::get_cache_directory();

        // Ensure cache directory exists
        if ( ! self::ensure_cache_directory() ) {
            error_log( 'BWG IGF Image Proxy: Failed to create cache directory: ' . $cache_dir );
            return false;
        }

        // Save the image file
        $saved = file_put_contents( $cache_path, $body );
        if ( false === $saved ) {
            error_log( 'BWG IGF Image Proxy: Failed to save image to cache: ' . $cache_path );
            return false;
        }

        // Save metadata
        $meta = array(
            'url'          => $url,
            'content_type' => $content_type,
            'size'         => strlen( $body ),
            'created_at'   => time(),
            'expires_at'   => time() + self::CACHE_DURATION,
        );

        $meta_saved = file_put_contents( $cache_meta_path, wp_json_encode( $meta ) );
        if ( false === $meta_saved ) {
            error_log( 'BWG IGF Image Proxy: Failed to save metadata: ' . $cache_meta_path );
            // Delete the image file since metadata failed
            unlink( $cache_path );
            return false;
        }

        return true;
    }

    /**
     * Ensure the cache directory exists.
     *
     * @return bool True if directory exists or was created successfully.
     */
    public static function ensure_cache_directory() {
        $cache_dir = self::get_cache_directory();

        if ( file_exists( $cache_dir ) ) {
            return is_dir( $cache_dir ) && is_writable( $cache_dir );
        }

        // Create the directory
        $created = wp_mkdir_p( $cache_dir );

        if ( $created ) {
            // Add index.php for security (prevent directory listing)
            file_put_contents( $cache_dir . '/index.php', '<?php // Silence is golden.' );

            // Add .htaccess for proper caching headers (Apache)
            $htaccess = "# BWG Instagram Feed Image Cache\n";
            $htaccess .= "<IfModule mod_headers.c>\n";
            $htaccess .= "    Header set Cache-Control \"public, max-age=604800\"\n";
            $htaccess .= "</IfModule>\n";
            $htaccess .= "<IfModule mod_expires.c>\n";
            $htaccess .= "    ExpiresActive On\n";
            $htaccess .= "    ExpiresDefault \"access plus 7 days\"\n";
            $htaccess .= "</IfModule>\n";
            file_put_contents( $cache_dir . '/.htaccess', $htaccess );
        }

        return $created;
    }

    /**
     * Check if an image is cached.
     *
     * @param string $url The image URL.
     * @return bool True if cached and not expired.
     */
    public static function is_cached( $url ) {
        $cache_path = self::get_cache_path( $url );
        $cache_meta_path = $cache_path . '.meta';

        return self::is_cache_valid( $cache_path, $cache_meta_path );
    }

    /**
     * Get cache metadata for an image.
     *
     * @param string $url The image URL.
     * @return array|false Metadata array or false if not cached.
     */
    public static function get_cache_meta( $url ) {
        $cache_path = self::get_cache_path( $url );
        $cache_meta_path = $cache_path . '.meta';

        return self::read_cache_metadata( $cache_meta_path );
    }

    /**
     * Clear all cached images.
     *
     * @return int Number of files deleted.
     */
    public static function clear_cache() {
        $cache_dir = self::get_cache_directory();
        $count = 0;

        if ( ! is_dir( $cache_dir ) ) {
            return 0;
        }

        $files = glob( $cache_dir . '/*' );
        foreach ( $files as $file ) {
            if ( is_file( $file ) && basename( $file ) !== 'index.php' && basename( $file ) !== '.htaccess' ) {
                if ( unlink( $file ) ) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Clean up expired cache files.
     *
     * @return int Number of files deleted.
     */
    public static function cleanup_expired() {
        $cache_dir = self::get_cache_directory();
        $count = 0;

        if ( ! is_dir( $cache_dir ) ) {
            return 0;
        }

        $meta_files = glob( $cache_dir . '/*.meta' );
        foreach ( $meta_files as $meta_file ) {
            $meta = self::read_cache_metadata( $meta_file );

            if ( $meta && isset( $meta['expires_at'] ) && $meta['expires_at'] < time() ) {
                // Delete the image file
                $image_file = str_replace( '.meta', '', $meta_file );
                if ( file_exists( $image_file ) ) {
                    unlink( $image_file );
                    $count++;
                }
                // Delete the meta file
                unlink( $meta_file );
            }
        }

        return $count;
    }

    /**
     * Detect image type from magic bytes.
     *
     * @param string $data The image data.
     * @return string The detected content type.
     */
    private static function detect_image_type( $data ) {
        // Check magic bytes
        $magic = substr( $data, 0, 16 );

        // JPEG
        if ( substr( $magic, 0, 3 ) === "\xFF\xD8\xFF" ) {
            return 'image/jpeg';
        }

        // PNG
        if ( substr( $magic, 0, 8 ) === "\x89PNG\r\n\x1a\n" ) {
            return 'image/png';
        }

        // GIF
        if ( substr( $magic, 0, 6 ) === 'GIF87a' || substr( $magic, 0, 6 ) === 'GIF89a' ) {
            return 'image/gif';
        }

        // WebP
        if ( substr( $magic, 0, 4 ) === 'RIFF' && substr( $magic, 8, 4 ) === 'WEBP' ) {
            return 'image/webp';
        }

        // Default to JPEG for Instagram images
        return 'image/jpeg';
    }

    /**
     * Check if the content type is a valid image type.
     *
     * @param string $content_type The content type to check.
     * @return bool True if valid image type.
     */
    private static function is_valid_image_type( $content_type ) {
        $valid_types = array(
            'image/jpeg',
            'image/jpg',
            'image/png',
            'image/gif',
            'image/webp',
        );

        // Extract the main type (ignore charset and other params)
        $type = explode( ';', $content_type )[0];
        $type = trim( strtolower( $type ) );

        return in_array( $type, $valid_types, true );
    }

    /**
     * Return a placeholder image when the original image cannot be fetched.
     *
     * @return void Outputs a placeholder image and exits.
     */
    private static function return_placeholder_image() {
        // Return a simple gray placeholder image
        $placeholder = self::generate_placeholder_svg();

        header( 'Content-Type: image/svg+xml' );
        header( 'Content-Length: ' . strlen( $placeholder ) );
        header( 'Cache-Control: public, max-age=3600' ); // Cache for 1 hour
        header( 'X-Content-Type-Options: nosniff' );

        echo $placeholder;
        exit;
    }

    /**
     * Generate a simple SVG placeholder.
     *
     * @return string The SVG content.
     */
    private static function generate_placeholder_svg() {
        return '<?xml version="1.0" encoding="UTF-8"?>
<svg width="400" height="400" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#f0f0f0"/>
    <g transform="translate(200, 200)">
        <circle r="40" fill="none" stroke="#ccc" stroke-width="3"/>
        <path d="M-15,-15 L15,15 M-15,15 L15,-15" stroke="#ccc" stroke-width="3"/>
    </g>
    <text x="200" y="280" font-family="Arial, sans-serif" font-size="14" fill="#999" text-anchor="middle">
        Image unavailable
    </text>
</svg>';
    }

    /**
     * Generate a proxy URL for an Instagram image (alias).
     *
     * @param string $instagram_url The original Instagram CDN URL.
     * @return string The proxy URL.
     */
    public static function get_proxy_url( $instagram_url ) {
        if ( empty( $instagram_url ) ) {
            return '';
        }

        return add_query_arg(
            'url',
            rawurlencode( $instagram_url ),
            rest_url( self::API_NAMESPACE . self::API_ROUTE )
        );
    }

    /**
     * Generate a proxy URL for an Instagram image.
     *
     * This is the main utility function for converting Instagram CDN URLs
     * to the plugin's proxy endpoint URLs. Used throughout the plugin when
     * outputting image sources.
     *
     * Supports various Instagram URL formats:
     * - JPEG images (scontent*.cdninstagram.com/*.jpg)
     * - WebP images (scontent*.cdninstagram.com/*.webp)
     * - PNG images (scontent*.cdninstagram.com/*.png)
     * - Different CDN subdomains (scontent-iad3-1, scontent-lga3-2, etc.)
     * - FBCDN URLs (instagram.fath1-1.fna.fbcdn.net)
     *
     * @param string $instagram_url The Instagram CDN URL to convert.
     * @return string|false The proxy URL pointing to the plugin's endpoint, or false if URL is invalid.
     */
    public static function generate_proxy_url( $instagram_url ) {
        // Validate input
        if ( empty( $instagram_url ) || ! is_string( $instagram_url ) ) {
            return false;
        }

        // Ensure the URL is valid
        $parsed = wp_parse_url( $instagram_url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return false;
        }

        // Verify scheme is http or https
        $scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : '';
        if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
            return false;
        }

        // Build the proxy URL with the Instagram URL properly encoded
        $proxy_url = add_query_arg(
            'url',
            rawurlencode( $instagram_url ),
            rest_url( self::API_NAMESPACE . self::API_ROUTE )
        );

        return $proxy_url;
    }

    /**
     * Check if a URL is from an Instagram CDN domain.
     *
     * @param string $url The URL to check.
     * @return bool True if the URL is from an Instagram CDN domain.
     */
    public static function is_instagram_cdn_url( $url ) {
        if ( empty( $url ) || ! is_string( $url ) ) {
            return false;
        }

        $parsed = wp_parse_url( $url );
        if ( ! $parsed || empty( $parsed['host'] ) ) {
            return false;
        }

        $host = strtolower( $parsed['host'] );

        foreach ( self::$allowed_domains as $domain ) {
            // Check if host matches or is a subdomain
            if ( $host === $domain || substr( $host, -strlen( '.' . $domain ) ) === '.' . $domain ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate proxy URL only for Instagram CDN URLs.
     *
     * Returns the original URL unchanged if it's not from an Instagram CDN,
     * otherwise generates and returns the proxy URL.
     *
     * @param string $url The URL to potentially proxy.
     * @return string The proxy URL if from Instagram CDN, original URL otherwise.
     */
    public static function maybe_proxy_url( $url ) {
        if ( self::is_instagram_cdn_url( $url ) ) {
            $proxy = self::generate_proxy_url( $url );
            return $proxy ? $proxy : $url;
        }
        return $url;
    }

    /**
     * Decode the Instagram URL from a proxy URL.
     *
     * Extracts and decodes the original Instagram CDN URL from a proxy URL.
     *
     * @param string $proxy_url The proxy URL to decode.
     * @return string|false The original Instagram URL, or false if not a valid proxy URL.
     */
    public static function decode_proxy_url( $proxy_url ) {
        if ( empty( $proxy_url ) || ! is_string( $proxy_url ) ) {
            return false;
        }

        $parsed = wp_parse_url( $proxy_url );
        if ( ! $parsed || empty( $parsed['query'] ) ) {
            return false;
        }

        parse_str( $parsed['query'], $query_vars );

        if ( empty( $query_vars['url'] ) ) {
            return false;
        }

        return rawurldecode( $query_vars['url'] );
    }

    /**
     * Get the proxy endpoint URL (without any image URL parameter).
     *
     * @return string The base proxy endpoint URL.
     */
    public static function get_proxy_endpoint() {
        return rest_url( self::API_NAMESPACE . self::API_ROUTE );
    }
}

// Initialize the image proxy
BWG_IGF_Image_Proxy::init();
