<?php
	/*
		Plugin Name: Rezgo Tour Posts
		Plugin URI: http://wordpress.org/plugins/rezgo-tour-posts/
		Description: Create posts for each of your Rezgo tours using the custom post type 'tours'. The tours will then be available for inclusion in XML sitemaps.
		Version: 1.2
		Author: Rezgo.
		Author URI: http://www.rezgo.com
		License: Modified BSD
	*/
	
	/*  
		Author: John McDonald
				
		Copyright (c) 2015, Rezgo (A Division of Sentias Software Corp.)
		All rights reserved. (email: support@rezgo.com)
		
		Redistribution and use in source form, with or without modification,
		is permitted provided that the following conditions are met:
		
		* Redistributions of source code must retain the above copyright
		notice, this list of conditions and the following disclaimer.
		* Neither the name of Rezgo, Sentias Software Corp, nor the names of
		its contributors may be used to endorse or promote products derived
		from this software without specific prior written permission.
		* Source code is provided for the exclusive use of Rezgo members who
		wish to connect to their Rezgo XMP API.  Modifications to source code
		may not be used to connect to competing software without specific
		prior written permission.
		
		THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
		"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
		LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
		A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
		HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
		SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
		LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
		DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
		THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
		(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
		OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
	
	*/
	
	// Rezgo Tour Posts constants
	if ( ! defined( 'RTP_PLUGIN_URL' ) )
		define( 'RTP_PLUGIN_URL', WP_PLUGIN_URL . '/rezgo-tour-posts' );
	
	if ( ! defined( 'RTP_TOUR_PAGE' ) )	
		define( 'RTP_TOUR_PAGE', get_option('rtp_tour_page') );

			
	add_action( 'init', 'rtp_plugin_init' );	
	
	function rtp_plugin_init() {
		
		if ( ( RTP_TOUR_PAGE != '' ) && ( RTP_TOUR_PAGE != 'tours' ) ) {
			
			register_post_type (
				'tour', array(	
					'public' => true,
					'show_ui' => false,
					'show_in_menu' => false,
					'rewrite' => array('slug' => RTP_TOUR_PAGE),
					'query_var' => true
				) 
			);		
				
		} else {
			
			register_post_type (
				'tour', array(	
					'public' => true,
					'show_ui' => false,
					'show_in_menu' => false,
					'rewrite' => array('slug' => 'tours'),
					'query_var' => true
				) 
			);			
			
		}
		
	}
		
	
	register_activation_hook( __FILE__, 'rtp_activate' );	
	
	function rtp_activate() {
		flush_rewrite_rules();
	}
	
	// add settings link on plugin page
	function rtp_settings_link($links) { 
		$settings_link = '<a href="' . admin_url( 'admin.php?page=rezgo-rtp-settings' )  . '">Settings</a>'; 
		array_unshift($links, $settings_link); 
		return $links; 
	}
	 
	$plugin = plugin_basename(__FILE__); 
	add_filter("plugin_action_links_$plugin", 'rtp_settings_link' );

		
	// register settings
	add_action('admin_init', 'rtp_register_settings');
	
	function rtp_register_settings() { 
		register_setting('rtp_options', 'rtp_tour_page');
		wp_register_style('rtp_settings_css', RTP_PLUGIN_URL . '/rtp-settings.css');
	}
	
	// add admin menu
	add_action('admin_menu', 'rtp_plugin_menu');
	
	function rtp_plugin_menu() {
		$sub_menu_page = add_submenu_page( 'rezgo-settings', 'Rezgo Tour Posts', 'Rezgo Tour Posts', 'manage_options', 'rezgo-rtp-settings', 'rtp_plugin_settings' );
		add_action('admin_print_styles-' . $sub_menu_page, 'rtp_plugin_admin_styles');
	}
	
	function rtp_plugin_admin_styles() {
		 wp_enqueue_style('rtp_settings_css');
	}
	
	
	function rtp_plugin_settings() {
		
		global $wpdb;
		
		// check for installation/activation of Rezgo plugin
		if ( defined('REZGO_DIR') ) {
			
			$site = new RezgoSite();
			$tour_list = $site->getTours();		
						
			if (!current_user_can('manage_options'))  {
				wp_die( __('You do not have sufficient permissions to access this page.') );
			}
			
			echo '
			<div class="wrap" id="rtp_settings">
			<h1>Rezgo Tour Posts</h1>';	
			
			if($_POST['rtp_update']) {
				
				$tour_check = $wpdb->get_row("SELECT * FROM $wpdb->posts WHERE post_type = 'tour'");
				
				if ($tour_check) {
				
					echo '
					<p class="warning">You have already created tour posts. If you would like to recreate them, you must first remove the existing tours by clicking the <em>Remove Tour Posts</em> button below.</p>
					';		
							
				} else {
					
					$tour_page = $_POST['rtp_tour_page'];
					update_option("rtp_tour_page", $tour_page);
					
					foreach( $tour_list as $tour ) {
						
						$tour_name = ( string ) $tour->name;
						$tour_path = 'details/'.$tour->com.'/'.$site->seoEncode($tour->name);
						$tour_url = site_url().'/?pagename='.$tour_page.'&rezgo_page=tour_details&com='.$tour->com;
						
						$tour_post = array(
							'post_author' => 1,
							'comment_status' => 'closed', 
							'ping_status' => 'closed', 
							'post_date' => date('Y-m-d H:i:s', (time() - 86400)), 
							'post_date_gmt' => gmdate('Y-m-d H:i:s', (time() - 86400)), 
							'post_title' => $tour_name,
							'post_name' => $tour_path,  
							'guid' => $tour_url,
							'post_status' => 'publish', 
							'post_type' => 'tour'
						);  	
						
						// insert new tour post
						$post_id = wp_insert_post( $tour_post );
						
						// update the post to set the modified dates
						wp_update_post( $post_id );
						
						// update the post again to rewrite the path with slashes
						$wpdb->update( 'wp_posts', array( 'post_name' => $tour_path ), array( 'ID' => $post_id ) );
						
					}
				
					echo '
					<p class="success">Successfully created custom post type &quot;tour&quot; and generated new posts for each Rezgo tour. <br />Tours should now be available to XML sitemaps.</p>
					';
					
					if ( defined('WPSEO_VERSION') ) {
						echo '
						<p class="success">WordPress SEO is installed so you should be able to view the tour sitemap here &hellip; <a href="/tour-sitemap.xml" target="_blank">Tours Sitemap</a></p>
						';
					}				
					
				}
				
			}
			
			if($_POST['rtp_flush']) {
				
				$wpdb->query("DELETE FROM $wpdb->posts WHERE `post_type` = 'tour';");
				$wpdb->flush(); 
				
				echo '
				<p class="success">Successfully removed posts with the custom post type of &quot;tour&quot;</p>
				';
				
			}
			
			echo '
			<p>To create your Rezgo posts, first select the WordPress page where ALL of your tours are listed. Then click the <em>Create Tour Posts</em> button.</p>
			<form name="create_posts" method="post" action="">
			';
			
			settings_fields('rtp_options');
			
			echo '
			<dl>
				<dt>Select Tour Page:</dt>
				<dd><select name="rtp_tour_page">
						<option value="tours"> select Rezgo page </option>
				';
				
			$pages = get_pages(); 
			
			foreach ( $pages as $page ) {
				echo '<option value="'.$page->post_name.'"';
				echo (get_option('rtp_tour_page') == $page->post_name) ? ' selected' : '';
				echo '>'.$page->post_title.'</option>'."\n";
			}
				
			echo '	
				</select></dd>
				<dt>&nbsp;</dt>
				<dd><input type="submit" class="button-primary" value="Create Tour Posts" /></dd>		
			</dl>
			<input type="hidden" name="rtp_update" value="1" />
			<input type="hidden" name="action" value="update" />
			<input type="hidden" name="page_options" value="rtp_tour_page" />
			
			</form>
			</div>
			<br clear="all" />
			';
			
			$tour_posts = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type = 'tour'");
			
			if ($tour_posts) {
				
				echo '
				<div id="rtp_tour_list">
				<strong>There are currently '.count($tour_posts).' tour posts</strong> <br />
				';
				
				foreach( $tour_posts as $post ) {
					$tour_link = site_url() . '/' . RTP_TOUR_PAGE . '/' . $post->post_name;
					echo '<a href="'.$tour_link.'" target="_blank">'. $post->post_title .'</a><br />';
				}		
				
				echo '
					<br clear="all" />
					<form name="flush_posts" method="post" action="">
						<input type="submit" class="button-primary" value="Remove Tour Posts" />
						<input type="hidden" name="rtp_flush" value="1" />
					</form>
				</div>';	
				
			}			
			
		} else {
			
			echo '
			<div class="wrap" id="rtp_settings">
				<br /><br />
				<p class="warning">The Rezgo plugin is either not installed or activated. Before you can use this plugin, you must activate the primary Rezgo plugin. If you have not already done so, <a href="http://wordpress.org/plugins/rezgo-online-booking/" target="_blank">download the latest version of the Rezgo plugin here</a>.</p>
			</div>
			';		
			
		}
				
		

		
	}

?>