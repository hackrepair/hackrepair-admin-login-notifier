<?php
/*
Plugin Name: The Hack Repair Guy's Admin Login Notifier
Plugin URI: https://wordpress.org/plugins/the-hack-repair-guys-admin-login-notifier/
Description: Receive email notification each time an Administrator logs into your WordPress dashboard.
Author: Jim Walker, The Hack Repair Guy
Version: 2.0.2
Author URI: http://hackrepair.com/hackrepair-admin-login-notifier/
*/

add_action('plugins_loaded', array( 'HackRepair_Admin_Login_Notifier', 'init' ) );

class HackRepair_Admin_Login_Notifier {
	private static $plugin_dir = '';
	public static $options = array(
		'notify' 		=> array(-1),
		'capability' 	=> 'manage_options',
		'exclude_ip'	=> '',
	);

	public static function init() {
		self::$plugin_dir = plugin_dir_path( __FILE__ );
		$options = get_option( 'hackrepair-admin-login-notifier_options' );
		self::$options = wp_parse_args( $options, self::$options );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( 'HackRepair_Admin_Login_Notifier', 'admin_init'  ) );
		}
		add_filter( 'plugin_action_links', 			array( 'HackRepair_Admin_Login_Notifier', 'action_link' ), 10, 4 );

		add_action( 'wp_login', array( 'HackRepair_Admin_Login_Notifier', 'login' ), 10, 2 );
		load_plugin_textdomain( 'the-hack-repair-guys-admin-login-notifier', FALSE, basename( dirname( __FILE__ ) ) );
	}

	public static function admin_init() {
		global $pagenow;
		require_once ( self::$plugin_dir . 'includes/options.php' );
		$user_list = array(
			-1 => sprintf( __( 'WordPress Notification Email (%s)', 'the-hack-repair-guys-admin-login-notifier' ), get_option( 'admin_email' ) ),
		);
		$users = get_users(array( 'role' => 'administrator' ) );
		foreach ($users as $key => $user) {
			$user_list[$user->ID] = "{$user->data->display_name} ( {$user->data->user_email} )";
		}
		$fields =   array(
			"general" => array(
				'title' => '',
				'callback' => '',
				'options' => array(
					'notify' => array(
						'title'=>__( 'Email Notifications to:', 'the-hack-repair-guys-admin-login-notifier' ),
						'args' => array (
							'values' => $user_list,
							'description' => __( 'The email contacts selected above will be notified each time an administrator logs into this dashboard', 'the-hack-repair-guys-admin-login-notifier' ),
						),
						'callback' => 'checklist',
					),
					'exclude_ip' => array(
						'title'=>__( 'Exclude IP addresses', 'the-hack-repair-guys-admin-login-notifier' ),
						'args' => array (
							// 'values' => $user_list,
							'description' => __( 'A comma-separated list of IP addresses that will not trigger a notification email.', 'the-hack-repair-guys-admin-login-notifier' ),
						),
						'callback' => 'text',
					),
				),
			),
		);
		$tabs = array();
		HackRepair_Admin_Login_Notifier_Options::init(
			'hackrepair-admin-login-notifier',
			__( 'Admin Login Notifier',          'the-hack-repair-guys-admin-login-notifier' ),
			__( "The Hack Repair Guy's Admin Login Notifier: Settings", 'the-hack-repair-guys-admin-login-notifier' ),
			$fields,
			$tabs,
			'HackRepair_Admin_Login_Notifier',
			'hackrepair-admin-login-notifier-settings'
		);
	}

	public static function action_link($actions, $plugin_file, $plugin_data, $context) {
		if ( 'the-hack-repair-guys-admin-login-notifier/the-hack-repair-guys-admin-login-notifier.php' == $plugin_file ) {
			$actions['settings'] = '<a href="' . admin_url( 'options-general.php?page=hackrepair-admin-login-notifier-settings' ) . '" aria-label="' . esc_attr( sprintf( __( '%s Settings', 'the-hack-repair-guys-admin-login-notifier' ), $plugin_data['Name'] ) ) . '">' . __( 'Settings', 'the-hack-repair-guys-admin-login-notifier' ) . '</a>';
		}
		return $actions;
	}



	public static function login( $user_login, $user ) {
		$capability = apply_filters( 'hackrepair_admin_login_notifier_capability', self::$options['capability'] );
		$ips = self::$options['exclude_ip'];
		$ips = explode( ',', $ips );
		if ( $user->has_cap( $capability ) && !in_array( $_SERVER['REMOTE_ADDR'], $ips ) ) {
			self::_notify( $user );
		}
	}

	private static function _notify( $user ) {
		$data = array(
			'subject' => __( '%domain% - Admin %user_login% logged in at %time%', 'the-hack-repair-guys-admin-login-notifier' ),
			'content' => __( "User '%user_login%' has successfully logged into http://%domain% from %ip%, %date%, at %time% \r\n\r\nAdmin notification provided by %plugin_title_full%, %plugin_link%", 'the-hack-repair-guys-admin-login-notifier' ),
		);
		$data['subject'] = apply_filters( 'hackrepair_admin_login_notifier_subject', $data['subject'], $user );
		$data['content'] = apply_filters( 'hackrepair_admin_login_notifier_content', $data['content'], $user );

		$replace = array(
			'date' 				=> current_time( get_option( 'date_format' ) ),
			'time' 				=> current_time( get_option( 'time_format' ) ),
			'datetime' 			=> current_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'ip'				=> $_SERVER['REMOTE_ADDR'],
		);

		$data = self::_replace_data( $data, $user, $replace );

		foreach ( self::$options['notify'] as $user_id ) {
			if ( -1 == $user_id ) {
				$user_email = get_option( "admin_email" );
			} else {
				$user = get_user_by( 'ID', $user_id );
				$user_email = $user->data->user_email;
			}
			wp_mail( $user_email, $data['subject'], $data['content'] );
		}
	}

	private static function _replace_data( $content, $user, $data = array() ) {
		$defaults = array(
			'plugin_title'  	=> __( 'Admin Login Notifier', 'the-hack-repair-guys-admin-login-notifier' ),
			'plugin_title_full' => __( "The Hack Repair Guy's Admin Login Notifier", 'the-hack-repair-guys-admin-login-notifier' ),
			'plugin_link'		=> 'https://hackrepair.com/about/hackrepair-admin-login-notifier',
			'url' 				=> get_bloginfo('url'),
			'domain'			=> parse_url( get_bloginfo('url'), PHP_URL_HOST ),
			'title' 			=> get_bloginfo('title'),
			'description' 		=> get_bloginfo('description'),
		);
		$data = wp_parse_args( $data, $defaults );
		$data = wp_parse_args( $user->to_array(), $data );
		$data = apply_filters( 'hackrepair_admin_login_notifier_data', $data, $user );
		$replace = array();
		foreach ( $data as $key => $value ) {
			$replace["%{$key}%"] = $value;
		}
		$content = str_replace( array_keys( $replace ), array_values( $replace ), $content );
		return  $content;
	}
}
