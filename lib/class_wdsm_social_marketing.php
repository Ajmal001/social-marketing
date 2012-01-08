<?php
/**
 * Main CPT handler and model class.
 * Modified singleton pattern: see init() and get_instance()
 */
class Wdsm_SocialMarketing {

	/**
	 * Supported services.
	 */
	private $_services = array(
		'facebook' => array (
			'file' => 'facebook',
			'instance' => false,
		),
		'linkedin' => array (
			'file' => 'linkedin',
			'instance' => false,
		),
		'twitter' => array (
			'file' => 'twitter',
			'instance' => false,
		),
		'google' => array (
			'file' => 'google',
			'instance' => false,
		),
	);
	
	/**
	 * Supported styles.
	 * Initialized in constructor, for l10n support in labels.
	 */
	private $_styles = array();
	
	private static $_instance;

	private function __construct () {
		$this->_styles = array(
			'yellow' => __('Yellow', 'wdsm'),
			'blue' => __('Blue', 'wdsm'),
			'grey' => __('Grey', 'wdsm'),
			'red' => __('Red', 'wdsm'),
		);
		$this->_load_dependencies();
	}

	/**
	 * Loads service dependencies.
	 */
	private function _load_dependencies () {
		require_once WDSM_PLUGIN_BASE_DIR . '/lib/class_wdsm_service.php';
		foreach ($this->_services as $class => $service) {
			$realclass = "Wdsm_{$class}Service";
			$file = $service['file'];
			if (!class_exists($realclass)) require_once WDSM_PLUGIN_BASE_DIR . "/lib/services/class_wdsm_{$file}_service.php";
			if (!class_exists($realclass)) throw new Wdsm_Exception(sprintf(__("Unsupported service: %s", 'wdsm'), $class));
			$this->_services[$class]['instance'] = new $realclass;
		}
	}

	/**
	 * Glues everything together and initialize singleton.
	 */
	public static function init () {
		if (!isset(self::$_instance)) self::$_instance = new Wdsm_SocialMarketing;

		add_action('init', array(self::$_instance, 'register_post_type'));
		add_action('admin_init', array(self::$_instance, 'add_meta_boxes'));
		add_action('save_post', array(self::$_instance, 'save_meta'), 9); // Bind it a bit earlier, so we can kill Post Indexer actions.

		add_action('admin_print_scripts-post.php', array(self::$_instance, 'js_print_admin_scripts'));
		add_action('admin_print_scripts-post-new.php', array(self::$_instance, 'js_print_admin_scripts'));
		add_action('admin_print_styles-post.php', array(self::$_instance, 'css_print_admin_styles'));
		add_action('admin_print_styles-post-new.php', array(self::$_instance, 'css_print_admin_styles'));

		add_filter("manage_edit-social_marketing_ad_columns", array(self::$_instance, "add_custom_columns"));
		add_action("manage_posts_custom_column",  array(self::$_instance, "fill_custom_columns"));
	}

	/**
	 * Prepared singleton object getting routine.
	 */
	public function get_instance () {
		return self::$_instance;
	}
	
	/**
	 * Get supported styles.
	 */
	public function get_styles () {
		return $this->_styles;
	}
	
/* ----- Handlers ----- */

	/**
	 * Registers custom columns.
	 */
	public function add_custom_columns ($cols) {
		return array_merge($cols, array(
			'wdsm_type' => __('Type', 'wdsm'),
			'wdsm_services' => __('Services', 'wdsm'),
		));
	}
	
	/**
	 * Fills registered custom columns.
	 */
	public function fill_custom_columns ($col) {
		global $post;
		if ('wdsm_type' != $col && 'wdsm_services' != $col) return $col;
		$wdsm = get_post_meta($post->ID, 'wdsm', true);
		$wdsm = $wdsm ? $wdsm : array();

		if ('wdsm_type' == $col) echo ('download_url' == $wdsm['type']) ? __('Download URL', 'wdsm') : __('Coupon code', 'wdsm');
		if ('wdsm_services' == $col) {
			$ret = array();
			if (@$wdsm['services']) foreach ($wdsm['services'] as $id => $service) {
				if ($service) $ret[] = ucfirst($id);
			}
			echo join(', ', $ret);
		}
	}

	/**
	 * Adds metabox javascript.
	 */
	public function js_print_admin_scripts () {
		wp_enqueue_script('wdsm_meta_boxes', WDSM_PLUGIN_URL . '/js/meta_boxes.js', array('jquery'));
	}

	/**
	 * Adds metabox css.
	 */
	public function css_print_admin_styles () {
		wp_enqueue_style('wdsm_meta_boxes', WDSM_PLUGIN_URL . '/css/meta_boxes.css');
	}

	/**
	 * Registers CPT.
	 */
	public function register_post_type () {
		register_post_type('social_marketing_ad', array(
			'labels' => array(
				'name' => __('Social Marketing', 'wdsm'),
				'singular_name' => __('Social Marketing Advert', 'wdsm'),
				'add_new_item' => __('Add new Social Marketing Advert', 'wdsm'),
				'edit_item' => __('Edit Social Marketing Advert', 'wdsm'),
			),
			'menu_icon' => WDSM_PLUGIN_URL . '/img/menu_inactive.png',
			'public' => true,
			'supports' => array(
				'title', 'editor', 'comments'
			),
			'rewrite' => true,
		));
	}

	/**
	 * Registers metaboxes.
	 */
	public function add_meta_boxes () {
		add_meta_box(
			'wdsm_services',
			__('Set up Social Marketing', 'wdsm'),
			array($this, 'render_services_box'),
			'social_marketing_ad',
			'normal',
			'high'
		);
	}

	/**
	 * Renders metabox.
	 */
	public function render_services_box () {
		global $post;
		$wdsm = get_post_meta($post->ID, 'wdsm', true);
		$wdsm = $wdsm ? $wdsm : array();

		$checked_du = ('download_url' == @$wdsm['type']) ? 'selected="selected"' : '';
		$checked_cc = ('coupon_code' == @$wdsm['type']) ? 'selected="selected"' : '';
		$download_url = @$wdsm['result']['download_url'];
		$coupon_code = @$wdsm['result']['coupon_code'];
		$share_text = @$wdsm['share_text'];
		$url = @$wdsm['url'];
		$button_text = @$wdsm['button_text'];
		echo
			"<label for='wdsm_url'>" . __('URL to Share', 'wdsm') . '</label> ' .
			"<input type='text' class='widefat' id='wdsm_url' name='wdsm[url]' value='{$url}' />" .
		'<br />';
		echo
			"<label for='wdsm_button_text'>" . __('Button Text', 'wdsm') . '</label> ' .
			"<input type='text' class='widefat' id='wdsm_button_text' name='wdsm[button_text]' value='{$button_text}' />" .
		'<br />';
		echo
			"<label for='wdsm_type'>" . __('Type', 'wdsm') . '</label> ' .
			"<select class='widefat' id='wdsm_type' name='wdsm[type]'>" .
				"<option wdsm:for='wdsm_download_url' value='download_url' {$checked_du}>" . __('Download URL', 'wdsm') . '</option>' .
				"<option wdsm:for='wdsm_coupon_code' value='coupon_code' {$checked_cc}>" . __('Coupon code', 'wdsm') . '</option>' .
			"</select>" .
		'<br />';

		echo '<div id="wdsm_share_root">';
		echo '<div id="wdsm_download_url" class="wdsm_result_item">';
		echo
			"<label for='wdsm_result-download_url'>" . __('Download URL', 'wdsm') . '</label> ' .
			"<input type='text' class='widefat' id='wdsm_result-download_url' name='wdsm[result][download_url]' value='{$download_url}' />" .
		'<br />';
		echo '</div>';
		echo '<div id="wdsm_coupon_code" class="wdsm_result_item">';
		echo
			"<label for='wdsm_result-coupon_code'>" . __('Coupon Code', 'wdsm') . '</label> ' .
			"<textarea class='widefat' id='wdsm_result-coupon_code' name='wdsm[result][coupon_code]'>{$coupon_code}</textarea>" .
		'<br />';
		echo '</div>';
		echo '</div>'; // Share root;
		echo
			"<label for='wdsm_share_text'>" . __('Thank You Text <small>(will be shown after share action)</small>', 'wdsm') . '</label> ' .
			"<textarea class='widefat' id='wdsm_share_text' name='wdsm[share_text]'>{$share_text}</textarea>" .
		'<br />';

		echo '<table class="widefat" id="wdsm-services_box">';
		foreach (array('thead', 'tfoot') as $el) {
			echo "<{$el}><tr>";
			echo '<th colspan="4">' . __('Social Media Services', 'wdsm') . '</th>';
			echo "</tr></{$el}>";
		}
		echo '<tbody><tr>';
		$cnt = 1;
		foreach ($this->_services as $class => $service) {
			if ($cnt%2) echo '</tr><tr>';
			if (!$service['instance']) continue;
			$id = $service['instance']->get_setting('id');
			$sel = (!isset($wdsm['services'][$id]) || $wdsm['services'][$id]);
			echo '<th>' . ucfirst($class) . '</th>';
			echo '<td>' . $service['instance']->get_meta_box($sel) . '</td>';
			$cnt++;
		}
		echo '</tr></tbody>';
		echo '</table>';
	}

	/**
	 * Saves metabox data.
	 */
	public function save_meta () {
		global $post;
		// If we have Post Indexer present, remove the post save action for the moment.
		if (function_exists('post_indexer_post_insert_update')) {
			remove_action('save_post', 'post_indexer_post_insert_update');
		}
		if (@$_POST['wdsm']) {
			update_post_meta($post->ID, "wdsm", $_POST["wdsm"]);
		}
	}
	
/* ----- Model procedures ----- */

	/**
	 * Gets a list of supported services.
	 */
	public function get_services () {
		return $this->_services;
	}

	/**
	 * Gets a single, complete Ad object.
	 */
	public function get_ad ($id) {
		$ad = get_post($id);
		$ad->wdsm = $this->get_ad_meta($ad->ID);
		return $ad;
	}

	/**
	 * Gets ad meta information.
	 */
	public function get_ad_meta ($id) {
		$meta = get_post_meta($id, 'wdsm', true);
		$meta = $meta ? $meta : array();
		$wdsm = new stdClass;
		foreach ($meta as $key=>$val) {
			$wdsm->$key = $val;
		}
		return $wdsm;
	}

	/**
	 * Gets a list of all ads.
	 */
	public function get_all_ads () {
		$q = new Wp_Query(array(
			'post_type' => 'social_marketing_ad',
			'posts_per_page' => -1,
			'orderby' => 'title',
		));
		$ret = array();
		foreach ($q->posts as $ad) {
			$ad->wdsm = $this->get_ad_meta($ad->ID);
			$ret[] = $ad;
		}
		return $ret;
	}

	public function get_service_markup ($id, $type, $ad=false) {
		return $this->_services[$id]['instance']->render($type, $ad);
	}

	public function add_service_js ($id) {
		return $this->_services[$id]['instance']->add_js();
	}
	public function add_service_handler_js ($id) {
		return $this->_services[$id]['instance']->add_handler_js();
	}
}