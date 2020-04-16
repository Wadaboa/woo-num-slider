<?php

/**
 * Plugin Name: Woocommerce Numeric Slider
 * Plugin URI: https://alessiofalai.it
 * Description: A simple widget to filter Woocommerce products by a numeric attribute using a slider.
 * Version: 0.1.1
 * Author: Alessio Falai
 * Author URI: https://alessiofalai.it
 */
class Woo_Num_Slider extends WP_Widget
{
	public $product_query;

	/* Constructor */
	function __construct()
	{
		$widget_options = array(
			'classname' => 'woo-num-slider',
			'description' => __('A simple widget to filter Woocommerce products by a numeric attribute using a slider.', 'woo-num-slider')
		);
		add_action('woocommerce_product_query', [$this, 'filter_by_current_range']);
		add_action('plugins_loaded', [$this, 'load_our_textdomain']);
		add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
		parent::__construct('woo-num-slider', 'Woocommerce Numeric Slider', $widget_options);

		/* Compatibility with PHP < 7.3.0 */
		if (!function_exists('array_key_first')) {
			function array_key_first(array $arr)
			{
				foreach ($arr as $key => $unused) {
					return $key;
				}
				return NULL;
			}
		}
	}

	/* Load textdomain */
	public static function load_our_textdomain()
	{
		load_plugin_textdomain('woo-num-slider', false, dirname(plugin_basename(__FILE__)) . '/lang/');
	}

	/* Load jQuery UI */
	public function enqueue_scripts()
	{
		global $wp_scripts;
		wp_enqueue_script('jquery-ui-slider');
		$ui = $wp_scripts->query('jquery-ui-core');
		$protocol = is_ssl() ? 'https' : 'http';
		$url = "$protocol://ajax.googleapis.com/ajax/libs/jqueryui/{$ui->ver}/themes/smoothness/jquery-ui.min.css";
		wp_enqueue_style('jquery-ui-smoothness', $url, false, null);
	}

	/* Output the widget form */
	public function form($instance)
	{
		$title = !empty($instance['title']) ? $instance['title'] : __('New title', 'text_domain');
		$attributes = get_wc_attributes();
		$attribute = !empty($instance['attribute']) ? $instance['attribute'] : array_key_first($attributes);
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>">
			<label for="<?php echo $this->get_field_id('attribute'); ?>"><?php _e('Attribute:'); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id('attribute'); ?>" name="<?php echo $this->get_field_name('attribute'); ?>">
				<?php foreach ($attributes as $key => $value) : ?>
					<option <?php selected($attribute, $key); ?> value="<?php echo $key ?>"><?php echo $value->attribute_label ?></option>
				<?php endforeach ?>
			</select>
		</p>
	<?php
	}

	/* Data saved by the widget */
	public function update($new_instance, $old_instance)
	{
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['attribute'] = $new_instance['attribute'];
		return $instance;
	}

	/* Show the widget on the site */
	function widget($args, $instance)
	{
		// Exit if we are not in the shop
		if (!is_shop() && !is_product_taxonomy()) {
			return;
		}

		// Extract attribute values
		$attributes = get_wc_attributes();
		$attribute = $attributes[$instance['attribute']];
		$terms = get_terms('pa_' . $attribute->attribute_name);

		// If there are not posts and we're not filtering, hide the widget
		$min_value_key = $attribute->attribute_name . '_min_value';
		$max_value_key = $attribute->attribute_name . '_max_value';
		if (!WC()->query->get_main_query()->post_count && !isset($_GET[$min_value_key]) && !isset($_GET[$max_value_key])) {
			return;
		}

		// Extract max and min values
		$min_value = INF;
		$max_value = -INF;
		foreach ($terms as $key => $term) {
			$value = to_numerical($term->name);
			if ($value) {
				if ($value < $min_value) {
					$min_value = $value;
				} else if ($value > $max_value) {
					$max_value = $value;
				}
			}
		}

		// No good values, exit
		if ($min_value == INF || $max_value == -INF || $min_value == $max_value) {
			return;
		}

		// Display before widget and widget title
		echo $args['before_widget'];
		if (!empty($instance['title'])) {
			echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
		}

		// Round values to nearest ten
		if ($max_value - $min_value > 10) {
			$min_value = floor($min_value / 10) * 10;
			$max_value = ceil($max_value / 10) * 10;
		}

		// Set slider options
		$terms_num = count(get_terms('pa_' . $attribute->attribute_name));
		$step = (int) (($max_value - $min_value) / $terms_num);
		$current_min_value = isset($_GET[$min_value_key]) ? floor(to_numerical(wp_unslash($_GET[$min_value_key])) / $step) * $step : $min_value;
		$current_max_value = isset($_GET[$max_value_key]) ? ceil(to_numerical(wp_unslash($_GET[$max_value_key])) / $step) * $step : $max_value;

		global $wp;
		if ('' === get_option('permalink_structure')) {
			$form_action = remove_query_arg(array('page', 'paged', 'product-page'), add_query_arg($wp->query_string, '', home_url($wp->request)));
		} else {
			$form_action = preg_replace('%\/page/[0-9]+%', '', home_url(trailingslashit($wp->request)));
		}

		// Show the form
		$id = 'woo_num_slider_' . $attribute->attribute_name;
	?>
		<form id="<?php echo $id; ?>" class="widget woocommerce widget_price_filter" method="get" action="<?php echo esc_url($form_action); ?>">
			<div class="woo_num_slider_wrapper price_slider_wrapper">
				<div class="woo_num_slider price_slider"></div>
				<div class="woo_num_slider_amount" style="line-height: 2.4em; text-align: right;">
					<input type="hidden" id="<?php echo $min_value_key; ?>" name="<?php echo $min_value_key; ?>" value="<?php echo esc_attr($current_min_value); ?>" data-min="<?php echo esc_attr($min_value); ?>" placeholder="<?php echo esc_attr__('Min value', 'woocommerce'); ?>" />
					<input type="hidden" id="<?php echo $max_value_key; ?>" name="<?php echo $max_value_key; ?>" value="<?php echo esc_attr($current_max_value); ?>" data-max="<?php echo esc_attr($max_value); ?>" placeholder="<?php echo esc_attr__('Max value', 'woocommerce'); ?>" />
					<button type="submit" class="button" style="padding: 10px 30px; line-height: 13px; float: left;"><?php echo esc_html__('Filter', 'woocommerce'); ?></button>
					<div class="woo_num_label price_label">
						<?php echo esc_html__('Value:', 'woocommerce'); ?> <span class="from"><?php echo $current_min_value; ?></span> &mdash; <span class="to"><?php echo $current_max_value; ?></span>
					</div>
					<?php echo wc_query_string_form_fields(null, array($min_value_key, $max_value_key, 'paged'), '', true); ?>
					<div class="clear"></div>
				</div>
			</div>
		</form>

		<script>
			(function($) {
				$(function() {
					$("#<?php echo $id; ?> .woo_num_slider_wrapper .woo_num_slider").slider({
						range: true,
						animate: true,
						min: <?php echo $min_value; ?>,
						max: <?php echo $max_value; ?>,
						step: <?php echo $step; ?>,
						values: [<?php echo $current_min_value; ?>, <?php echo $current_max_value; ?>],
						slide: function(event, ui) {
							$("#<?php echo $id; ?> .woo_num_slider_wrapper .woo_num_slider_amount #<?php echo $min_value_key; ?>").val(ui.values[0]);
							$("#<?php echo $id; ?> .woo_num_slider_wrapper .woo_num_slider_amount .woo_num_label .from").html(ui.values[0]);
							$("#<?php echo $id; ?> .woo_num_slider_wrapper .woo_num_slider_amount #<?php echo $max_value_key; ?>").val(ui.values[1]);
							$("#<?php echo $id; ?> .woo_num_slider_wrapper .woo_num_slider_amount .woo_num_label .to").html(ui.values[1]);
						}
					});
				});
			})(jQuery);
		</script>
<?php

		// Display after widget
		echo $args['after_widget'];
	}

	/* Filter the products */
	public function filter_by_current_range($query)
	{
		$this->product_query = $query;
		$query->set('tax_query', $this->get_tax_query());
	}

	/* Build the products query */
	public function get_tax_query()
	{
		// Extract keys from GET
		$min_value_keys = array_filter_key($_GET, function ($key) {
			return ends_with($key, 'min_value');
		});
		ksort($min_value_keys);
		$max_value_keys = array_filter_key($_GET, function ($key) {
			return ends_with($key, 'max_value');
		});
		ksort($max_value_keys);

		// If we don't have one of max or min, exit
		if (count($min_value_keys) != count($max_value_keys)) {
			return;
		}

		// Get attribute names
		$attribute_names = array_map(function ($key) {
			return explode('_', $key)[0];
		}, array_keys($min_value_keys));

		// Compose tax query
		$tax_query = array('relation' => 'AND');
		foreach ($attribute_names as $key => $attribute_name) {
			$min_value = to_numerical($_GET[$attribute_name . '_min_value']);
			$max_value = to_numerical($_GET[$attribute_name . '_max_value']);

			$terms = get_terms('pa_' . $attribute_name);
			$amps = [];
			foreach ($terms as $key => $term) {
				$value = to_numerical($term->name);
				if ($value && $value <= $max_value && $value >= $min_value) {
					$amps[] = $term->term_id;
				}
			}

			array_push($tax_query, array(
				'taxonomy' => 'pa_' . $attribute_name,
				'terms'    => $amps,
				'operator' => 'IN'
			));
		}

		if (!empty($this->product_query->get('tax_query'))) {
			$tax_query = array_merge($this->product_query->get('tax_query'), $tax_query);
		}

		return $tax_query;
	}
}

/* Register the widget */
function register_woo_num_slider()
{
	register_widget('Woo_Num_Slider');
}
add_action('widgets_init', 'register_woo_num_slider');

/* Get Woocommerce attributes */
function get_wc_attributes()
{
	global $wpdb;
	$raw_attribute_taxonomies = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name != '' ORDER BY attribute_name ASC;");
	$raw_attribute_taxonomies = (array) array_filter(apply_filters('woocommerce_attribute_taxonomies', $raw_attribute_taxonomies));

	$attribute_taxonomies = array();
	foreach ($raw_attribute_taxonomies as $result) {
		$attribute_taxonomies['id:' . $result->attribute_id] = $result;
	}

	return $attribute_taxonomies;
}

/* Convert a string representing a number to a float */
function to_numerical($attr)
{
	if (is_numeric($attr)) {
		return floatval($attr);
	}
	if (is_string($attr) && strpos($attr, '/') !== false) {
		$exploded = explode('/', $attr);
		if (count($exploded) > 2 || !is_numeric($exploded[0]) || !is_numeric($exploded[1])) {
			return false;
		}
		$num = floatval($exploded[0]);
		$den = floatval($exploded[1]);
		return $num / $den;
	}
	return false;
}

/* Filter an array by keys */
function array_filter_key($input, $callback)
{
	if (!is_array($input)) {
		trigger_error('array_filter_key() expects parameter 1 to be array, ' . gettype($input) . ' given', E_USER_WARNING);
		return null;
	}

	if (empty($input)) {
		return $input;
	}

	$filtered_keys = array_filter(array_keys($input), $callback);
	if (empty($filtered_keys)) {
		return array();
	}

	$input = array_intersect_key(array_flip($filtered_keys), $input);

	return $input;
}

/* Check if a string ends with another string*/
function ends_with($haystack, $needle)
{
	$length = strlen($needle);
	if ($length == 0) {
		return true;
	}

	return (substr($haystack, -$length) === $needle);
}

?>
