<?php
/**
 * Plugin Name: Nera – Vote
 * Plugin URI: https://github.com/Nera-Marketing/nera-vote-plugin
 * Description: Time-boxed votes for members. The team creates a Vote with a list of Options and a start and end date, pastes its shortcode into any page, and signed-in members tick the Options they like while it is open. Results are ranked in the admin area.
 * Version: 1.0.0
 * Author: Nera
 * Text Domain: nera-vote
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * @package Nera_Vote
 */

use YahnisElsts\PluginUpdateChecker\v5p5\Vcs\GitHubApi;

defined( 'ABSPATH' ) || exit;

define( 'NERA_VOTE_VERSION', '1.0.0' );
define( 'NERA_VOTE_PLUGIN_FILE', __FILE__ );
define( 'NERA_VOTE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NERA_VOTE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * GitHub updates (Plugin Update Checker v5.5). On by default when `lib/plugin-update-checker/load-v5p5.php` exists.
 * Same updater as the other Nera plugins.
 *
 * Disable:      define( 'NERA_VOTE_DISABLE_GITHUB_UPDATES', true );
 * Private repo: define( 'NERA_VOTE_GITHUB_TOKEN', 'ghp_...' );
 * Custom URL:   define( 'NERA_VOTE_GITHUB_REPO_URL', 'https://github.com/Owner/repo/' );  (or filter nera_vote_github_repo_url)
 *
 * PUC reads the `Version` header from the GitHub ref it selects. Bump `Version` + `NERA_VOTE_VERSION` for every
 * release, then tag/push to match. A custom setReleaseFilter (always true) + maxReleases > 1 makes GitHubApi
 * use the paginated /releases endpoint instead of /latest (which 404s without a GitHub "latest" release).
 * enableReleaseAssets() prefers the attached zip over the tag tarball.
 *
 * @link https://github.com/YahnisElsts/plugin-update-checker
 */
if ( ! defined( 'NERA_VOTE_DISABLE_GITHUB_UPDATES' ) || ! NERA_VOTE_DISABLE_GITHUB_UPDATES ) {
	$nera_vote_github_repo_default = 'https://github.com/Nera-Marketing/nera-vote-plugin/';
	if ( defined( 'NERA_VOTE_GITHUB_REPO_URL' ) && is_string( NERA_VOTE_GITHUB_REPO_URL ) && NERA_VOTE_GITHUB_REPO_URL !== '' ) {
		$nera_vote_github_repo_default = NERA_VOTE_GITHUB_REPO_URL;
	}
	$nera_vote_github_repo = apply_filters( 'nera_vote_github_repo_url', $nera_vote_github_repo_default );

	$nera_vote_puc_loader = NERA_VOTE_PLUGIN_DIR . 'lib/plugin-update-checker/load-v5p5.php';
	if ( is_readable( $nera_vote_puc_loader ) ) {
		require_once $nera_vote_puc_loader;
		// Fourth argument: check period in hours (PUC default is 12).
		$nera_vote_update_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$nera_vote_github_repo,
			__FILE__,
			'nera-vote-plugin',
			6
		);
		$nera_vote_update_checker->setBranch( 'main' );

		if ( defined( 'NERA_VOTE_GITHUB_TOKEN' ) && is_string( NERA_VOTE_GITHUB_TOKEN ) && NERA_VOTE_GITHUB_TOKEN !== '' ) {
			$nera_vote_update_checker->setAuthentication( NERA_VOTE_GITHUB_TOKEN );
		}

		// GitHub-hosted updates carry no plugin icon, so the Dashboard → Updates and
		// Plugins screens show a blank logo. Inject the bundled logo.png as the icon.
		$nera_vote_update_checker->addResultFilter(
			static function ( $plugin_info ) {
				if ( is_object( $plugin_info ) && is_readable( NERA_VOTE_PLUGIN_DIR . 'logo.png' ) ) {
					$logo                = NERA_VOTE_PLUGIN_URL . 'logo.png';
					$plugin_info->icons = array(
						'1x'      => $logo,
						'2x'      => $logo,
						'default' => $logo,
					);
				}
				return $plugin_info;
			}
		);

		$nera_vote_puc_vcs = $nera_vote_update_checker->getVcsApi();
		if ( $nera_vote_puc_vcs instanceof GitHubApi ) {
			$nera_vote_puc_vcs->setReleaseFilter(
				static function ( $version_number, $release_object ) {
					unset( $version_number, $release_object );
					return true;
				},
				\YahnisElsts\PluginUpdateChecker\v5p5\Vcs\Api::RELEASE_FILTER_SKIP_PRERELEASE,
				20
			);
			$nera_vote_puc_vcs->enableReleaseAssets();
		}
	}
}

require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-cpt.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-votes.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-results.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-assets.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-suggestions.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-settings.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-shortcodes.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-page.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-account.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-rest.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-privacy.php';
require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-activator.php';

register_activation_hook( __FILE__, array( 'Nera_Vote_Activator', 'activate' ) );

if ( is_admin() ) {
	require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-admin.php';
	require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-suggestions-admin.php';
	require_once NERA_VOTE_PLUGIN_DIR . 'includes/class-demo.php';
	Nera_Vote_Admin::init();
	Nera_Vote_Suggestions_Admin::init();
	Nera_Vote_Settings::init();
	Nera_Vote_Demo::init();
}

Nera_Vote_Activator::init();
Nera_Vote_CPT::init();
Nera_Vote_Assets::init();
Nera_Vote_Suggestions::init();
Nera_Vote_Shortcodes::init();
Nera_Vote_Page::init();
Nera_Vote_Account::init();
Nera_Vote_REST::init();
Nera_Vote_Privacy::init();

add_action(
	'init',
	static function () {
		load_plugin_textdomain( 'nera-vote', false, dirname( plugin_basename( NERA_VOTE_PLUGIN_FILE ) ) . '/languages' );
	}
);
