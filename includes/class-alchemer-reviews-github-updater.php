<?php
/**
 * GitHub release updater for Alchemer Reviews.
 *
 * @package AlchemerReviews
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Adds WordPress plugin update support backed by GitHub Releases.
 */
class Alchemer_Reviews_GitHub_Updater {

    const TRANSIENT_KEY = 'alchemer_reviews_github_latest_release';
    const CACHE_TTL     = 6 * HOUR_IN_SECONDS;

    /**
     * Absolute plugin file path.
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Plugin basename used by WordPress update transients.
     *
     * @var string
     */
    private $plugin_basename;

    /**
     * Plugin slug.
     *
     * @var string
     */
    private $slug = 'alchemer-reviews';

    /**
     * GitHub repository in owner/name format.
     *
     * @var string
     */
    private $repo;

    /**
     * Preferred release asset filename.
     *
     * @var string
     */
    private $asset_name;

    /**
     * GitHub repository homepage.
     *
     * @var string
     */
    private $repo_url;

    /**
     * Latest release API endpoint.
     *
     * @var string
     */
    private $api_url;

    /**
     * Constructor.
     *
     * @param string $plugin_file Absolute plugin file path.
     */
    public function __construct( $plugin_file ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->repo            = defined( 'ALCHEMER_REVIEWS_GITHUB_REPO' ) ? ALCHEMER_REVIEWS_GITHUB_REPO : 'braudypedrosa/alchemer-reviews';
        $this->asset_name      = defined( 'ALCHEMER_REVIEWS_GITHUB_ASSET_NAME' ) ? ALCHEMER_REVIEWS_GITHUB_ASSET_NAME : 'alchemer-reviews.zip';
        $this->repo_url        = 'https://github.com/' . $this->repo;
        $this->api_url         = 'https://api.github.com/repos/braudypedrosa/alchemer-reviews/releases/latest';
    }

    /**
     * Register update hooks.
     *
     * @return void
     */
    public function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
        add_action( 'upgrader_process_complete', array( $this, 'clear_release_cache' ), 10, 2 );
    }

    /**
     * Inject GitHub release updates into WordPress' plugin update transient.
     *
     * @param object $transient Update transient.
     * @return object
     */
    public function check_for_update( $transient ) {
        if ( ! is_object( $transient ) ) {
            $transient = new stdClass();
        }

        if ( empty( $transient->checked ) || empty( $transient->checked[ $this->plugin_basename ] ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if ( empty( $release ) ) {
            return $transient;
        }

        $latest_version = $this->normalize_version( $release['tag_name'] );
        if ( empty( $latest_version ) || ! version_compare( $latest_version, ALCHEMER_REVIEWS_VERSION, '>' ) ) {
            return $transient;
        }

        $package_url = $this->get_download_url( $release );
        if ( empty( $package_url ) ) {
            return $transient;
        }

        $transient->response[ $this->plugin_basename ] = (object) array(
            'id'          => $this->repo_url,
            'slug'        => $this->slug,
            'plugin'      => $this->plugin_basename,
            'new_version' => $latest_version,
            'url'         => $this->get_release_url( $release ),
            'package'     => $package_url,
            'tested'      => get_bloginfo( 'version' ),
        );

        return $transient;
    }

    /**
     * Provide plugin details for the View version details modal.
     *
     * @param false|object|array $result Existing API result.
     * @param string             $action API action.
     * @param object             $args   API arguments.
     * @return false|object|array
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->slug !== $args->slug ) {
            return $result;
        }

        $release = $this->get_latest_release();
        if ( empty( $release ) ) {
            return $result;
        }

        $latest_version = $this->normalize_version( $release['tag_name'] );
        $release_body   = ! empty( $release['body'] ) ? wp_kses_post( wpautop( $release['body'] ) ) : '<p>No changelog was provided for this release.</p>';
        $download_url   = $this->get_download_url( $release );

        return (object) array(
            'name'          => 'Alchemer Reviews',
            'slug'          => $this->slug,
            'version'       => $latest_version,
            'author'        => '<a href="https://github.com/braudypedrosa">Braudy Pedrosa</a>',
            'homepage'      => $this->repo_url,
            'download_link' => $download_url,
            'last_updated'  => ! empty( $release['published_at'] ) ? $release['published_at'] : '',
            'sections'      => array(
                'description' => '<p>Import and manage Alchemer survey responses as WordPress reviews.</p>',
                'changelog'   => $release_body,
            ),
        );
    }

    /**
     * Clear cached release data after this plugin is updated.
     *
     * @param WP_Upgrader $upgrader Upgrader instance.
     * @param array       $options  Upgrader options.
     * @return void
     */
    public function clear_release_cache( $upgrader, $options ) {
        if ( empty( $options['action'] ) || 'update' !== $options['action'] || empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
            return;
        }

        if ( ! empty( $options['plugins'] ) && in_array( $this->plugin_basename, $options['plugins'], true ) ) {
            delete_site_transient( self::TRANSIENT_KEY );
        }
    }

    /**
     * Fetch and cache the latest GitHub release.
     *
     * @param bool $force_refresh Whether to bypass the cached release.
     * @return array|false
     */
    public function get_latest_release( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached_release = get_site_transient( self::TRANSIENT_KEY );
            if ( is_array( $cached_release ) ) {
                return $cached_release;
            }
        }

        $response = wp_remote_get(
            $this->api_url,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'Alchemer-Reviews-WordPress-Updater',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $status_code ) {
            return false;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $release ) || empty( $release['tag_name'] ) || ! empty( $release['draft'] ) || ! empty( $release['prerelease'] ) ) {
            return false;
        }

        set_site_transient( self::TRANSIENT_KEY, $release, self::CACHE_TTL );

        return $release;
    }

    /**
     * Normalize a GitHub release tag into a semantic version string.
     *
     * @param string $tag_name Release tag.
     * @return string
     */
    private function normalize_version( $tag_name ) {
        return ltrim( trim( (string) $tag_name ), 'vV' );
    }

    /**
     * Get the best release download URL.
     *
     * @param array $release GitHub release data.
     * @return string
     */
    private function get_download_url( $release ) {
        if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
            foreach ( $release['assets'] as $asset ) {
                if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
                    continue;
                }

                if ( $this->asset_name === $asset['name'] ) {
                    return $asset['browser_download_url'];
                }
            }

            foreach ( $release['assets'] as $asset ) {
                if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
                    continue;
                }

                if ( '.zip' === substr( $asset['name'], -4 ) ) {
                    return $asset['browser_download_url'];
                }
            }
        }

        return ! empty( $release['zipball_url'] ) ? $release['zipball_url'] : '';
    }

    /**
     * Get the public release URL.
     *
     * @param array $release GitHub release data.
     * @return string
     */
    private function get_release_url( $release ) {
        return ! empty( $release['html_url'] ) ? $release['html_url'] : $this->repo_url;
    }
}
