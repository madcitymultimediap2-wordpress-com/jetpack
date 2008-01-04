<?php
/*
Plugin Name: WP Super Cache
Plugin URI: http://ocaoimh.ie/wp-super-cache/
Description: Very fast caching module for WordPress. Once enabled, you must <a href="options-general.php?page=wp-super-cache/wp-cache.php">enable the cache</a>. Based on WP-Cache by <a href="http://mnm.uib.es/gallir/">Ricardo Galli Granada</a>.
Version: 0.5.1
Author: Donncha O Caoimh
Author URI: http://ocaoimh.ie/
*/
/*  Copyright 2005-2006  Ricardo Galli Granada  (email : gallir@uib.es)
    Some code copyright 2007 Donncha O Caoimh (http://ocaoimh.ie/)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$wp_cache_config_file = ABSPATH . 'wp-content/wp-cache-config.php';

if( !@include($wp_cache_config_file) ) {
	get_wpcachehome();
	$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
	@include($wp_cache_config_file_sample);
} else {
	get_wpcachehome();
}

$wp_cache_config_file_sample = WPCACHEHOME . 'wp-cache-config-sample.php';
$wp_cache_link = ABSPATH . 'wp-content/advanced-cache.php';
$wp_cache_file = WPCACHEHOME . 'wp-cache-phase1.php';

include(WPCACHEHOME . 'wp-cache-base.php');

function get_wpcachehome() {
	if( defined( 'WPCACHEHOME' ) == false ) {
		if( is_file( dirname(__FILE__) . '/wp-cache-config-sample.php' ) ) {
			define( 'WPCACHEHOME', trailingslashit( dirname(__FILE__) ) );
		} elseif( is_file( dirname(__FILE__) . '/wp-super-cache/wp-cache-config-sample.php' ) ) {
			define( 'WPCACHEHOME', dirname(__FILE__) . '/wp-super-cache/' );
		} else {
			die( 'Please create wp-content/wp-cache-config.php from wp-super-cache/wp-cache-config-sample.php' );
		}
	}
}

function wp_cache_add_pages() {
	if( function_exists( 'is_site_admin' ) ) {
		if( is_site_admin() ) {
			add_submenu_page('wpmu-admin.php', __('WP Super Cache'), __('WP Super Cache'), 'manage_options', __FILE__, 'wp_cache_manager');
			add_options_page('WP Super Cache', 'WP Super Cache', 'manage_options', __FILE__, 'wp_cache_manager');
		}
	} else {
		add_options_page('WP Super Cache', 'WP Super Cache', 'manage_options', __FILE__, 'wp_cache_manager');
	}
}

function wp_cache_manager() {
	global $wp_cache_config_file, $valid_nonce, $supercachedir, $cache_path, $cache_enabled, $cache_compression, $super_cache_enabled, $wp_cache_hello_world;

	if( function_exists( 'is_site_admin' ) )
		if( !is_site_admin() )
			return;

	$supercachedir = $cache_path . 'supercache/' . preg_replace('/:.*$/', '',  $_SERVER["HTTP_HOST"]);
	if( get_option( 'gzipcompression' ) == 1 )
		update_option( 'gzipcompression', 0 );
	$valid_nonce = wp_verify_nonce($_REQUEST['_wpnonce'], 'wp-cache');
	
 	echo '<div class="wrap">';
	echo "<h2>WP Super Cache Manager</h2>\n";
	if( ini_get( 'safe_mode' ) ) {
		?><h3>Warning! PHP safe mode enabled!</h3>
		<p>You may experience problems running this plugin because SAFE MODE is enabled. <?php
		if( !ini_get( 'safe_mode_gid' ) ) {
			?>Your server is set up to check the owner of PHP scripts before allowing them to read and write files.</p><p>You or an administrator may be able to make it work by changing the group owner of the plugin scripts to match that of the web server user. The group owner of the wp-content/cache/ directory must also be changed. See the <a href='http://php.net/features.safe-mode'>safe mode manual page</a> for further details.</p><?php
		} else {
			?>You or an administrator must disable this. See the <a href='http://php.net/features.safe-mode'>safe mode manual page</a> for further details. This cannot be disabled in a .htaccess file unfortunately. It must be done in the php.ini config file.</p><?php
		}
	}
	if(isset($_REQUEST['wp_restore_config']) && $valid_nonce) {
		unlink($wp_cache_config_file);
		echo '<strong>Configuration file changed, some values might be wrong. Load the page again from the "Options" menu to reset them.</strong>';
	}

	if ( !wp_cache_check_link() ||
		!wp_cache_verify_config_file() ||
		!wp_cache_verify_cache_dir() ) {
		echo "<br>Cannot continue... fix previous problems and retry.<br />";
		echo "</div>\n";
		return;
	}

	if (!wp_cache_check_global_config()) {
		echo "</div>\n";
		return;
	}

	if( !got_mod_rewrite() ) {
		?><h4 style='color: #a00'>Mod rewrite may not be installed!</h4>
		<p>It appears that mod_rewrite is not installed. Sometimes this check isn't 100% reliable, especially if you are not using Apache. Please verify that the mod_rewrite module is loaded. It is required for serving Super Cache static files. You will still be able to use WP-Cache.</p><?php
	}

	if( is_writeable( ABSPATH ) ) {
		?><h4 style='color: #a00'>Warning! <?php echo ABSPATH; ?> is writeable!</h4>
		<p>Your blog root directory is writeable by the webserver. Unless you are creating direct cached files it is recommended that this be changed to read-only.</p><?php
	}

	if ( $valid_nonce ) {
		if( isset( $_POST[ 'wp_cache_hello_world' ] ) ) {
			$wp_cache_hello_world = (int)$_POST[ 'wp_cache_hello_world' ];
		} else {
			$wp_cache_hello_world = 0;
		}
		wp_cache_replace_line('^ *\$wp_cache_hello_world', '$wp_cache_hello_world = ' . (int)$wp_cache_hello_world . ";", $wp_cache_config_file);

		if( isset( $_POST[ 'wp_cache_status' ] ) ) {
			switch( $_POST[ 'wp_cache_status' ] ) {
				case 'all':
					wp_cache_enable();
					break;
				case 'none':
					wp_cache_disable();
					break;
				case 'wpcache':
					wp_cache_enable();
					wp_super_cache_disable();
					break;
			}
		}
		if( isset( $_POST[ 'cache_compression' ] ) && $_POST[ 'cache_compression' ] != $cache_compression ) {
			$cache_compression_changed = true;
			$cache_compression = intval( $_POST[ 'cache_compression' ] );
			wp_cache_replace_line('^ *\$cache_compression', "\$cache_compression = " . $cache_compression . ";", $wp_cache_config_file);
			if( function_exists( 'prune_super_cache' ) )
				prune_super_cache ($cache_path, true);
			delete_option( 'super_cache_meta' );
		}
	}

	?><fieldset style='border: 1px solid #aaa' class="options"> 
	<legend>WP Super Cache Status</legend><?php
	echo '<form name="wp_manager" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	?>
	<label><input type='radio' name='wp_cache_status' value='all' <?php if( $cache_enabled == true && $super_cache_enabled == true ) { echo 'checked=checked'; } ?>> WP Cache and Super Cache enabled</label><br />
	<label><input type='radio' name='wp_cache_status' value='none' <?php if( $cache_enabled == false ) { echo 'checked=checked'; } ?>> WP Cache and Super Cache disabled</label><br />
	<label><input type='radio' name='wp_cache_status' value='wpcache' <?php if( $cache_enabled == true && $super_cache_enabled == false ) { echo 'checked=checked'; } ?>> Super Cache Disabled</label><br />
	<p><label><input type='checkbox' name='wp_cache_hello_world' <?php if( $wp_cache_hello_world ) echo "checked"; ?> value='1'> Proudly tell the world your server is Digg proof! (places a message in your blog's footer)</label></p>
	<?php
	echo '<div class="submit"><input type="submit"value="Update Status &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	?>
	</form>
	</fieldset><br />

	<fieldset style='border: 1px solid #aaa' class="options"> 
	<legend>Super Cache Compression</legend>
	<form name="wp_manager" action="<?php echo $_SERVER["REQUEST_URI"]; ?>" method="post">
	<label><input type="radio" name="cache_compression" value="1" <?php if( $cache_compression ) { echo "checked=checked"; } ?>> Enabled</label>
	<label><input type="radio" name="cache_compression" value="0" <?php if( !$cache_compression ) { echo "checked=checked"; } ?>> Disabled</label>
	<p>Compression is disabled by default because some hosts have problems with compressed files. Switching this on and off clears the cache.</p>
	<?php
	$home_path = get_home_path();
	$home_root = parse_url(get_option('home'));
	$home_root = trailingslashit($home_root['path']);
	$wprules = implode( "\n", extract_from_markers( $home_path.'.htaccess', 'WordPress' ) );
	$wprules = str_replace( "RewriteEngine On\n", '', $wprules );
	$wprules = str_replace( "RewriteBase $home_root\n", '', $wprules );

	$dohtaccess = false;
	if( !$wprules || $wprules == '' ) {
		echo "<h4 style='color: #a00'>Mod Rewrite rules not updated!</h4>";
		echo "<p>You must have <strong>BEGIN</strong> and <strong>END</strong> markers in {$home_path}.htaccess for the auto update to work. They look like this and surround the main WordPress mod_rewrite rules:
		<blockquote><code><em># BEGIN WordPress</em><br /> RewriteCond %{REQUEST_FILENAME} !-f<br /> RewriteCond %{REQUEST_FILENAME} !-d<br /> RewriteRule . /index.php [L]<br /> <em># END WordPress</em></code></blockquote>
		Refresh this page when you have updated your .htaccess file to add the Super Cache rules.";
	} elseif( strpos( $wprules, 'supercache' ) == false ) { // only write the rules once
		$dohtaccess = true;
	}
	if( $dohtaccess ) {
		$rules = "<IfModule mod_rewrite.c>\n";
		$rules .= "RewriteEngine On\n";
		$rules .= "RewriteBase $home_root\n"; // props Chris Messina
		$rules .= "RewriteCond %{QUERY_STRING} !.*s=.*\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} !^.*comment_author_.*$\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} !^.*wordpressuser.*$\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} !^.*wp-postpass_.*$\n";
		$rules .= "RewriteCond %{HTTP:Accept-Encoding} gzip\n";
		$rules .= "RewriteCond %{DOCUMENT_ROOT}{$home_root}wp-content/cache/supercache/%{HTTP_HOST}{$home_root}$1index.html.gz -f\n";
		$rules .= "RewriteRule ^(.*) {$home_root}wp-content/cache/supercache/%{HTTP_HOST}{$home_root}$1index.html.gz [L]\n\n";

		$rules .= "RewriteCond %{QUERY_STRING} !.*s=.*\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} !^.*comment_author_.*$\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} !^.*wordpressuser.*$\n";
		$rules .= "RewriteCond %{HTTP_COOKIE} !^.*wp-postpass_.*$\n";
		$rules .= "RewriteCond %{DOCUMENT_ROOT}{$home_root}wp-content/cache/supercache/%{HTTP_HOST}{$home_root}$1index.html -f\n";
		$rules .= "RewriteRule ^(.*) {$home_root}wp-content/cache/supercache/%{HTTP_HOST}{$home_root}$1index.html [L]\n";
		$rules .= $wprules . "\n";
		$rules .= "</IfModule>";
		if( insert_with_markers( $home_path.'.htaccess', 'WordPress', explode( "\n", $rules ) ) ) {
			echo "<h4>Mod Rewrite rules updated!</h4>";
			echo "<p><strong>" . ABSPATH . ".htaccess has been updated with the necessary mod_rewrite rules. Please verify they are correct. The file should look like this:</strong></p>\n";
		} else {
			echo "<h4>Mod Rewrite rules must be updated!</h4>";
			echo "<p><strong> Your " . ABSPATH . ".htaccess is not writable by the webserver and must be updated with the necessary mod_rewrite rules. The new rules go above the regular WordPress rules as shown in the code below:</strong></p>\n";
		}
		echo "<p><pre># BEGIN WordPress\n{$rules}# END WordPress</pre></p>\n";
	}
	// http://allmybrain.com/2007/11/08/making-wp-super-cache-gzip-compression-work/
	$gziprules = "AddEncoding x-gzip .gz\n";
	$gziprules .= "AddType text/html .gz";
	$gziprules = insert_with_markers( $cache_path . '.htaccess', 'supercache', explode( "\n", $gziprules ) );

	if( isset( $cache_compression_changed ) && isset( $_POST[ 'cache_compression' ] ) && !$cache_compression ) {
		?><p><strong>Super Cache compression is now disabled.</strong></p> <?php
	} elseif( isset( $cache_compression_changed ) && isset( $_POST[ 'cache_compression' ] ) && $cache_compression ) {
		?><p><strong>Super Cache compression is now enabled.</strong></p><?php
	}
	echo '<div class="submit"><input type="submit"value="Update Compression &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	?></fieldset><?php

	wp_cache_edit_max_time();

	echo '<br /><a name="files"></a><fieldset style="border: 1px solid #aaa" class="options"><legend>Accepted filenames, rejected URIs</legend>';
	wp_cache_edit_rejected();
	echo "<br />\n";
	wp_cache_edit_accepted();
	echo '</fieldset>';

	wp_cache_edit_rejected_ua();

	wp_lock_down();

	wp_cache_files();

	wp_cache_restore();

	ob_start();
	if( defined( 'WP_CACHE' ) ) {
		if( function_exists( 'do_cacheaction' ) ) {
			do_cacheaction( 'cache_admin_page' );
		}
	}
	$out = ob_get_contents();
	ob_end_clean();
	if( $out != '' ) {
		echo '<fieldset class="options"><legend>Cache Plugins</legend>';
		echo $out;
		echo '</fieldset>';
	}

	echo "</div>\n";

}

function wp_cache_restore() {
	echo '<br /><fieldset style="border: 1px solid #aaa" class="options"><legend>Configuration messed up?</legend>';
	echo '<form name="wp_restore" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo '<input type="hidden" name="wp_restore_config" />';
	echo '<div class="submit"><input type="submit" id="deletepost" value="Restore default configuration &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	echo '</fieldset>';

}

function comment_form_lockdown_message() {
	?><p><?php _e( "Comment moderation is enabled. Your comment may take some time to appear." ); ?></p><?php
}
if( defined( 'WPLOCKDOWN' ) && constant( 'WPLOCKDOWN' ) )
	add_action( 'comment_form', 'comment_form_lockdown_message' );

function wp_lock_down() {
	global $wpdb, $cache_path, $wp_cache_config_file, $valid_nonce, $cached_direct_pages;

	if(isset($_POST['wp_lock_down']) && $valid_nonce) {
		$wp_lock_down = $_POST['wp_lock_down'] == '1' ? '1' : '0';
		wp_cache_replace_line('^.*WPLOCKDOWN', "define( 'WPLOCKDOWN', '$wp_lock_down' );", $wp_cache_config_file);
		if( $wp_lock_down == '0' && function_exists( 'prune_super_cache' ) )
			prune_super_cache( $cache_path, true ); // clear the cache after lockdown

	}
	if( !isset( $wp_lock_down ) ) {
		if( defined( 'WPLOCKDOWN' ) ) {
			$wp_lock_down = constant( 'WPLOCKDOWN' );
		} else {
			$wp_lock_down = '0';
		}
	}
	?><br /><fieldset style='border: 1px solid #aaa' class="options"> 
	<legend>Lock Down: <span style='color: #f00'><?php echo $wp_lock_down == '0' ? 'disabled' : 'enabled'; ?></span></legend>
	<p>Prepare your server for an expected spike in traffic by enabling the lock down. When this is enabled, new comments on a post will not refresh the cached static files.</p>
	<p>Developers: Make your plugin lock down compatible by checking the 'WPLOCKDOWN' constant. The following code will make sure your plugin respects the WPLOCKDOWN setting.
	<blockquote><code>if( defined( 'WPLOCKDOWN' ) && constant( 'WPLOCKDOWN' ) ) { <br />
		&nbsp;&nbsp;&nbsp;&nbsp;echo "Sorry. My blog is locked down. Updates will appear shortly";<br />
		}</code></blockquote>
	<?php
	if( $wp_lock_down == '1' ) {
		?><strong>WordPress is locked down. Super Cache static files will not be deleted when new comments are made.</strong><?php
	} else {
		?><strong>WordPress is not locked down. New comments will refresh Super Cache static files as normal.</strong><?php
	}
	$new_lockdown =  $wp_lock_down == '1' ? '0' : '1';
	$new_lockdown_desc =  $wp_lock_down == '1' ? 'Disable' : 'Enable';
	echo '<form name="wp_lock_down" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo "<input type='hidden' name='wp_lock_down' value='{$new_lockdown}' />";
	echo "<div class='submit'><input type='submit' value='{$new_lockdown_desc} Lock Down &raquo;' /></div>";
	wp_nonce_field('wp-cache');
	echo "</form>\n";

	?></fieldset><br /><?php
	?><fieldset style='border: 1px solid #aaa' class="options"> 
	<legend>Directly Cached Files (advanced use only)</legend><?php

	$out = '';
	if( $valid_nonce && is_array( $_POST[ 'direct_pages' ] ) && !empty( $_POST[ 'direct_pages' ] ) ) {
		$expiredfiles = array_diff( $cached_direct_pages, $_POST[ 'direct_pages' ] );
		unset( $cached_direct_pages );
		foreach( $_POST[ 'direct_pages' ] as $page ) {
			$page = $wpdb->escape( $page );
			if( $page != '' ) {
				$cached_direct_pages[] = $page;
				$out .= "'$page', ";
			}
		}
		if( $out == '' ) {
			$out = "'', ";
		}
	}
	if( $valid_nonce && $_POST[ 'new_direct_page' ] && '' != $_POST[ 'new_direct_page' ] ) {
		$page = str_replace( get_option( 'siteurl' ), '', $_POST[ 'new_direct_page' ] );
		if( substr( $page, 0, 1 ) != '/' )
			$page = '/' . $page;
		$page = $wpdb->escape( $page );
		if( in_array( $page, $cached_direct_pages ) == false ) {
			$cached_direct_pages[] = $page;
			$out .= "'$page', ";
		}
	}

	if( $out != '' ) {
		$out = substr( $out, 0, -2 );
		$out = '$cached_direct_pages = array( ' . $out . ' );';
		wp_cache_replace_line('^ *\$cached_direct_pages', "$out", $wp_cache_config_file);
		prune_super_cache( $cache_path, true );
	}

	if( !empty( $expiredfiles ) ) {
		foreach( $expiredfiles as $file ) {
			if( $file != '' ) {
				$firstfolder = explode( '/', $file );
				$firstfolder = ABSPATH . $firstfolder[1];
				$file = ABSPATH . $file;
				unlink( trailingslashit( $file ) . 'index.html' );
				RecursiveFolderDelete( trailingslashit( $firstfolder ) );
			}
		}
	}

	if( $valid_nonce && $_POST[ 'deletepage' ] ) {
		$page = preg_replace('/[ <>\'\"\r\n\t\(\)]/', '', str_replace( '..', '', $_POST['deletepage']) );
		$pagefile = ABSPATH . $page . 'index.html';
		$firstfolder = explode( '/', $page );
		$firstfolder = ABSPATH . $firstfolder[1];
		$page = ABSPATH . $page;
		if( is_file( $pagefile ) && is_writable( $pagefile ) && is_writable( $firstfolder ) ) {
			unlink( $pagefile );
			RecursiveFolderDelete( $firstfolder );
			echo "<strong>$pagefile removed!</strong><br />";
			prune_super_cache( $cache_path, true );
		}
	}

	$readonly = '';
	if( !is_writeable( ABSPATH ) ) {
		$readonly = 'READONLY';
		?><p><strong style='color: #a00'>WARNING! You must make <?php echo ABSPATH ?> writable to enable this feature. As this is a security risk please make it readonly after your page is generated.</strong></p><?php
	} else {
		?><p><strong style='color: #a00'>WARNING! <?php echo ABSPATH ?> is writable. Please make it readonly after your page is generated as this is a security risk.</strong></p><?php
	}
	echo '<form name="direct_page" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	if( is_array( $cached_direct_pages ) ) {
		$out = '';
		foreach( $cached_direct_pages as $page ) {
			if( $page == '' )
				continue;
			$generated = '';
			if( is_file( ABSPATH . $page . '/index.html' ) )
				$generated = '<input type="Submit" name="deletepage" value="' . $page . '">';
			$out .= "<tr><td><input type='text' $readonly name='direct_pages[]' size='30' value='$page' /></td><td>$generated</td></tr>";
		}
		if( $out != '' ) {
			?><table><tr><th>Existing direct page</th><th>Delete cached file</th></tr><?php
			echo "$out</table>";
		}
	}
	if( $readonly != 'READONLY' )
		echo "Add direct page: <input type='text' $readonly name='new_direct_page' size='30' value='' /><br />";

	echo "<p>Directly cached files are files created directly off " . ABSPATH . " where your blog lives. This feature is only useful if you are expecting a major Digg or Slashdot level of traffic to one post or page.</p>";
	if( $readonly != 'READONLY' ) {
		echo "<p>For example: to cache <em>'" . trailingslashit( get_option( 'siteurl' ) ) . "about/'</em>, you would enter '" . trailingslashit( get_option( 'siteurl' ) ) . "about/' or '/about/'. The cached file will be generated the next time an anonymous user visits that page.</p>";
		echo "<p>Make the textbox blank to remove it from the list of direct pages and delete the cached file.</p>";
	}

	wp_nonce_field('wp-cache');
	if( $readonly != 'READONLY' )
		echo "<div class='submit'><input type='submit' value='Update direct pages &raquo;' /></div>";
	echo "</form>\n";
	?></fieldset><?php
}

function RecursiveFolderDelete ( $folderPath ) { // from http://www.php.net/manual/en/function.rmdir.php
	if( trailingslashit( constant( 'ABSPATH' ) ) == trailingslashit( $folderPath ) )
		return false;
	if ( @is_dir ( $folderPath ) ) {
		$dh  = @opendir($folderPath);
		while (false !== ($value = @readdir($dh))) {
			if ( $value != "." && $value != ".." ) {
				$value = $folderPath . "/" . $value; 
				if ( @is_dir ( $value ) ) {
					RecursiveFolderDelete ( $value );
				}
			}
		}
		return @rmdir ( $folderPath );
	} else {
		return FALSE;
	}
}

function wp_cache_edit_max_time () {
	global $super_cache_max_time, $cache_max_time, $wp_cache_config_file, $valid_nonce;

	if( !isset( $super_cache_max_time ) )
		$super_cache_max_time = 21600;

	if(isset($_POST['wp_max_time']) && $valid_nonce) {
		$max_time = (int)$_POST['wp_max_time'];
		if ($max_time > 0) {
			$cache_max_time = $max_time;
			wp_cache_replace_line('^ *\$cache_max_time', "\$cache_max_time = $cache_max_time;", $wp_cache_config_file);
		}
	}
	if(isset($_POST['super_cache_max_time']) && $valid_nonce) {
		$max_time = (int)$_POST['super_cache_max_time'];
		if ($max_time > 0) {
			$super_cache_max_time = $max_time;
			wp_cache_replace_line('^ *\$super_cache_max_time', "\$super_cache_max_time = $super_cache_max_time;", $wp_cache_config_file);
		}
	}
	?><br /><fieldset style='border: 1px solid #aaa' class="options"> 
	<legend>Expiry Time</legend><?php
	echo '<form name="wp_edit_max_time" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo '<label for="wp_max_time">Expire time:</label> ';
	echo "<input type=\"text\" size=6 name=\"wp_max_time\" value=\"$cache_max_time\" /> seconds<br />";
	echo '<label for="super_cache_max_time">Super Cache Expire time:</label> ';
	echo "<input type=\"text\" size=6 name=\"super_cache_max_time\" value=\"$super_cache_max_time\" /> seconds";
	echo '<div class="submit"><input type="submit" value="Change expiration &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	?></fieldset><?php
}

function wp_cache_sanitize_value($text, & $array) {
	$text = wp_specialchars(strip_tags($text));
	$array = preg_split("/[\s,]+/", chop($text));
	$text = var_export($array, true);
	$text = preg_replace('/[\s]+/', ' ', $text);
	return $text;
}

function wp_cache_edit_rejected_ua() {
	global $cache_rejected_user_agent, $wp_cache_config_file, $valid_nonce;

	if (!function_exists('apache_request_headers')) return;

	if(isset($_REQUEST['wp_rejected_user_agent']) && $valid_nonce) {
		$text = wp_cache_sanitize_value($_REQUEST['wp_rejected_user_agent'], $cache_rejected_user_agent);
		wp_cache_replace_line('^ *\$cache_rejected_user_agent', "\$cache_rejected_user_agent = $text;", $wp_cache_config_file);
	}


	echo '<br /><a name="user-agents"></a><fieldset style="border: 1px solid #aaa" class="options"><legend>Rejected User Agents</legend>';
	echo "<p>Strings in the HTTP 'User Agent' header that prevent WP-Cache from 
		caching bot, spiders, and crawlers' requests.
		Note that cached files are still sent to these request if they already exists.</p>\n";
	echo '<form name="wp_edit_rejected_user_agent" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo '<label for="wp_rejected_user_agent">Rejected UA strings</label>';
	echo '<textarea name="wp_rejected_user_agent" cols="40" rows="4" style="width: 70%; font-size: 12px;" class="code">';
	foreach ($cache_rejected_user_agent as $ua) {
		echo wp_specialchars($ua) . "\n";
	}
	echo '</textarea> ';
	echo '<div class="submit"><input type="submit" value="Save UA strings &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo '</form>';
	echo "</fieldset>\n";
}


function wp_cache_edit_rejected() {
	global $cache_acceptable_files, $cache_rejected_uri, $wp_cache_config_file, $valid_nonce;

	if(isset($_REQUEST['wp_rejected_uri']) && $valid_nonce) {
		$text = wp_cache_sanitize_value($_REQUEST['wp_rejected_uri'], $cache_rejected_uri);
		wp_cache_replace_line('^ *\$cache_rejected_uri', "\$cache_rejected_uri = $text;", $wp_cache_config_file);
	}


	echo "<p>Add here strings (not a filename) that forces a page not to be cached. For example, if your URLs include year and you dont want to cache last year posts, it's enough to specify the year, i.e. '/2004/'. WP-Cache will search if that string is part of the URI and if so, it will no cache that page.</p>\n";
	echo '<form name="wp_edit_rejected" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo '<label for="wp_rejected_uri">Rejected URIs</label>';
	echo '<textarea name="wp_rejected_uri" cols="40" rows="4" style="width: 70%; font-size: 12px;" class="code">';
	foreach ($cache_rejected_uri as $file) {
		echo wp_specialchars($file) . "\n";
	}
	echo '</textarea> ';
	echo '<div class="submit"><input type="submit" value="Save strings &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
}

function wp_cache_edit_accepted() {
	global $cache_acceptable_files, $cache_rejected_uri, $wp_cache_config_file, $valid_nonce;

	if(isset($_REQUEST['wp_accepted_files']) && $valid_nonce) {
		$text = wp_cache_sanitize_value($_REQUEST['wp_accepted_files'], $cache_acceptable_files);
		wp_cache_replace_line('^ *\$cache_acceptable_files', "\$cache_acceptable_files = $text;", $wp_cache_config_file);
	}


	echo "<p>Add here those filenames that can be cached, even if they match one of the rejected substring specified above.</p>\n";
	echo '<form name="wp_edit_accepted" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo '<label for="wp_accepted_files">Accepted files</label>';
	echo '<textarea name="wp_accepted_files" cols="40" rows="8" style="width: 70%; font-size: 12px;" class="code">';
	foreach ($cache_acceptable_files as $file) {
		echo wp_specialchars($file) . "\n";
	}
	echo '</textarea> ';
	echo '<div class="submit"><input type="submit" value=" &raquo;Save files &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
}

function wp_cache_enable() {
	global $wp_cache_config_file, $cache_enabled, $supercachedir;

	if(get_option('gzipcompression')) {
		echo "<b>Error: GZIP compression is enabled, disable it if you want to enable wp-cache.</b><br /><br />";
		return false;
	}
	if( wp_cache_replace_line('^ *\$cache_enabled', '$cache_enabled = true;', $wp_cache_config_file) ) {
		$cache_enabled = true;
	}
	wp_super_cache_enable();
}

function wp_cache_disable() {
	global $wp_cache_config_file, $cache_enabled, $supercachedir, $cache_path;

	if (wp_cache_replace_line('^ *\$cache_enabled', '$cache_enabled = false;', $wp_cache_config_file)) {
		$cache_enabled = false;
	}
	wp_super_cache_disable();
}
function wp_super_cache_enable() {
	global $supercachedir, $wp_cache_config_file, $super_cache_enabled;

	if( is_dir( $supercachedir . ".disabled" ) )
		rename( $supercachedir . ".disabled", $supercachedir );
	wp_cache_replace_line('^ *\$super_cache_enabled', '$super_cache_enabled = true;', $wp_cache_config_file);
	$super_cache_enabled = true;
}

function wp_super_cache_disable() {
	global $supercachedir, $wp_cache_config_file, $super_cache_enabled;

	wp_cache_replace_line('^ *\$super_cache_enabled', '$super_cache_enabled = false;', $wp_cache_config_file);
	if( is_dir( $supercachedir ) )
		rename( $supercachedir, $supercachedir . ".disabled" );
	$super_cache_enabled = false;
	sleep( 1 ); // allow existing processes to write to the supercachedir and then delete it
	if (function_exists ('prune_super_cache') && is_dir( $supercachedir ) ) {
		prune_super_cache( $supercachedir, true );
	}
}

function wp_cache_is_enabled() {
	global $wp_cache_config_file;

	if(get_option('gzipcompression')) {
		echo "<b>Warning</b>: GZIP compression is enabled in Wordpress, wp-cache will be bypassed until you disable gzip compression.<br />";
		return false;
	}
	$lines = file($wp_cache_config_file);
	foreach($lines as $line) {
	 	if (preg_match('/^ *\$cache_enabled *= *true *;/', $line))
			return true;
	}
	return false;
}


function wp_cache_replace_line($old, $new, $my_file) {
	if (!is_writable($my_file)) {
		echo "Error: file $my_file is not writable.<br />\n";
		return false;
	}
	$found = false;
	$lines = file($my_file);
	foreach($lines as $line) {
	 	if ( preg_match("/$old/", $line)) {
			$found = true;
			break;
		}
	}
	if ($found) {
		$fd = fopen($my_file, 'w');
		foreach($lines as $line) {
			if ( !preg_match("/$old/", $line))
				fputs($fd, $line);
			else {
				fputs($fd, "$new //Added by WP-Cache Manager\n");
			}
		}
		fclose($fd);
		return true;
	}
	$fd = fopen($my_file, 'w');
	$done = false;
	foreach($lines as $line) {
		if ( $done || !preg_match('/^define|\$|\?>/', $line))
			fputs($fd, $line);
		else {
			fputs($fd, "$new //Added by WP-Cache Manager\n");
			fputs($fd, $line);
			$done = true;
		}
	}
	fclose($fd);
	return true;
/*
	copy($my_file, $my_file . "-prev");
	rename($my_file . '-new', $my_file);
*/
}

function wp_cache_verify_cache_dir() {
	global $cache_path;

	$dir = dirname($cache_path);
	if ( !file_exists($cache_path) ) {
		if ( !is_writable( $dir ) || !($dir = mkdir( $cache_path ) ) ) {
				echo "<b>Error:</b> Your cache directory (<b>$cache_path</b>) did not exist and couldn't be created by the web server. <br /> Check  $dir permissions.";
				return false;
		}
	}
	if ( !is_writable($cache_path)) {
		echo "<b>Error:</b> Your cache directory (<b>$cache_path</b>) or <b>$dir</b> need to be writable for this plugin to work. <br /> Double-check it.";
		return false;
	}

	if ( '/' != substr($cache_path, -1)) {
		$cache_path .= '/';
	}

	@mkdir( $cache_path . 'meta' );

	return true;
}

function wp_cache_verify_config_file() {
	global $wp_cache_config_file, $wp_cache_config_file_sample;

	$new = false;
	$dir = dirname($wp_cache_config_file);

	if ( !is_writable($dir)) {
			echo "<b>Error:</b> wp-content directory (<b>$dir</b>) is not writable by the Web server.<br />Check its permissions.";
			return false;
	}
	if ( file_exists($wp_cache_config_file) ) {
		$lines = join( ' ', file( $wp_cache_config_file ) );
		if( strpos( $lines, 'WPCACHEHOME' ) === false ) {
			if( is_writable( $wp_cache_config_file ) ) {
				@unlink( $wp_cache_config_file );
			} else {
				echo "<b>Error:</b> Your WP-Cache config file (<b>$wp_cache_config_file</b>) is out of date and not writable by the Web server.<br />Please delete it and refresh this page.";
				return false;
			}
		}
	}

	if ( !file_exists($wp_cache_config_file) ) {
		if ( !file_exists($wp_cache_config_file_sample) ) {
			echo "<b>Error:</b> Sample WP-Cache config file (<b>$wp_cache_config_file_sample</b>) does not exist.<br />Verify you installation.";
			return false;
		}
		copy($wp_cache_config_file_sample, $wp_cache_config_file);
		if( is_file( dirname(__FILE__) . '/wp-cache-config-sample.php' ) ) {
			wp_cache_replace_line('WPCACHEHOME', "define( 'WPCACHEHOME', ABSPATH . " . str_replace( '\\', '/', str_replace( ABSPATH, ' "', dirname(__FILE__) ) ) . "/\" );", $wp_cache_config_file);
		} elseif( is_file( dirname(__FILE__) . '/wp-super-cache/wp-cache-config-sample.php' ) ) {
			wp_cache_replace_line('WPCACHEHOME', "define( 'WPCACHEHOME', ABSPATH . " . str_replace( '\\', '/', str_replace( ABSPATH, ' "', dirname(__FILE__) ) ) . "/wp-super-cache/\" );", $wp_cache_config_file);
		}
		$new = true;
	}
	if ( !is_writable($wp_cache_config_file)) {
		echo "<b>Error:</b> Your WP-Cache config file (<b>$wp_cache_config_file</b>) is not writable by the Web server.<br />Check its permissions.";
		return false;
	}
	require($wp_cache_config_file);
	return true;
}

function wp_cache_check_link() {
	global $wp_cache_link, $wp_cache_file;
 
	if( file_exists($wp_cache_link) ) {
		if( strpos( join( "\n", file( $wp_cache_link ) ), 'WPCACHEHOME' ) ) {
			// read the file and verify it's a super-cache file and not the wp-cache one.
			return true;
		} else {
			// remove the old version
			@unlink($wp_cache_link);
		}
	}

	$ret = true;
	if ( basename(@readlink($wp_cache_link)) != basename($wp_cache_file)) {
		@unlink($wp_cache_link);
		if( function_exists( 'symlink' ) ) {
			if( !@symlink ($wp_cache_file, $wp_cache_link) ) {
				$ret = false;
			}
		} elseif( !@copy( $wp_cache_file, $wp_cache_link ) ) {
			$ret = false;
		}
		if( !$ret ) {
			echo "<code>advanced-cache.php</code> does not exist<br />";
			echo "Create it by executing: <br /><code>ln -s $wp_cache_file $wp_cache_link</code><br /> in your server<br />";
			echo "Or by copying $wp_cache_file to $wp_cache_link.<br />";
			return false;
		}
	}
	return true;
}

function wp_cache_check_global_config() {

	$global = ABSPATH . 'wp-config.php';

	$lines = file($global);
	foreach($lines as $line) {
	 	if (preg_match('/^ *define *\( *\'WP_CACHE\' *, *true *\) *;/', $line)) {
			return true;
		}
	}
	$line = 'define(\'WP_CACHE\', true);';
	if (!is_writable($global) || !wp_cache_replace_line('define *\( *\'WP_CACHE\'', $line, $global) ) {
			echo "<b>Error: WP_CACHE is not enabled</b> in your <code>wp-config.php</code> file and I couldn't modified it.<br />";
			echo "Edit <code>$global</code> and add the following line: <br /><code>define('WP_CACHE', true);</code><br />Otherwise, <b>WP-Cache will not be executed</b> by Wordpress core. <br />";
			return false;
	} 
	return true;
}

function wp_cache_files() {
	global $cache_path, $file_prefix, $cache_max_time, $super_cache_max_time, $valid_nonce, $supercachedir;

	if ( '/' != substr($cache_path, -1)) {
		$cache_path .= '/';
	}

	if ( $valid_nonce ) {
		if(isset($_REQUEST['wp_delete_cache'])) {
			wp_cache_clean_cache($file_prefix);
		}
		if(isset($_REQUEST['wp_delete_cache_file'])) {
			wp_cache_clean_cache($_REQUEST['wp_delete_cache_file']);
		}
		if(isset($_REQUEST['wp_delete_expired'])) {
			wp_cache_clean_expired($file_prefix);
		}
	}
	if(isset($_REQUEST['wp_list_cache'])) {
		$list_files = true;
		$list_mess = "Update list";
	} else 
		$list_mess = "List files";

	echo '<br /><a name="list"></a><fieldset style="border: 1px solid #aaa" class="options"><legend>Cache contents</legend>';
	echo '<form name="wp_cache_content_list" action="'. $_SERVER["REQUEST_URI"] . '#list" method="post">';
	echo '<input type="hidden" name="wp_list_cache" />';
	echo '<div class="submit"><input type="submit" value="'.$list_mess.' &raquo;" /></div>';
	echo "</form>\n";

	$count = 0;
	$expired = 0;
	$now = time();
	if ( ($handle = @opendir( $cache_path . 'meta/' )) ) { 
		if ($list_files) echo "<table cellspacing=\"0\" cellpadding=\"5\">";
		while ( false !== ($file = readdir($handle))) {
			if ( preg_match("/^$file_prefix.*\.meta/", $file) ) {
				$this_expired = false;
				$content_file = preg_replace("/meta$/", "html", $file);
				$mtime = filemtime($cache_path . 'meta/' . $file);
				if ( ! ($fsize = @filesize($cache_path.$content_file)) ) 
					continue; // .meta does not exists
				$fsize = intval($fsize/1024);
				$age = $now - $mtime;
				if ( $age > $cache_max_time) {
					$expired++;
					$this_expired = true;
				}
				$count++;
				if ($list_files) {
					$meta = new CacheMeta;
					$meta = unserialize(file_get_contents($cache_path . 'meta/' . $file));
					echo $flip ? '<tr style="background: #EAEAEA;">' : '<tr>';
					$flip = !$flip;
					echo '<td><a href="http://' . $meta->uri . '" target="_blank" >';
					echo $meta->uri . "</a></td>";
					if ($this_expired) echo "<td><span style='color:red'>$age secs</span></td>";
					else echo "<td>$age secs</td>";
					echo "<td>$fsize KB</td>";
					echo '<td><form name="wp_delete_cache_file" action="'. $_SERVER["REQUEST_URI"] . '#list" method="post">';
					echo '<input type="hidden" name="wp_list_cache" />';
					echo '<input type="hidden" name="wp_delete_cache_file" value="'.preg_replace("/^(.*)\.meta$/", "$1", $file).'" />';
					echo '<div class="submit"><input id="deletepost" type="submit" value="Remove" /></div>';
					wp_nonce_field('wp-cache');
					echo "</form></td></tr>\n";
				}
			}
		}
		closedir($handle);
		if ($list_files) echo "</table>";
	}
	$sizes = get_option( 'super_cache_meta' );
	if( !$sizes )
		$sizes = array( 'expired' => 0, 'cached' => 0, 'ts' => 0 );

	$now = time();
	if( $_POST[ 'super_cache_stats' ] == 1 || $sizes[ 'cached' ] == 0 || $sizes[ 'ts' ] + 3600 <= $now ) {
		$sizes = array( 'expired' => 0, 'cached' => 0, 'ts' => 0 );

		if (is_dir($supercachedir)) {
			$entries = glob($supercachedir. '/*');
			foreach ($entries as $entry) {
				if ($entry != '.' && $entry != '..') {
					$sizes = wpsc_dirsize( $entry, $sizes );
				}
			}
		} else {
			if(is_file($supercachedir) && filemtime( $supercachedir ) + $super_cache_max_time <= $now )
				$sizes[ 'expired' ] ++;
		}
		$sizes[ 'ts' ] = time();
		update_option( 'super_cache_meta', $sizes );
	}
	echo "<p><strong>WP-Cache</strong></p>";
	echo "<ul><li>$count cached pages</li>";
	echo "<li>$expired expired pages</li></ul>";
	echo "<p><strong>WP-Super-Cache</strong></p>";
	echo "<ul><li>" . intval($sizes['cached']/2) . " cached pages</li>";
	$age = intval(($now - $sizes['ts'])/60);
	echo "<li>" . intval($sizes['expired']/2) . " expired pages. (Generated $age minutes ago. Refresh in " . (60 - $age) . " minutes. )</li></ul>";

	echo '<form name="wp_cache_content_expired" action="'. $_SERVER["REQUEST_URI"] . '#list" method="post">';
	echo '<input type="hidden" name="wp_delete_expired" />';
	echo '<div class="submit"><input type="submit" value="Delete expired &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";


	echo '<form name="wp_cache_content_delete" action="'. $_SERVER["REQUEST_URI"] . '#list" method="post">';
	echo '<input type="hidden" name="wp_delete_cache" />';
	echo '<div class="submit"><input id="deletepost" type="submit" value="Delete cache &raquo;" /></div>';

	wp_nonce_field('wp-cache');
	echo "</form>\n";

	echo '<form name="wp_super_cache_stats" action="'. $_SERVER["REQUEST_URI"] . '" method="post">';
	echo '<input type="hidden" name="super_cache_stats" value="1" />';
	echo '<div class="submit"><input type="submit" value="Regenerate Super Cache Stats &raquo;" /></div>';
	wp_nonce_field('wp-cache');
	echo "</form>\n";
	echo '</fieldset>';
}

function wpsc_dirsize($directory, $sizes) {
	global $super_cache_max_time;
	$now = time();

	if (is_dir($directory)) {
		$entries = glob($directory. '/*');
		if( is_array( $entries ) && !empty( $entries ) ) foreach ($entries as $entry) {
			if ($entry != '.' && $entry != '..') {
				$sizes = wpsc_dirsize($entry, $sizes);
			}
		}
	} else {
		if(is_file($directory) ) {
			if( filemtime( $directory ) + $super_cache_max_time <= $now ) {
				$sizes[ 'expired' ]+=1;
			} else {
				$sizes[ 'cached' ]+=1;
			}
		}
	}
	return $sizes;
}


function wp_cache_clean_cache($file_prefix) {
	global $cache_path, $supercachedir;

	// If phase2 was compiled, use its function to avoid race-conditions
	if(function_exists('wp_cache_phase2_clean_cache')) {
		if (function_exists ('prune_super_cache')) {
			if( is_dir( $supercachedir ) ) {
				prune_super_cache( $supercachedir, true );
			} elseif( is_dir( $supercachedir . '.disabled' ) ) {
				prune_super_cache( $supercachedir . '.disabled', true );
			}
			prune_super_cache( $cache_path, true );
			$_POST[ 'super_cache_stats' ] = 1; // regenerate super cache stats;
		}
		return wp_cache_phase2_clean_cache($file_prefix);
	}

	$expr = "/^$file_prefix/";
	if ( ($handle = opendir( $cache_path )) ) { 
		while ( false !== ($file = readdir($handle))) {
			if ( preg_match($expr, $file) ) {
				unlink($cache_path . $file);
				unlink($cache_path . 'meta/' . str_replace( '.html', '.term', $file ) );
			}
		}
		closedir($handle);
	}
}

function wp_cache_clean_expired($file_prefix) {
	global $cache_path, $cache_max_time;

	// If phase2 was compiled, use its function to avoid race-conditions
	if(function_exists('wp_cache_phase2_clean_expired')) {
		if (function_exists ('prune_super_cache')) {
			$dir = $cache_path . 'supercache/' . preg_replace('/:.*$/', '',  $_SERVER["HTTP_HOST"]);
			if( is_dir( $dir ) ) {
				prune_super_cache( $dir );
			} elseif( is_dir( $dir . '.disabled' ) ) {
				prune_super_cache( $dir . '.disabled' );
			}
			$_POST[ 'super_cache_stats' ] = 1; // regenerate super cache stats;
		}
		return wp_cache_phase2_clean_expired($file_prefix);
	}

	$expr = "/^$file_prefix/";
	$now = time();
	if ( ($handle = opendir( $cache_path )) ) { 
		while ( false !== ($file = readdir($handle))) {
			if ( preg_match($expr, $file)  &&
				(filemtime($cache_path . $file) + $cache_max_time) <= $now) {
				unlink($cache_path . $file);
				unlink($cache_path . 'meta/' . str_replace( '.html', '.term', $file ) );
			}
		}
		closedir($handle);
	}
}

add_action('admin_menu', 'wp_cache_add_pages');

function wp_super_cache_footer() {
	?><p><?php bloginfo('name'); ?> is Digg proof thanks to caching by <a href="http://ocaoimh.ie/wp-super-cache/">WP Super Cache</a>!</p><?php
}
if( isset( $wp_cache_hello_world ) && $wp_cache_hello_world )
	add_action( 'wp_footer', 'wp_super_cache_footer' );

if( get_option( 'gzipcompression' ) )
	update_option( 'gzipcompression', 0 );

?>
