<?php
/*
Plugin Name: Post Date Randomizer
Description: Simple plugin that bulk changes the date/s of all published posts to any random date in the past or future. Visit "Post Date Randomizer" setting in the dasboard meny to setup date range and post type.
Version:     1.3.0
Author:      Anty
Author URI:  https://profiles.wordpress.org/wellbeingtips
License:     GPL3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Adding settings

add_action( 'admin_menu', 'pdrdaddSubMenuPage' );
function pdrdaddSubMenuPage() {
	add_menu_page(
		__( 'Randomizer', 'pdr_rnd' ),
		__( 'Randomizer', 'pdr_rnd' ),
		'manage_options',
		'pdr_random_posts',
		'pdr_settings',
		'dashicons-calendar-alt'

	);

}

function pdr_settings() {
	?>
    <div class="wrap">
        <h1>Post Date Randomizer</h1>
        <form method="post" action="options.php">
			<?php
			settings_fields( "pdr_rnd_section" );
			do_settings_sections( "pdr_rnd_options" );
			submit_button();
			?>
        </form>
        <a class="button button-secondary"
           href="<?php echo wp_nonce_url( admin_url( 'options-general.php?page=pdr_random_posts&randomize=yes' ), 'pdr_rd_randomize', 'pdr_rd_nonce' ) ?>">Randomize</a>
    </div>
	<?php
}

add_action( "admin_init", "pdr_settings_fields" );
function pdr_settings_fields() {
	add_settings_section( "pdr_rnd_section", "All Settings", null, "pdr_rnd_options" );


	add_settings_field( "pdr_date1", "Start Date:", "pdr_date1_element", "pdr_rnd_options", "pdr_rnd_section" );
	add_settings_field( "pdr_date2", "End Date", "pdr_date2_element", "pdr_rnd_options", "pdr_rnd_section" );
	add_settings_field( "pdr_post_type", "Select Post Type", "pdr_post_element", "pdr_rnd_options", "pdr_rnd_section" );
	add_settings_field( "pdr_set_modified_date", "Set modified date also", "pdr_set_modified_date_element", "pdr_rnd_options", "pdr_rnd_section" );


	register_setting( "pdr_rnd_section", "pdr_date1" );
	register_setting( "pdr_rnd_section", "pdr_date2" );
	register_setting( "pdr_rnd_section", "pdr_post_type" );
	register_setting( "pdr_rnd_section", "pdr_set_modified_date" );
}

function pdr_date1_element() {
	$date_format = 'Y-m-d H:i:s';
	$date1       = get_option( 'pdr_date1', date( $date_format, strtotime( "-1 year", time() ) ) );
	?>

    <input name="pdr_date1" type="text" id="pdr_date1" value="<?php echo $date1 ?>" class="regular-text code">
	<?php
}

function pdr_date2_element() {
	$date_format = 'Y-m-d H:i:s';
	$date2       = get_option( 'pdr_date2', date( $date_format, time() ) );
	?>

    <input name="pdr_date2" type="text" id="pdr_date2" value="<?php echo $date2 ?>" class="regular-text code">
	<?php
}

function pdr_post_element() {
	$default_type = 'post';
	$cr_type      = get_option( 'pdr_post_type', $default_type );
	$builtin      = [
		'post',
		'page',
	];
	$cpts         = get_post_types( [
		'public'   => true,
		'_builtin' => false,
	] );
	$post_types   = array_merge( $builtin, $cpts );
	?>
    <select name="pdr_post_type" id="pdr_post_type">
		<?php
		foreach ( $post_types as $cpt ) {
			?>
            <option value="<?php echo $cpt ?>" <?php selected( $cpt, $cr_type ); ?>><?php echo $cpt ?></option>
		<?php } ?>
    </select>

	<?php
}

function pdr_set_modified_date_element() {
	$checked = get_option( 'pdr_set_modified_date', 1 ) ? 'checked="checked" ' : 0; // default to true
	?>
    <input name="pdr_set_modified_date" type="checkbox" id="pdr_set_modified_date" value="1" <?php echo $checked ?>/>
	<?php
}


add_action( 'init', 'pdr_randomize' );
function pdr_randomize() {
	$randomize = isset( $_REQUEST['randomize'] ) ? $_REQUEST['randomize'] : '';
	if ( isset( $_GET['pdr_rd_nonce'] ) && wp_verify_nonce( $_GET['pdr_rd_nonce'], 'pdr_rd_randomize' ) ) {
		if ( $randomize == 'yes' ) {
			$date_format       = 'Y-m-d H:i:s';
			$date1             = get_option( 'pdr_date1', date( $date_format, strtotime( "-1 year", time() ) ) );
			$date2             = get_option( 'pdr_date2', date( $date_format, time() ) );
			$cr_type           = get_option( 'pdr_post_type' );
			$set_modified_date = get_option( 'pdr_set_modified_date', 1 );
			//* Get all the posts
			$posts = get_posts( [ 'numberposts' => - 1, 'post_status' => 'any', 'post_type' => $cr_type ] );
			// Also set the modified date?
			if ( $set_modified_date ) {
				add_filter( 'wp_insert_post_data', 'pdr_set_post_modified_to_published' );
			}
			foreach ( $posts as $post ) {

				//* Generate a random post dates
				$random_date = mt_rand( strtotime( $date1 ), strtotime( $date2 ) );
				//* Format the date that WordPress likes
				$post_date = date( $date_format, $random_date );

				// We only want to update the post date
				$update = [
					'ID'            => $post->ID,
					'post_date'     => $post_date,
					'post_date_gmt' => null,
				];

				//* Update the post
				wp_update_post( $update );

			}
			// Stop setting the modfied date
			if ( $set_modified_date ) {
				remove_filter( 'wp_insert_post_data', 'pdr_set_post_modified_to_published' );
			}
			// Notice of sucess after action
			add_action( 'admin_notices', 'pdr_acf_notice' );
		}
	}
}

function pdr_set_post_modified_to_published( $data ) {
	if ( isset( $data['post_date'] ) ) {
		$data['post_modified'] = $data['post_date'];
	}

	if ( isset( $data['post_date_gmt'] ) ) {
		$data['post_modified_gmt'] = $data['post_date_gmt'];
	}

	return $data;
}

function pdr_acf_notice() {
	?>
    <div class="success notice">
        <p><?php _e( 'Posts Randomized.', 'pdr_rnd' ); ?></p>
    </div>
	<?php
}
