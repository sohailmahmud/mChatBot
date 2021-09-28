<?php

/*
 * ==========================================================
 * WORDPRESS APP
 * ==========================================================
 *
 * WordPress App main file. © 2021 board.support. All rights reserved.
 *
 */

if (!defined('SB_WP')) define('SB_WP', true);

/*
 * -----------------------------------------------------------
 * USER FUNCTIONS
 * -----------------------------------------------------------
 *
 * 1. Returns the details of a WordPress user
 * 2. Get the active WordPress user and register it if it's not a Support Board user
 * 3. Import the WordPress users into Support Board
 * 4. Get a WordPress user from its email
 *
 */

function sb_wp_get_user($user_id) {
    $user_data = sb_db_get('SELECT * FROM ' . SB_WP_PREFIX . 'users WHERE ID = "' . $user_id . '" LIMIT 1');
    if (!sb_is_error($user_data) && isset($user_data['ID'])) {
        $user_meta = sb_db_get('SELECT meta_key, meta_value FROM ' . SB_WP_PREFIX . 'usermeta WHERE user_id = "' . $user_id . '" AND (meta_key = "first_name" OR meta_key = "last_name")', false);
        if (!sb_is_error($user_data) && is_array($user_meta)) {
            for ($i = 0; $i < count($user_meta); $i++) {
                $user_data[$user_meta[$i]['meta_key']] = $user_meta[$i]['meta_value'];
            }
        }
        return ['id' => $user_id, 'first_name' => ucfirst(empty($user_data['first_name']) ? $user_data['user_login'] : $user_data['first_name']), 'last_name' => ucfirst($user_data['last_name']), 'email' => $user_data['user_email'], 'password' => $user_data['user_pass']];
    }
    return false;
}

function sb_wp_get_active_user($login_app) {
    $user_id = $login_app[0];
    $wp_user = sb_wp_get_user($user_id);
    $query = '';
    if (isset($wp_user['email'])) {
        $query = 'SELECT id, token FROM sb_users WHERE email = "' . $wp_user['email'] . '" LIMIT 1';
        $user_db = sb_db_get($query);
        $active_user = sb_get_active_user(false, true);
        if ($user_db === '') {
            if (!empty($login_app[1]) && (strpos($login_app[1], '.jpg') || strpos($login_app[1], '.png'))) {
                $wp_user['profile_image'] = $login_app[1];
            }
            $settings_extra = ['wp-id' => [$wp_user['id'], 'WordPress ID']];
            if ($active_user && ($active_user['user_type'] == 'lead' || $active_user['user_type'] == 'visitor')) {
                sb_update_user($active_user['id'], $wp_user, $settings_extra, false);
            } else {
                sb_add_user($wp_user, $settings_extra, false, false);
            }
            $wp_user = sb_db_get($query);
        } else {
            if ($active_user) {
                sb_db_query('UPDATE sb_conversations SET user_id = ' . $user_db['id'] . ' WHERE user_id = ' . $active_user['id']);
            }
            $wp_user = $user_db;
        }
        if (sb_is_error($wp_user) || !isset($wp_user['token']) || !isset($wp_user['id'])) {
            return false;
        } else {
            return sb_login('', '', $wp_user['id'], $wp_user['token']);
        }
    } else {
        return false;
    }
}

function sb_wp_synch() {
    $emails = sb_db_get('SELECT email FROM sb_users WHERE email <> ""', false);
    $emails_array = [];
    $users = sb_db_get('SELECT a.*, b.meta_value first_name, c.meta_value last_name FROM ' . SB_WP_PREFIX . 'users a LEFT JOIN ' . SB_WP_PREFIX . 'usermeta b ON a.ID = b.user_id AND b.meta_key = "first_name" LEFT JOIN ' . SB_WP_PREFIX . 'usermeta c ON a.ID = c.user_id AND c.meta_key = "last_name"', false);
    if (sb_is_error($users)) return $users;
    for ($i = 0; $i < count($emails); $i++) {
        array_push($emails_array, $emails[$i]['email']);
    }
    for ($i = 0; $i < count($users); $i++) {
        if (!in_array($users[$i]['user_email'], $emails_array)) {
            $settings = ['first_name' => $users[$i]['first_name'] == '' ? ucfirst($users[$i]['user_login']) : $users[$i]['first_name'], 'last_name' => $users[$i]['last_name'], 'password' => $users[$i]['user_pass'], 'email' => $users[$i]['user_email']];
            $settings_extra = [];
            $user_id = $users[$i]['ID'];
            if (defined('SB_UMP')) {
                $settings_extra = sb_ump_get_user_extra($user_id);
                $settings['profile_image'] = sb_ump_get_profile_image($user_id, 'user');
            }
            if (empty($settings['profile_image']) && function_exists('get_avatar_url')) {
                $settings['profile_image'] = get_avatar_url($user_id, ['size' => '500']);
            }
            if (defined('SB_ARMEMBER')) {
                $settings_extra = sb_armember_get_user_extra($user_id);
            }
            $settings_extra['wp-id'] = [$users[$i]['ID'], 'WordPress ID'];
            sb_add_user($settings, $settings_extra);
        }
    }
    return true;
}

function sb_wp_get_user_id($email) {
    return sb_isset(sb_db_get('SELECT id FROM ' . SB_WP_PREFIX . 'users WHERE user_email = "' . $email . '"'), 'id');
}

/*
 * -----------------------------------------------------------
 * CONFIG
 * -----------------------------------------------------------
 *
 * Return the WordPress information to insert into the config.php file
 *
 */

function sb_wp_config() {
    global $wpdb;
    $upload_directory = wp_upload_dir();
    if (!file_exists($upload_directory['basedir'] . '/sb')) {
        mkdir($upload_directory['basedir'] . '/sb', 0777, true);
    }
    return '/* WordPress prefix */' . PHP_EOL . 'define(\'SB_WP_PREFIX\', \'' . $wpdb->prefix . '\');' . PHP_EOL . PHP_EOL . '/* Upload path */'. PHP_EOL . 'define(\'SB_UPLOAD_PATH\', \'' . $upload_directory['basedir'] . '/sb\');' . PHP_EOL . PHP_EOL . '/* Upload url */'. PHP_EOL . 'define(\'SB_UPLOAD_URL\', \'' . $upload_directory['baseurl'] . '/sb\');' . PHP_EOL . PHP_EOL . '/* [extra] */';
}

/*
 * -----------------------------------------------------------
 * WORDPRESS BASE FUNCTIONS
 * -----------------------------------------------------------
 *
 * 1. Create a new post
 * 2. Create a new option
 * 3. Return the value of an option
 * 4. Return an image of the media library
 * 5. Return the site url
 *
 */

function sb_wp_post($post_title = '', $post_content = '', $post_type = 'post') {
    return sb_db_query('INSERT INTO ' . SB_WP_PREFIX . 'posts (post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, comment_status, ping_status, post_name, to_ping, pinged, post_modified, post_modified_gmt, post_content_filtered, guid, post_type, comment_count) VALUES (NOW(), NOW(), "' . sb_db_escape($post_content) . '", "' . sb_db_escape($post_title) . '", "", "publish", "closed", "closed", "' . sb_db_escape($post_title) . '", "", "", NOW(), NOW(), "", "", "' . $post_type . '", 0)', true);
}

function sb_wp_update_option($name, $value) {
    return sb_db_query('INSERT INTO ' . SB_WP_PREFIX . 'options (option_name, option_value) VALUES ("' . sb_db_escape($name) . '", "' . sb_db_escape($value) . '") ON DUPLICATE KEY UPDATE option_value = \'' . sb_db_escape($value) . '\'', true);
}

function sb_wp_get_option($name) {
    return sb_isset(sb_db_get('SELECT option_value FROM ' . SB_WP_PREFIX . 'options WHERE option_name = "' . sb_db_escape($name) . '"'), 'option_value');
}

function sb_wp_get_image($image_id, $size = 1000) {
    $image = sb_db_get('SELECT meta_value FROM ' . SB_WP_PREFIX . 'postmeta WHERE meta_key = "_wp_attachment_metadata" AND post_id = ' . $image_id);
    if (!empty($image)) {
        $image = unserialize($image['meta_value']);
        if (isset($image['width'])) {
            $image_url = $image['file'];
            if (intval($image['width']) > $size) {
                foreach ($image['size'] as $image_size) {
                    if ($image_size['width'] < $size) {
                        $image_url = $image_size['file'];
                        break;
                    }
                }
            }
            return sb_wp_site_url() . '\wp-content\uploads\\' . $image_url;
        }
    }
    return '';
}

function sb_wp_site_url() {
    return sb_isset(sb_db_get('SELECT option_value FROM ' . SB_WP_PREFIX . 'options WHERE option_name = "siteurl"'), 'option_value', '');
}

/*
 * -----------------------------------------------------------
 * MULTILINGUAL FUNCTIONS
 * -----------------------------------------------------------
 *
 * Multilingual functions for WPML and Polylang
 *
 */

function sb_wp_language_settings($plugin = false) {
    if ($plugin === false) $plugin = sb_get_setting('wp-multilingual-plugin');
    if ($plugin == 'wpml') {
        $settings = sb_wp_get_option('icl_sitepress_settings');
        if ($settings) {
            $settings = unserialize($settings);
            $post_types = [];
            $taxonomies = [];
            foreach ($settings['custom_posts_sync_option'] as $key => $value) {
                if ($value) array_push($post_types, $key);
            }
            foreach ($settings['taxonomies_sync_option'] as $key => $value) {
                if ($value) array_push($taxonomies, $key);
            }
            return ['default' => $settings['admin_default_language'], 'languages' => $settings['active_languages'], 'post-types' => $post_types, 'taxonomies' => $taxonomies, 'link-type' => $settings['language_negotiation_type'], 'plugin' => $plugin];
        } else {
            return new SBError('wpml-db-row-not-found', 'sb_wp_language_settings');
        }
    } else if ($plugin == 'polylang') {
        $settings = sb_db_get('SELECT option_value FROM ' . SB_WP_PREFIX . 'options WHERE option_name = "polylang" OR option_name = "_transient_pll_languages_list" ORDER BY option_name ASC', false);
        if (is_array($settings) && count($settings) > 1) {
            $settings = [unserialize($settings[0]['option_value']), unserialize($settings[1]['option_value'])];
            $active_languages = [];
            $mo_ids = [];
            for ($i = 0; $i < count($settings[0]); $i++) {
                $language_code = $settings[0][$i]['slug'];
                array_push($active_languages, $language_code);
                $mo_ids[$language_code] = $settings[0][$i]['mo_id'];
            }
            if (defined('SB_WOOCOMMERCE')) {
                $attributes = sb_db_get('SELECT attribute_name FROM ' . SB_WP_PREFIX . 'woocommerce_attribute_taxonomies', false);
                for ($i = 0; $i < count($attributes); $i++) {
                    array_push($settings[1]['taxonomies'], 'pa_' . $attributes[$i]['attribute_name']);
                }
            }
            return ['default' => $settings[1]['default_lang'], 'languages' => $active_languages, 'post-types' => $settings[1]['post_types'], 'taxonomies' => $settings[1]['taxonomies'], 'link-type' => $settings[1]['force_lang'], 'plugin' => $plugin, 'extra' => ['mo' => $mo_ids]];
        } else {
            return new SBError('polylang-db-row-not-found', 'sb_wp_language_settings');
        }
    }
    return false;
}

function sb_wp_language_get_page_id($page_id, $language, $plugin = false) {
    if ($plugin === false) $plugin = sb_get_setting('wp-multilingual-plugin');
    if ($plugin == 'wpml') {
        $id = sb_db_get('SELECT b.element_id FROM ' . SB_WP_PREFIX . 'icl_translations a, ' . SB_WP_PREFIX . 'icl_translations b WHERE b.language_code = "' . $language . '" AND a.element_id = ' . $page_id . ' AND a.trid = b.trid LIMIT 1');
        return sb_isset($id, 'element_id', $page_id);
    } else if ($plugin == 'polylang') {
        $id = sb_db_get('SELECT description FROM ' . SB_WP_PREFIX . 'term_taxonomy t, ' . SB_WP_PREFIX . 'term_relationships r WHERE r.object_id = 49 AND r.term_taxonomy_id = t.term_taxonomy_id AND t.taxonomy = "post_translations" LIMIT 1');
        if (sb_isset($id, 'description')) {
            $id = unserialize($id['description']);
            return sb_isset($id, $language, $page_id);
        }
    }
    return $page_id;
}

function sb_wp_language_get_data($taxonomy_or_post_type, $type = 'taxonomies', $language = '') {
    $multilingual_plugin = sb_get_setting('wp-multilingual-plugin', '');
    if ($multilingual_plugin == '') return false;
    $language_settings = sb_wp_language_settings($multilingual_plugin);
    if (in_array($taxonomy_or_post_type, $language_settings[$type])) {
        if ($language == '' || !in_array($language, $language_settings['languages'])) {
            $language = $language_settings['default'];
        }
        return ['language' => $language, 'settings' => $language_settings];
    }
    return false;
}

/*
 * -----------------------------------------------------------
 * WORDPRESS AJAX
 * -----------------------------------------------------------
 *
 * Manage the WordPress ajax calls
 *
 */

function sb_wp_ajax() {
    if (sb_isset($_POST, 'type')) {
        switch ($_POST['type']) {
            case 'cart-add':
        	case 'cart-remove':
                die(defined('SB_WOOCOMMERCE') ? sb_woocommerce_update_cart($_POST['product_id'], $_POST['type']) : false);
            case 'button-purchase':
                die(sb_woocommerce_purchase_button($_POST['product_ids'], sb_isset($_POST, 'coupon'), sb_isset($_POST, 'checkout')));
            case 'url':
                die(sb_wp_get_url($_POST['url_name']));
            case 'wp-sync':
                if (sb_is_agent()) die(sb_wp_synch());
                break;
        }
        die(false);
    } else {
        die(new SBError('type-not-found', 'sb_woocommerce_wp_ajax'));
    }
}

/*
 * -----------------------------------------------------------
 * WORDPRESS URL
 * -----------------------------------------------------------
 *
 * Return the URLs of WordPress
 *
 */

function sb_wp_get_url($url_name) {
    $url = '';
    if (defined('SB_WOOCOMMERCE')) {
        $url = sb_woocommerce_get_url($url_name);
    }
    return $url;
}

/*
 * -----------------------------------------------------------
 * WORDPRESS PASSWORD HASH
 * -----------------------------------------------------------
 *
 * Original WordPress password hash framework
 *
 */

class SBPasswordHash {
	var $itoa64;
	var $iteration_count_log2;
	var $portable_hashes;
	var $random_state;

	function __construct( $iteration_count_log2, $portable_hashes ) {
		$this->itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

		if ($iteration_count_log2 < 4 || $iteration_count_log2 > 31)
			$iteration_count_log2 = 8;
		$this->iteration_count_log2 = $iteration_count_log2;

		$this->portable_hashes = $portable_hashes;

		$this->random_state = microtime() . uniqid(rand(), TRUE);
	}

	public function PasswordHash( $iteration_count_log2, $portable_hashes ) {
		self::__construct( $iteration_count_log2, $portable_hashes );
	}

	function get_random_bytes($count) {
		$output = '';
		if ( @is_readable('/dev/urandom') &&
		    ($fh = @fopen('/dev/urandom', 'rb'))) {
			$output = fread($fh, $count);
			fclose($fh);
		}

		if (strlen($output) < $count) {
			$output = '';
			for ($i = 0; $i < $count; $i += 16) {
				$this->random_state =
				    md5(microtime() . $this->random_state);
				$output .=
				    pack('H*', md5($this->random_state));
			}
			$output = substr($output, 0, $count);
		}

		return $output;
	}

	function encode64($input, $count) {
		$output = '';
		$i = 0;
		do {
			$value = ord($input[$i++]);
			$output .= $this->itoa64[$value & 0x3f];
			if ($i < $count)
				$value |= ord($input[$i]) << 8;
			$output .= $this->itoa64[($value >> 6) & 0x3f];
			if ($i++ >= $count)
				break;
			if ($i < $count)
				$value |= ord($input[$i]) << 16;
			$output .= $this->itoa64[($value >> 12) & 0x3f];
			if ($i++ >= $count)
				break;
			$output .= $this->itoa64[($value >> 18) & 0x3f];
		} while ($i < $count);

		return $output;
	}

	function gensalt_private($input) {
		$output = '$P$';
		$output .= $this->itoa64[min($this->iteration_count_log2 +
			((PHP_VERSION >= '5') ? 5 : 3), 30)];
		$output .= $this->encode64($input, 6);

		return $output;
	}

	function crypt_private($password, $setting) {
		$output = '*0';
		if (substr($setting, 0, 2) == $output)
			$output = '*1';

		$id = substr($setting, 0, 3);
		if ($id != '$P$' && $id != '$H$')
			return $output;

		$count_log2 = strpos($this->itoa64, $setting[3]);
		if ($count_log2 < 7 || $count_log2 > 30)
			return $output;

		$count = 1 << $count_log2;

		$salt = substr($setting, 4, 8);
		if (strlen($salt) != 8)
			return $output;

		if (PHP_VERSION >= '5') {
			$hash = md5($salt . $password, TRUE);
			do {
				$hash = md5($hash . $password, TRUE);
			} while (--$count);
		} else {
			$hash = pack('H*', md5($salt . $password));
			do {
				$hash = pack('H*', md5($hash . $password));
			} while (--$count);
		}

		$output = substr($setting, 0, 12);
		$output .= $this->encode64($hash, 16);

		return $output;
	}

	function gensalt_extended($input) {
		$count_log2 = min($this->iteration_count_log2 + 8, 24);
		$count = (1 << $count_log2) - 1;

		$output = '_';
		$output .= $this->itoa64[$count & 0x3f];
		$output .= $this->itoa64[($count >> 6) & 0x3f];
		$output .= $this->itoa64[($count >> 12) & 0x3f];
		$output .= $this->itoa64[($count >> 18) & 0x3f];

		$output .= $this->encode64($input, 3);

		return $output;
	}

	function gensalt_blowfish($input) {
		$itoa64 = './ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

		$output = '$2a$';
		$output .= chr(ord('0') + $this->iteration_count_log2 / 10);
		$output .= chr(ord('0') + $this->iteration_count_log2 % 10);
		$output .= '$';

		$i = 0;
		do {
			$c1 = ord($input[$i++]);
			$output .= $itoa64[$c1 >> 2];
			$c1 = ($c1 & 0x03) << 4;
			if ($i >= 16) {
				$output .= $itoa64[$c1];
				break;
			}

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 4;
			$output .= $itoa64[$c1];
			$c1 = ($c2 & 0x0f) << 2;

			$c2 = ord($input[$i++]);
			$c1 |= $c2 >> 6;
			$output .= $itoa64[$c1];
			$output .= $itoa64[$c2 & 0x3f];
		} while (1);

		return $output;
	}

	function HashPassword($password) {
		if ( strlen( $password ) > 4096 ) {
			return '*';
		}

		$random = '';

		if (CRYPT_BLOWFISH == 1 && !$this->portable_hashes) {
			$random = $this->get_random_bytes(16);
			$hash =
			    crypt($password, $this->gensalt_blowfish($random));
			if (strlen($hash) == 60)
				return $hash;
		}

		if (CRYPT_EXT_DES == 1 && !$this->portable_hashes) {
			if (strlen($random) < 3)
				$random = $this->get_random_bytes(3);
			$hash =
			    crypt($password, $this->gensalt_extended($random));
			if (strlen($hash) == 20)
				return $hash;
		}

		if (strlen($random) < 6)
			$random = $this->get_random_bytes(6);
		$hash =
		    $this->crypt_private($password,
		    $this->gensalt_private($random));
		if (strlen($hash) == 34)
			return $hash;

		return '*';
	}

	function CheckPassword($password, $stored_hash) {
		if ( strlen( $password ) > 4096 ) {
			return false;
		}

		$hash = $this->crypt_private($password, $stored_hash);
		if ($hash[0] == '*')
			$hash = crypt($password, $stored_hash);

		return $hash === $stored_hash;
	}
}

?>