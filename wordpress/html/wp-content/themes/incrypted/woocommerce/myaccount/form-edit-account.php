<?php
/**
 * Edit account form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/myaccount/form-edit-account.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 9.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hook - woocommerce_before_edit_account_form.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_before_edit_account_form' );
$user_id    = get_current_user_id();
?>

<div class="np-settings np-account-edit">
	<div class="np-settings__card">
		<form class="woocommerce-EditAccountForm edit-account" action="" method="post" <?php do_action( 'woocommerce_edit_account_form_tag' ); ?> >

		<?php do_action( 'woocommerce_edit_account_form_start' ); ?>

		<div class="np-settings__section">
			<h2 class="np-settings__heading">
				<span class="np-settings__heading-icon" aria-hidden="true">
					<svg viewBox="0 0 24 24" focusable="false" aria-hidden="true">
						<path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3.87 0-7 2.24-7 5v1h14v-1c0-2.76-3.13-5-7-5Z" fill="currentColor"/>
					</svg>
				</span>
				<?php esc_html_e( 'Profile Info', 'incrypted' ); ?>
			</h2>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide np-settings__field">
				<label for="account_display_name"><?php esc_html_e( 'Display name', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<div class="np-settings__input-wrap">
					<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="account_display_name" id="account_display_name" aria-describedby="account_display_name_description" value="<?php echo esc_attr( $user->display_name ); ?>" aria-required="true" />
				</div>
				<span id="account_display_name_description" class="np-settings__helper"><em><?php esc_html_e( 'This will be how your name will be displayed in the account section and in reviews', 'woocommerce' ); ?></em></span>
			</p>
			<div class="clear"></div>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide np-settings__field">
				<label for="account_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required" aria-hidden="true">*</span></label>
				<div class="np-settings__input-wrap">
					<input type="email" class="woocommerce-Input woocommerce-Input--email input-text" name="account_email" id="account_email" autocomplete="email" value="<?php echo esc_attr( $user->user_email ); ?>" aria-required="true" />
				</div>
			</p>

			<?php
				/**
				 * Hook where additional fields should be rendered.
				 *
				 * @since 8.7.0
				 */
				do_action( 'woocommerce_edit_account_form_fields' );
			?>
			<button type="button" class="np-settings__toggle-security" aria-expanded="false" aria-controls="np-security">
				<?php esc_html_e( 'Change password', 'incrypted' ); ?>
			</button>
		</div>

		<div class="np-settings__section np-settings__section--security" id="np-security">
			<h2 class="np-settings__heading"><?php esc_html_e( 'Security', 'incrypted' ); ?></h2>

			<fieldset class="np-settings__fieldset">
				<legend class="np-settings__legend"><?php esc_html_e( 'Security', 'incrypted' ); ?></legend>

				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide np-settings__field np-settings__field--password">
					<label for="password_current"><?php esc_html_e( 'Current password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
					<div class="np-settings__input-wrap">
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_current" id="password_current" autocomplete="off" />
						<button type="button" class="np-settings__toggle" data-toggle="password_current" aria-label="<?php esc_attr_e( 'Show password', 'incrypted' ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M12 5c5.05 0 9.27 3.11 11 7-1.73 3.89-5.95 7-11 7S2.73 15.89 1 12c1.73-3.89 5.95-7 11-7Zm0 2c-3.49 0-6.6 2-8.22 5 1.62 3 4.73 5 8.22 5s6.6-2 8.22-5C18.6 9 15.49 7 12 7Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z" fill="currentColor"/>
							</svg>
						</button>
					</div>
				</p>
				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide np-settings__field np-settings__field--password">
					<label for="password_1"><?php esc_html_e( 'New password (leave blank to leave unchanged)', 'woocommerce' ); ?></label>
					<div class="np-settings__input-wrap">
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_1" id="password_1" autocomplete="off" />
						<button type="button" class="np-settings__toggle" data-toggle="password_1" aria-label="<?php esc_attr_e( 'Show password', 'incrypted' ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M12 5c5.05 0 9.27 3.11 11 7-1.73 3.89-5.95 7-11 7S2.73 15.89 1 12c1.73-3.89 5.95-7 11-7Zm0 2c-3.49 0-6.6 2-8.22 5 1.62 3 4.73 5 8.22 5s6.6-2 8.22-5C18.6 9 15.49 7 12 7Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z" fill="currentColor"/>
							</svg>
						</button>
					</div>
				</p>
				<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide np-settings__field np-settings__field--password">
					<label for="password_2"><?php esc_html_e( 'Confirm new password', 'woocommerce' ); ?></label>
					<div class="np-settings__input-wrap">
						<input type="password" class="woocommerce-Input woocommerce-Input--password input-text" name="password_2" id="password_2" autocomplete="off" />
						<button type="button" class="np-settings__toggle" data-toggle="password_2" aria-label="<?php esc_attr_e( 'Show password', 'incrypted' ); ?>">
							<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
								<path d="M12 5c5.05 0 9.27 3.11 11 7-1.73 3.89-5.95 7-11 7S2.73 15.89 1 12c1.73-3.89 5.95-7 11-7Zm0 2c-3.49 0-6.6 2-8.22 5 1.62 3 4.73 5 8.22 5s6.6-2 8.22-5C18.6 9 15.49 7 12 7Zm0 2.5A2.5 2.5 0 1 1 9.5 12 2.5 2.5 0 0 1 12 9.5Z" fill="currentColor"/>
							</svg>
						</button>
					</div>
				</p>
			</fieldset>
			<div class="clear"></div>
		</div>

		<?php
			/**
			 * My Account edit account form.
			 *
			 * @since 2.6.0
			 */
			do_action( 'woocommerce_edit_account_form' );
		?>

		<p class="np-settings__actions">
			<?php wp_nonce_field( 'save_account_details', 'save-account-details-nonce' ); ?>
			<button type="submit" class="woocommerce-Button button np-settings__submit<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="save_account_details" value="<?php esc_attr_e( 'Save changes', 'woocommerce' ); ?>"><?php esc_html_e( 'Save changes', 'woocommerce' ); ?></button>
			<input type="hidden" name="action" value="save_account_details" />
		</p>

		<?php do_action( 'woocommerce_edit_account_form_end' ); ?>
	</form>
	</div>
</div>

<?php do_action( 'woocommerce_after_edit_account_form' ); ?>
