<?php
/*
* Plugin Name: WooCommerce FFL Dealer
* Description: WooCommerce FFL Dealer
* Version: 1.0.0
* Plugin URI: 
* Author: myhope1227
* 
*/ 
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) :
if( !class_exists( 'woo_ffl_dealer' ) ):
class woo_ffl_dealer{

	public static function plugin_activate(){
		set_time_limit(600);
		global $wpdb;
	    $tbl_ffl_dealer = $wpdb->prefix . 'ffl_dealer';

	    $dealer_sql = "CREATE TABLE `".$tbl_ffl_dealer."` (
	                `id` int(11) NOT NULL AUTO_INCREMENT,
	                `business_name` varchar(255) DEFAULT '',
	                `street` varchar(50) DEFAULT '',
	                `city` varchar(30) DEFAULT '',
	                `state` varchar(30) DEFAULT '',
	                `zip` varchar(30) DEFAULT '',
	                `phone` varchar(30) DEFAULT '',
	                PRIMARY KEY (`id`)
	        ) ".$wpdb->get_charset_collate()." ;";

	    $tbl_zipcode = $wpdb->prefix . 'zipcode';

	    $zipcode_sql = "CREATE TABLE `".$tbl_zipcode."` (
	                `id` int(11) NOT NULL AUTO_INCREMENT,
	                `zipcode` varchar(7) DEFAULT '',
	                `lat` DOUBLE,
	                `lon` DOUBLE,
	                PRIMARY KEY (`id`)
	        ) ".$wpdb->get_charset_collate()." ;";

	    if (!function_exists('dbDelta')) {
	        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	    }

	    dbDelta($dealer_sql);
	    dbDelta($zipcode_sql);

	    $zipcode_file = plugin_dir_path( __FILE__ ).'us-zipcode.csv';
	    $row = 1;
	    $temp = array();
	    $insert_sql = "INSERT INTO {$tbl_zipcode} (zipcode, lat, lon) VALUES ";
	    if (($handle = fopen($zipcode_file, "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
				if ($row == 1){
					$row++;
					continue;
				}
				if ($row % 1000 == 1){
					$insert_sql .= implode(',', $temp);
					$wpdb->query( $insert_sql );
					$insert_sql = "INSERT INTO {$tbl_zipcode} (zipcode, lat, lon) VALUES ";
					$temp = array(sprintf("('%s', %s, %s)", str_pad($data[0], 5, "0", STR_PAD_LEFT), $data[1], $data[2]));
				}else{
					$temp[] = sprintf("('%s', %s, %s)", str_pad($data[0], 5, "0", STR_PAD_LEFT), $data[1], $data[2]);
				}
				$row++;
			}
			fclose($handle);
			$insert_sql .= implode(',', $temp);
			$wpdb->query( $insert_sql );
		}
	}

	public static function plugin_deactivate(){
		global $wpdb;
	    $tbl_ffl_dealer = $wpdb->prefix . 'ffl_dealer';

	    //$wpdb->query( "DROP TABLE IF EXISTS ".$tbl_ffl_dealer );
	    //delete_option("ffl_dealer_options");
	}

	public function instance(){
		add_action( 'admin_menu', array($this, 'plugin_admin_page') );
		add_action( 'init', array($this, 'set_frontend_hook'), 10, 2 );
		add_action( 'wp_enqueue_scripts', array($this, 'load_plugin_assets' ) );
		add_action( 'wp_ajax_search_dealers', array($this, 'search_dealers' ));
		add_action( 'wp_ajax_nopriv_search_dealers', array($this, 'search_dealers' ));
	} 

	public function set_frontend_hook(){
		add_action( 'woocommerce_before_add_to_cart_button', array($this, 'plugin_add_dealer_button'), 10);
		add_filter( 'woocommerce_add_cart_item_data', array($this, 'plugin_add_dealer_data'),10,3);
		add_filter( 'woocommerce_cart_id', array($this, 'plugin_generate_cart_id'), 10, 5);
		add_filter( 'woocommerce_get_item_data', array($this, 'plugin_add_dealer_meta'),10,2);
		add_action( 'woocommerce_checkout_create_order_line_item', array($this, 'plugin_add_order_line_dealer_meta'),10,4 );
	}

	public function plugin_admin_page() {
		add_menu_page('WooCommerce FFL Dealer Option', 'FFL Dealer', 'manage_options', 'ffl_dealer', array($this, 'plugin_setting'));
	}

	public function plugin_setting(){
		global $wpdb;
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'woocommerce_admin_styles', WC()->plugin_url() . '/assets/css/admin.css', array(), '1.0.1');
		$tbl_ffl_dealer = $wpdb->prefix . 'ffl_dealer';
		if (isset($_REQUEST['save'])){
			if( ! empty( $_FILES ) ) 
       		{
       			$file = $_FILES['ffl_dealer_file'];
       			
       			require_once( ABSPATH . 'wp-admin/includes/admin.php' );
      			$file_result = wp_handle_upload( $file, array('test_form' => false ) );
      			if( !isset( $file_result['error'] ) && !isset( $file_result['upload_error_handler'] ) ) 
				{
					$row = 1;
					if (($handle = fopen($file_result['file'], "r")) !== FALSE) {
						$delete = $wpdb->query("TRUNCATE TABLE `".$tbl_ffl_dealer."`");
						while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
							if ($row != 1){
								$wpdb->insert($tbl_ffl_dealer, array(
									'business_name' => $data[0],
									'street' 		=> $data[1],
									'city' 			=> $data[2],
									'state' 		=> $data[3],
									'zip' 			=> $data[4],
									'phone' 		=> $data[5]
								));
							}
							$row++;
						}
						fclose($handle);
					}
				}
			}
			$ffl_categories = $_REQUEST['ffl_dealer_categories'];
			$ffl_products = $_REQUEST['ffl_dealer_products'];
			update_option( 'ffl_dealer_categories', implode(',', $ffl_categories) );
			update_option( 'ffl_dealer_products', implode(',', $ffl_products) );
			update_option( 'google_api_key', $_REQUEST['google_api_key'] );
		}

		$orderby = 'name';
		$order = 'asc';
		$hide_empty = false ;
		$cat_args = array(
		    'orderby'    => $orderby,
		    'order'      => $order,
		    'hide_empty' => $hide_empty,
		);
		 
		$product_categories = get_terms( 'product_cat', $cat_args );

		$products = wc_get_products( array() );
?>
	<div class="wrap woocommerce">
		<h1>Print Services</h1>
		<form method="post" id="mainform" action="" enctype="multipart/form-data">
			<table class="form-table">
				<tbody>
					<tr valign="top" class="single_select_page">
						<th scope="row" class="titledesc" style="width:250px;">
							<label>Upload current FFL Database(CSV)</label>
						</th>
						<td>
							<input name="ffl_dealer_file" type="file" accept=".csv" />
						</td>
					</tr>
					<tr valign="middle" class="single_select_page">
						<th scope="row" class="titledesc" style="width:250px;">
							<label>Apply FFL to product categories</label>
						</th>
						<td>
							<select multiple="" name="ffl_dealer_categories[]" id="ffl_dealer_categories" style="width:350px" data-placeholder="Choose categories" aria-label="Categories" class="wc-enhanced-select" tabindex="-1" aria-hidden="true">
						<?php foreach ($product_categories as $key => $cat) { ?>
								<option value="<?php echo $cat->term_id;?>" <?php echo in_array($cat->term_id, explode(',', get_option('ffl_dealer_categories')) )?'selected':'';?>><?php echo $cat->name;?></option>
						<?php }?>
							</select>
						</td>
					</tr>
					<tr valign="middle" class="single_select_page">
						<th scope="row" class="titledesc" style="width:250px;">
							<label>Apply FFL to specific products</label>
						</th>
						<td>
							<select multiple="" name="ffl_dealer_products[]" id="ffl_dealer_products" style="width:350px" data-placeholder="Choose categories" aria-label="products" class="wc-enhanced-select" tabindex="-1" aria-hidden="true">
						<?php foreach ($products as $key => $product) { ?>
								<option value="<?php echo $product->get_ID();?>" <?php echo in_array($product->get_ID(), explode(',', get_option('ffl_dealer_products')) )?'selected':'';?>><?php echo $product->get_title();?></option>
						<?php }?>
							</select>
						</td>
					</tr>
					<tr valign="middle" class="single_select_page">
						<th scope="row" class="titledesc" style="width:250px;">
							<label>Google API Key</label>
						</th>
						<td>
							<input type="text" name="google_api_key" value="<?php echo get_option('google_api_key');?>" class="form-control" />
						</td>
					</tr>
				</tbody>
			</table>
			<p><input type="submit" name="save" value="Save" class="button button-primary button-large"/>
		</form>
	</div>
<?php
	}

	public function plugin_add_dealer_button(){
		global $product;
		$id = $product->get_id();
		$ffl_dealer_categories = get_option('ffl_dealer_categories');
		$ffl_dealer_products = get_option('ffl_dealer_products');
		$terms = get_the_terms( $id, 'product_cat' );

		$is_needed_dealer = false;
		if ($ffl_dealer_categories == "" && $ffl_dealer_products == ""){
			$is_needed_dealer = true;
		}
		if ($ffl_dealer_categories != ""){
			$categories_array = explode(',' , $ffl_dealer_categories);
			foreach ($terms as $term) {
				if (in_array($term->term_id, $categories_array)){
					$is_needed_dealer = true;
					break;
				}
			}
		}
		if ($ffl_dealer_products != ""){
			$product_array = explode(',' , $ffl_dealer_products);
			if (in_array($id, $product_array)){
				$is_needed_dealer = true;
			}
		}
		if (!$is_needed_dealer){
			return;
		}
?>
	<div class="ffl_dealer_wrapper">
		<button class="button button-select-dealer" type="button" data-toggle="modal" data-target="#ffl_dealer_modal">Select FFL Dealer</button>
		<input type="hidden" name="dealer_id" class="dealer_id" value=""/>
		<div class="modal" id="ffl_dealer_modal" role="dialog" style="display: none;" aria-hidden="true">
			<div class="modal-dialog modal-lg in-store-inventory-dialog">
				<!-- Modal content-->
				<div class="modal-content">
					<div class="modal-header">
						<span class="modal-title text-uppercase">Select Your FFL Dealer</span>
						<button type="button" class="close pull-right" data-dismiss="modal" title="Close">&times;</button>
					</div>
					<div class="modal-body">
						<div class="store-outer-div">
							<div class="store-locator-container">
								<div class="store-locator-inner-container ">
									<div class="ffl-dealer-text">
										We are unable to sell firearms if your billing address and/or your selected FFL Dealer is located in California, Connecticut, Chicago, IL, District of Columbia, Hawaii, Maryland, Massachusetts, New Jersey, or New York.
									</div>
								    <div class="row">
								        <div class="col-lg-4 d-flex flex-column zip-code-div">
											<div class="form-group required">
												<label class="form-control-label input-focus" for="store-postal-code">Zip Code</label>
												<input autofocus="" type="tel" class="form-control" id="store-postal-code" name="postalCode" value="" autocomplete="nofill" required="">
											</div>
	        							</div>
								        <div class="col-lg-4 radius-div">
							                <div class="form-group">
							                    <label for="radius" class="form-control-label input-focus">Radius</label>
												<select class="form-control radius" id="radius" name="radius">
											        <option value="20">20mi</option>
											        <option value="30">30mi</option>
											        <option value="50">50mi</option>
											        <option value="100">100mi</option>
											        <option value="300">300mi</option>
												</select>
            								</div>
	    								</div>
	    								<div class="col-lg-4 d-flex flex-column zip-code-div">
											<button class="btn btn-block button-find-dealer" type="button">
									            Find FFL Dealer
									        </button>
	        							</div>
	    							</div>
	    							<div class="row">
	    								<div class="store-result-list">
    		                			</div>
	    							</div>
							        <div class="row store-page-button hide-in-page">
							            <div class="col-lg-6">
							            	<div class="select-dealer">
							                	<button class="btn btn-primary select-store button-primary storeID" data-action-url="/on/demandware.store/Sites-CTDDOTCOM-Site/default/Stores-SetPreferredDealer" disabled="">Select FFL Dealer</button>
							               	</div>
							            </div>
							        </div>
							    </div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
<?php
	}

	public function load_plugin_assets(){
		wp_enqueue_style( 'woocommerce-ffl-style', plugin_dir_url( __FILE__ ).'assets/frontend.css', null, null );
		wp_enqueue_script( 'woocommerce-ffl-script', plugin_dir_url( __FILE__ ) . 'assets/frontend.js', array( 'jquery'), null, true );
		wp_localize_script( 'woocommerce-ffl-script', 'ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );
	}

	public function search_dealers(){
		global $wpdb;
	    $tbl_ffl_dealer = $wpdb->prefix . 'ffl_dealer';
	    $tbl_zipcode = $wpdb->prefix . 'zipcode';

		$zipcode = $_REQUEST['zipcode'];
		$radius = $_REQUEST['radius'];

		$latlon_sql = "SELECT * FROM {$tbl_zipcode} WHERE zipcode = '{$zipcode}'";
		$latlon = $wpdb->get_results($latlon_sql, ARRAY_A);
		$lat = $latlon[0]['lat'];
		$lon = $latlon[0]['lon'];
		$dealers_sql = "SELECT * FROM {$tbl_ffl_dealer} WHERE zip IN 
			(
				SELECT distinct(zipcode) 
				FROM {$tbl_zipcode} 
				WHERE (3958*3.1415926*sqrt((lat-{$lat})*(lat-{$lat}) + cos(lat/57.29578)*cos({$lat}/57.29578)*(lon-{$lon})*(lon-{$lon}))/180) <= {$radius}
			)";

		$dealers = $wpdb->get_results($dealers_sql, ARRAY_A);
		echo json_encode($dealers);
		exit;
	}

	public function plugin_add_dealer_data($cart_item_data, $product_id, $variation_id)
	{
	    if (isset($_REQUEST['dealer_id']) && $_REQUEST['dealer_id'] != ""){
	    	$cart_item_data['dealer_id'] = $_REQUEST['dealer_id'];
	    }

	    return $cart_item_data;
	}

	public function plugin_generate_cart_id($cart_id, $product_id, $variation_id, $variation, $cart_item_data){
		global $woocommerce;
		$new_cart_id = $cart_id;
	    if (isset($cart_item_data['dealer_id']) && $cart_item_data['dealer_id'] != ""){
	    	$items = $woocommerce->cart->get_cart();
			foreach ($items as $key => $item) {
				if (!empty($item['dealer_id'])){
					$woocommerce->cart->cart_contents[$key]['dealer_id'] = $cart_item_data['dealer_id'];

					if ( ( $item['product_id'] == $product_id ) 
							&& ( $item['variation_id'] == $variation_id ) 
							&& empty( array_diff_assoc( $item['variation'], $variation ) ) )
					{
						$new_cart_id = $key;
					}
					
				} 
			}
	    }
	    $woocommerce->cart->set_session();

	    return $new_cart_id;
	}

	public function plugin_add_dealer_meta($item_data, $cart_item)
	{
		global $wpdb;
	    $tbl_ffl_dealer = $wpdb->prefix . 'ffl_dealer';

	    if(array_key_exists('dealer_id', $cart_item))
	    {
	        $dealer_id = $cart_item['dealer_id'];

	        $dealers_sql = "SELECT * FROM {$tbl_ffl_dealer} WHERE id='{$dealer_id}'";
	        $dealer = $wpdb->get_results($dealers_sql, ARRAY_A);
	        $current_dealer = $dealer[0];

	        $dealer_info = sprintf('<span class="dealer_name">%s</span><br/><span class="dealer_data">%s %s, %s %s<br/>%s', 
	        	$current_dealer['business_name'], 
	        	$current_dealer['street'], 
	        	$current_dealer['city'], 
	        	$current_dealer['state'], 
	        	$current_dealer['zip'], 
	        	$current_dealer['phone']);

	        $item_data[] = array(
	            'key'   => 'SELECTED FFL DEALER',
	            'value' => $dealer_info
	        );
	    }

	    return $item_data;
	}

	public function plugin_add_order_line_dealer_meta($item, $cart_item_key, $values, $order)
	{
		global $wpdb;
	    $tbl_ffl_dealer = $wpdb->prefix . 'ffl_dealer';

	    if(array_key_exists('dealer_id', $values))
	    {
	    	$dealer_id = $values['dealer_id'];

	        $dealers_sql = "SELECT * FROM {$tbl_ffl_dealer} WHERE id='{$dealer_id}'";
	        $dealer = $wpdb->get_results($dealers_sql, ARRAY_A);
	        $current_dealer = $dealer[0];

	        $dealer_info = sprintf('<span class="dealer_name">%s</span><br/><span class="dealer_data">%s %s, %s %s<br/>%s', 
	        	$current_dealer['business_name'], 
	        	$current_dealer['street'], 
	        	$current_dealer['city'], 
	        	$current_dealer['state'], 
	        	$current_dealer['zip'], 
	        	$current_dealer['phone']);

	        $item->add_meta_data( 'SELECTED FFL DEALER', $dealer_info );
	    }
	}
}

$ffl_object=new woo_ffl_dealer(); 
$ffl_object->instance();
register_activation_hook( __FILE__, array('woo_ffl_dealer', 'plugin_activate') );
register_deactivation_hook( __FILE__, array('woo_ffl_dealer', 'plugin_deactivate') );

endif;
endif;