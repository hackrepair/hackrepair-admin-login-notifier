<?php
/*
Plugin Name: The Hack Repair Guy's Admin Login Notifier
Plugin URI: http://wordpress.org/extend/plugins/hackrepair-admin-login-notifier/
Description: Receive an email every time an admin logs in to your website
Author: Jim Walker, The Hack Repair Guy
Version: 0.1.0
Text Domain: hackrepair-admin-login-notifier
Author URI: http://hackrepair.com/hackrepair-admin-login-notifier/
*/

add_action('plugins_loaded', array( 'HackRepair_Admin_Login_Notifier', 'init' ) );

class HackRepair_Admin_Login_Notifier {
	public static $options = array(
		'admin_email' => '',
		'capability' => 'manage_options',
	);

	public static function init() {
		self::$options['admin_email'] = get_option('admin_email');
		add_action( 'wp_login', array( 'HackRepair_Admin_Login_Notifier', 'login' ), 10, 2 );
		load_plugin_textdomain( 'hackrepair-admin-login-notifier', FALSE, basename( dirname( __FILE__ ) ) );
	}
	public static function login( $user_login, $user ) {
		$capability = apply_filters( 'hackrepair_admin_login_notifier_capability', self::$options['capability'] );
		if ( $user->has_cap( $capability ) ) {
			self::_notify( $user );
		}
	}

	private static function _notify( $user ) {
		$data = array(
			'subject' => __( '%domain% - Admin %user_login% logged in at %time%', 'hackrepair-admin-login-notifier' ),
			'content' => __( "User '%user_login%' has successfully logged in at %domain% from %ip% on %date%, at %time% \r\n\r\nAdmin notification provided by %plugin_title_full%, %plugin_link%", 'hackrepair-admin-login-notifier' ),
		);
		$data['subject'] = apply_filters( 'hackrepair_admin_login_notifier_subject', $data['subject'], $user );
		$data['content'] = apply_filters( 'hackrepair_admin_login_notifier_content', $data['content'], $user );

		$replace = array(
			'date' 				=> date( get_option( 'date_format' ) ),
			'time' 				=> date( get_option( 'time_format' ) ),
			'datetime' 			=> date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'ip'				=> $_SERVER['REMOTE_ADDR'],
		);

		$data = self::_replace_data( $data, $user, $replace );

		wp_mail( self::$options['admin_email'], $data['subject'], $data['content'] );
	}

	private static function _replace_data( $content, $user, $data = array() ) {
		$defaults = array(
			'plugin_title'  	=> __( 'Admin Login Notifier', 'hackrepair-admin-login-notifier' ),
			'plugin_title_full' => __( "The Hack Repair Guy's Admin Login Notifier", 'hackrepair-admin-login-notifier' ),
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