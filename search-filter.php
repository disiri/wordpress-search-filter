<?php 

/*
 Plugin Name: Search Filter
 Plugin URI: http://dev.gmail.com
 Description: Search Plugin
 Version: 1.0
 Author: Desiree Anne Q. Banua
 Author URI: http://dev.google.com
 */


/******************************************
*  Global Variables 
********************************************/

// $search_page_id = 171;
$page_name = "this-is-search/";
$post_type_name = "class_ant";

//contains all the custom fields that you want to include to your search
$acf_fields = array( 'address' , 'price' );

/******************************************
*  Register, Enqueque and Localize Related  
********************************************/

//hook register, enqueque and localize script on init
add_action( 'init' , 'class_ant_script' );

function class_ant_script() { 

	wp_register_script( 'ajax-search-request' , get_template_directory_uri() . '/js/ajax-search.js' , array('jquery') );

	wp_enqueue_script( 'ajax-search-request' );

	//standard nonce = 44pixels + clientname + ajax + L3tM31n!

	$url_array = array(

				 	'ajaxurl' => admin_url( 'admin-ajax.php' )

				 );

	wp_localize_script( 'ajax-search-request' , 'SearchAjax' , $url_array );

}



/******************************************
*  Search Functions Related
********************************************/

//hooks for myajax_submit function

add_action( 'wp_ajax_nopriv_class-ant-ajax-submit' , 'class_ant_ajax' );

add_action( 'wp_ajax_class-ant-ajax-submit' , 'class_ant_ajax' );

function class_ant_ajax() {

	global $wpdb, $wp_query;

	$nonce = $_POST['postCommentNonce'];

	//array of terms in this format: taxonomy + hyphen + term id, seperated by a comma
	$id_list = $_POST['id_list'];

	// if ( ! wp_verify_nonce( $nonce, '44pixels-ajax-L3tM31n!' ) )

	// 	die ( 'Busted!');

	//variable used in search by keyword
	$keyword_string = $_POST['keyword_string'];

	//seperate the terms and store them in their own index
	$term_list = explode( "," , $id_list );



	//if both keyword and term id list are empty print a message
	if( empty( $keyword_string ) && empty( $term_list ) ) {

		echo "You currently have 0 results try adding a filter to the left";

		die();

	}
	
	//remove empty array
	array_filter( $term_list );
	
	//build [key] => [value] = [taxonomy] => [term_id]
	$tax_present = array();

	/* eliminate the hypen and seperate the taxonomy and term id,
	   then execute a function that will find the slug base on the term id and store it
	   into a new array with corresponding taxonomy and term as an index. */
	foreach( $term_list as $term_tax ) {
		
		$final_tax_term = explode( "-" , $term_tax );
		
		$tax = $final_tax_term[0];
		$term_id = $final_tax_term[1];
	
		if( !empty( $term_id ) ) :
			
			$term_name_obj = get_term( (integer)$term_id , $tax );

			if( !is_wp_error( $term_name_obj ) ) :
			
				//$tax_present[ $tax ] = $term_name_obj->slug;
				$tax_present[] = array (

					"tax" => $tax,
					"term" => $term_name_obj->slug

				);
				
			endif;
			
		endif;

	}
	
	class_ant_search( $tax_present , $keyword_string , true );

	die();

}


/**
* Main search function
*
* @param array $tax_present the array that contains tax and term
* @param string $string the string that contains the keyword the user enters for searching
* @param boolean $ajax 
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function class_ant_search( $tax_present , $string , $ajax=false ) {
	
	global $post_type_name;

	//check if taxonomy and string are empty then perform the necessary query
	if( empty( $tax_present ) && empty( $string ) ) {

		$paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;

		if( $ajax ) 
			$posts_per_page = 999999999999;
		else
			$posts_per_page = 999999999999;
			
		$args = array(

			"post_type"      => $post_type_name,
			"posts_per_page" => $posts_per_page,
			"paged"          => $paged

		);
		
		run_query( $args );
		
		return;

	}

	//create taxonomy query based on the value of $ajax variable
	if( $ajax && !empty( $tax_present ) ) {

		$tax_query = create_tax_query_ajax( $tax_present );

	} else if( !empty( $tax_present ) ) {

		$tax_query = create_tax_query( $tax_present );
	
	} else {

		$tax_query = array();
		
	}

	//query the value of the taxonomy and the string
	$post_meta_array = meta_query( $tax_query , $string );

	//query using tax query only
	$post_tax_query_only = array();
	
	//if $tax_present is not empty perform a WP_Query
	if( !empty( $tax_present ) )
		$post_tax_query_only = post_tax_query( $tax_query );

    if( empty( $string ) ) {

    	$final_id_list = $post_tax_query_only;

    } else if( !empty( $post_tax_query_only ) ) {

    	$temp_array = array();

    	foreach( $post_tax_query_only as $item ) {

    		if( in_array( $item , $post_meta_array ) )
    			$temp_array[] = $item;

    	}
    	
    	$final_id_list = array_merge( $post_meta_array , $temp_array );
    	
    } else {

    	$final_id_list = $post_meta_array;

    }
	
	if( empty( $final_id_list ) ) {

		echo "You currently have 0 results try adding a filter to the left";

	} else {
	
		if( $ajax ) 
			$posts_per_page = 999999999999;

		else
			$posts_per_page = 999999999999;

		$paged = ( get_query_var('paged') ) ? get_query_var('paged') : 1;

		$args = array(

			"post_type"      => $post_type_name,
			"post__in"       => $final_id_list,
			"order_by"       => "post__in",
			"paged"          => $paged,
			"posts_per_page" => $posts_per_page

		);
		
		run_query( $args );

	}

}


/**
* Execute query, display the posts and reset the query afterwards
*
* @param array $args the array that contains the index for querying in wordpress
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function run_query( $args ) {
	
	global $wp_query;

	$wp_query = new WP_Query( $args );

	run_standard_loop();
	
	//reset the wordpress query
	wp_reset_query();

}

/**
* Display the posts based on the WP_Query arguments
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function run_standard_loop() {

	//echo "<div class='ad-wrap'>";

		echo "<div class='entry-wrap'>";
		$count_post = 0;
		if ( have_posts() ) : while ( have_posts() ) : the_post();
			
			
					global $post;
					
					$args = array(
			                'post_type' => 'attachment',
			                'post_parent' => $post->ID,
			                'numberposts' => -1,
			                'post_status' => NULL
			        );

					$attachs = get_posts($args);
					
					$src = array();
					
					if (!empty($attachs)) :
						
						$counter = 0;
						//loop through the attachs array
						foreach ($attachs as $att) :
							
						 // get attachment array with the ID from the returned posts
						 $img_data = wp_get_attachment_image_src($att->ID);
	 
						 //store the first value in the $src variable
						 $src[$counter] = $img_data;
						 
						 $ant_img_src = $src[0][0]; 
							
						$counter++;          	    
			            endforeach;
			            
			        endif;
			  $flag = get_field('flag');
			  if( $flag[0] != 1 ) : 
			  	$count_post++;
		?>
		
			<div <?php post_class(); ?> style="width: 500px; margin-bottom: 50px;">
				<img src='<?php echo$ant_img_src;?>' style='width: 150px; height:150px;'/>
				<br>
				<h3 class="entry-title"><a rel="bookmark" title="<?php the_title(); ?>" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3><br>
				<div class="post-info"></div>		
				<div class="entry-content">

				</div>
				<!-- end .entry-content -->	

				<br>

				<strong>Categories:</strong><br>

				<?php the_taxonomies(); ?>

			</div><!-- end .postclass -->
			
		<?php
			endif;
		endwhile;
		if( $count_post == 0 ) {
			echo "You currently have 0 results try adding a filter to the left";
		}
			
		
		echo "</div>";

	?>

			<div class="page_navigation" style="display: none;">
				
			</div>

			<style type="text/css">
			
				.page_navigation a, .alt_page_navigation a{
					padding:3px 5px;
					margin:2px;
					color:white;
					text-decoration:none;
					float: left;
					font-family: 'museo_sans_rounded_500regular';
					font-size: 12px;
					background-color:#ccc;
				}
				.active_page{
					background-color: #019e89 !important;
					color:#fff !important;
				}
				.page_navigation {
					float: right;
				}
				.ellipse {
					float: left;
				}
				.image-thumbnail {
					border: 0.5px solid;
					width: 150px;
					height: 150px;
				}

			</style>

			<script type="text/javascript" src="<?php bloginfo('stylesheet_directory') ?>/js/jquery.pajinate.min.js"></script>
			
			<script type="text/javascript">
				jQuery(document).ready(function(){
					jQuery('.content-item').pajinate({
						item_container_id: '.entry-wrap',
						num_page_links_to_display: 10,
						items_per_page: jQuery("#result").val()
					});
					
					jQuery("#result").change(function(){
						
						jQuery('.content-item	').pajinate({
							item_container_id: '.entry-wrap',
							num_page_links_to_display: 10,
							items_per_page: jQuery(this).val()
						});
						
					})
				})
			</script>

		<?php

	else : /** if no posts exist **/
		echo "You currently have 0 results try adding a filter to the left";
	endif; /** end loop **/
	
}
/**
* Organize the array to fit into the indexes of WP_Query
*
* @param array $args the array that contains the indexes for querying in wordpress
* @return array $tax_query
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function create_tax_query_ajax( $tax_present ) {
	
	$tax_query = array();

	if( !empty( $tax_present ) ) {

		$tax_query = array(

			"relation" => "And"

		);

		
		foreach( $tax_present as $single_tax ) {
		
			$tax_query[] = array(  

				'taxonomy' => $single_tax["tax"],
				'field'    => 'slug',
				'terms'    => $single_tax["term"]

			);

		}

	}

	return $tax_query;
	
}


/**
* Organize the array to fit into the indexes of WP_Query
*
* @param array $args the array that contains the indexes for querying in wordpress
* @return array $tax_query
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function create_tax_query( $tax_present ) {

	$tax_query = array();

	if( !empty( $tax_present ) ) {

		$tax_query = array(

			"relation" => "And"

		);

		
		foreach( $tax_present as $key => $val ) {

			$tax_query[] = array(  

				'taxonomy' => $key,
				'field'    => 'slug',
				'terms'    => $val

			);

		}
	}

	return $tax_query;

}


/**
* Query the posts with keywords and terms as an argument
*
* @param array $tax_query the array that contains the indexes for querying in wordpress
* @param string $string the string that contains the keyword the user enters for searching
* @return array $post_meta_array the array that contains all the id of the posts
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function meta_query( $tax_query , $string ) {

	global $post_type_name;

	//find the string inside the title and content of a post
	$title_result = query_title_content( $string );

	//find the string inside the custom field value
	$field_result = query_key_value( $string );

	//merge the id of the two results
	$search_result = array_merge( $title_result , $field_result );

	
	$post_meta_array = array();
	
	if( !empty( $string ) ) :

		//if $tax_query is empty, don't include the array to the query
		if( empty( $tax_query ) ) {
			if( !empty($search_result) ) {
				$args = array(

				'post_type' => $post_type_name,
				'post__in' => $search_result

				);
			} else {
				$args= array();
			}
			
	
		} else {

			$args = array(

				'post_type'  => $post_type_name,
				'tax_query'  => $tax_query,
				'post__in' => $search_result

			);
	
		}

		$meta_query = new WP_Query( $args );


		//check if the query return a post
		if( $meta_query->found_posts > 0 ) {
		
			//get the id of every posts in the loop and store it in an array
			foreach( $meta_query->posts as $post ) {

				$post_meta_array[] = (string)$post->ID;



			}
		
		}
	
	endif;
	
	return $post_meta_array;

}


/**
* Searching of the string in the post's title and content
*
* @param string $string the string that contains the keyword the user enters for searching
* @return array $post_query the array that contains all the id of the posts that is searched by a keyword
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function query_title_content( $string ) {
	
	global $wpdb, $post_type_name;
	
	$post_query = array();
	
	if( !empty( $string ) ) :
	
		//retrieve the prefix used for the database
		$table_name = $wpdb->prefix . "posts";
		
		$initial_where = '( ';
		
		/* if the string is more than 1 word, loop through every word of that string 
		   and create a query based on the word */
		foreach( explode( " " , $string ) as $keyword ) {
			$initial_where .= "`post_title` LIKE '%$keyword%' OR `post_content` LIKE '%$keyword%' OR ";
		}
		
		//add another query for the whole string
		$initial_where .= "`post_title` LIKE '%$string%' OR `post_content` LIKE '%$string%' )";
		
		//combines all the query
		$sql = "SELECT ID FROM $table_name WHERE $initial_where AND ( `post_status` LIKE 'publish' AND `post_type` LIKE '$post_type_name' )";
	    
	    $result = $wpdb->get_results( $sql , ARRAY_A );
	    
	    //get the id of every posts in the loop and store it in an array
	    foreach( $result as $post ) {

	    	$post_query[] = $post["ID"];

	    }
	
	endif;
	
	return $post_query;

}


/**
* Searching of the string in the custom field values
*
* @param string $string the string that contains the keyword the user enters for searching
* @return array $post_id the array that contains all the id of the posts that is searched by meta_value and meta_key
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function query_key_value( $string ) {

	global $wpdb, $acf_fields;
	
	$post_id = array();
	
	if( !empty( $string ) ) :
	
		//retrieve the prefix used for the database
		$table_name = $wpdb->prefix . "postmeta";
		
		$keyword_string = explode( " " , $string );

		$initial_where = "";
		
		/* if the string is more than 1 word, loop through every word of that string 
		   and create a query based on the word */
		foreach( $keyword_string as $keyword ) {

			//also loop on the field that is declared on the $acf_fields array
			foreach( $acf_fields as $fields ) {

				$initial_where .= "( meta_key = '$fields' AND meta_value LIKE '%$keyword%' ) OR ";

			}

		}

		$count = 0;

		//add another query for the whole string
		foreach ( $acf_fields as $fields ) {
			
			$initial_where .= "( meta_key = '$fields' AND meta_value LIKE '%$string%' )";

			if( $count != ( count( $acf_fields ) - 1 ) )
				$initial_where .= " OR ";

			$count++;

		}

	    //combines all the query
	    $sql = "SELECT post_id FROM $table_name WHERE $initial_where";

	    $result = $wpdb->get_results( $sql , ARRAY_A );

	    //get the id of every posts in the loop and store it in an array
	    foreach ( $result as $value ) {

	    	$post_id[] = $value['post_id'];

	    }
	
	endif;
	
	return $post_id;

}


/**
* Query the array parameter
*
* @param string $tax_query the array that contains the indexes for querying in wordpress
* @return array $post_tax_query_only the array that contains all the id of the post that is returned by the query
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function post_tax_query( $tax_query ) {

	global $post_type_name;

	$args = array(

		"posts_per_page" => 999999999999,
		"post_type"      => $post_type_name,
		"tax_query"      => $tax_query

	);

	wp_reset_query();
	
	$post_tax_query = new WP_Query( $args );

	//check if the query return a post
	if( $post_tax_query->found_posts > 0 ) {

		//get the id of every posts in the loop and store it in an array
		foreach( $post_tax_query->posts as $post ) {

			$post_tax_query_only[] = (string)$post->ID;
			
		}

	}

	return $post_tax_query_only;

}


/**
* Display all the taxonomy terms
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function display_tax_term() {

	global $post_type_name;

	$tax_present = from_get_to_term_id();

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'state' , 'hide_empty=0' );

	echo "<div class='AdTaxonomy'>";

	//
	if( !empty( $term_list ) ) :

		echo "<label for='state_all' class='tax_label label_state'>State: </label>";
		echo "<select id='state_all' class='stateandschool'>";
			echo '<option value="">All States</option>';

			//display all the terms as an option on the select element
			foreach( $term_list as $term ) {
				
				?>

					<option value="<?php echo $term->term_id; ?>" <?php if( in_array( $term->term_id , $tax_present ) ) echo "selected='selected'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>

				<?php

			}

		echo "</select>";

	endif;

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'school' , 'hide_empty=0' );

	if( !empty( $term_list ) ) :

		echo "<label for='school_all' class='tax_label label_school'>School: </label>";
		echo "<select id='school_all' class='stateandschool'>";
			echo '<option value="">All Schools</option>';

			//display all the terms as an option on the select element
			foreach( $term_list as $term ) {
				
			?>

				<option value="<?php echo $term->term_id; ?>" <?php if( in_array( $term->term_id, $tax_present ) ) echo "selected='selected'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>

			<?php

			}

		echo "</select>";

	endif;

	$tax_list = parse_get_variable("taxonomy");

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'state' , "hide_empty=0" );

	//array of terms that you don't want to appear on the checkbox
	$exclude = array( "state" , "school" );

	//get all the taxonomy from custom post type ad
	foreach( get_object_taxonomies( $post_type_name , "object" ) as $tax ) {

		//check if the taxonomy is in the $exclude array
		if( !in_array( $tax->name , $exclude )  ) :
		
			$term_list = get_terms( $tax->name , "hide_empty=0" );

			if( !empty( $term_list ) ) :

				echo "<br>";

				echo "<strong class='$tax->name'>" . $tax->labels->name . ": </strong>";

				echo "<ul id='$tax->name'>";

					//get all the terms and display it beside the checkbox
					foreach( $term_list as $term ) {

						?>

							<li><input type='checkbox' name='<?php echo $tax->name; ?>[]' value='<?php echo $term->term_id; ?>' class="filter-result" <?php if( in_array( $term->term_id , $tax_present ) && in_array( $term->taxonomy , $tax_list ) ) echo "checked='checked'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></li>	

						<?php

					}

				echo "</ul>";

			endif;

		endif;

	}

	echo "</div>";

}


/**
* Display all the taxonomy terms
*
* @return array 
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function from_get_to_term_id() {

	$tax_present = parse_get_variable( "term_id" );

	return $tax_present;

}


/**
* description
*
* @param string $return_need 
* @return array $tax_present
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function parse_get_variable( $return_need ) {

	$tax_present = array();

	/* loop through $_GET and create an array based on the 
	   string that is on the parameter */
	foreach ( $_GET as $key => $value ) {

		// $key = $value;

		if( $key != "string" ):

			if( !empty( $value ) ) {

				$term = get_term_by( 'id', $value , $key );

				if( !is_wp_error( $term ) )

					$tax_present[ $key ] = $term->$return_need;

			}
			
		endif;

	}

	return $tax_present;

}

/**
* 
*
* @return array $tax_present
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function from_get_to_term_name() {

	$tax_present = parse_get_variable( "name" );

	return $tax_present;

}


/**
* Get all the terms in the url
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function get_url_tax_and_term() {

	$final_array = array();	

	$need = "slug";

	foreach ( $_GET as $key => $value ) {
						

		if( $key != "string" && !empty( $value ) ) :

			$term = get_term_by( "id" , $value , $key );

			if( !is_wp_error( $term ) )
				$term_slug = $term->$need;

			if( !empty( $term_slug ) ) :

				$type_array = array(

					"tax" => $key ,
					"term" => $term_slug

				);

				$final_array[] = $type_array;

			endif;

		endif;

	}

	$string = $_GET['string'];

	class_ant_search( $final_array , $string , true );

}

/******************************************
*  Displaying On The Page Related
********************************************/


/**
* Display the search form with state and school selection
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function display_search_form() {

	if( isset( $_COOKIE['search-preference'] ) ) {

		$unserialize_cookie = unserialize( base64_decode( $_COOKIE['search-preference'] ) );

	}

	// global $search_page_id;

	// //return a page data based on the page id
	// $search_page_url = get_page( $search_page_id );

	$tax_present = from_get_to_term_id();

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'state' , 'hide_empty=0' );

	// echo "<form method='get' action='" . $search_page_url->post_name . "'>";

	echo "<form method='get' action=''>";

	if( !empty( $term_list ) ) :
		echo "<label for='state' class='tax_label label_state'>State: </label>";
		echo "<select name='state' id='state'>";
			echo '<option value="">All States</option>';

			//display all the terms as an option on the select element
			foreach( $term_list as $term ) {
				
			?>

				<option <?php if( $unserialize_cookie['state'] == $term->term_id ) echo "selected='selected'"; ?> value="<?php echo $term->term_id; ?>" <?php if( in_array( $term->term_id, $tax_present ) ) echo "selected='selected'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>

			<?php

			}

		echo "</select>";
	
	endif;

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'school', "hide_empty=0" );

	if( !empty( $term_list ) ) :

		echo "<label for='school' class='tax_label label_state'>School: </label>";
		echo "<select name='school' id='school'>";
			echo '<option value="">All Schools</option>';

			//display all the terms as an option on the select element
			foreach( $term_list as $term ) {
				
			?>

				<option <?php if( $unserialize_cookie['school'] == $term->term_id ) echo "selected='selected'"; ?> value="<?php echo $term->term_id; ?>" <?php if( in_array( $term->term_id , $tax_present ) ) echo "selected='selected'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>

			<?php 

			}

		echo "</select>";

	endif;

	echo "<br>";

	//echo "<input type='hidden' name='search-form' value='no-val'>";

	echo "<input class='search-button' type='submit' value='Search'>";

	echo "</form>";

	echo "<br><br><br>";

	//display all the terms from the category taxonomy
	display_categories();

}


// add_action( 'wp_head' , 'display_keyword_search' );

/**
* Display the search form with the keyword input and state and school selection
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function display_keyword_search() {

	// //this is the id of your search page
	global $page_name;

	// $search_page_url = get_page( $search_page_id );

	$tax_present = from_get_to_term_id();

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'state' , "hide_empty=0" );

	echo "<div class='searchKeyword'>";
?>
	
	<form method='get' action='<?php echo site_url()."/".$page_name; ?>'>
<?php
	echo "<label for='string' class='label-string'>Search: </label>";

	// echo "<br>";

	echo "<input class='inline-this' type='text' name='string' placeholder='Search key here'>";

	// echo "<br>";

	if( !empty( $term_list ) ) {

		echo "<label for='state' class='tax_label label_state'>States: </label>";
		echo "<select name='state' id='state' class='inline-this'>";
			echo '<option value="">All States</option>';

			//display all the terms as an option on the select element
			foreach( $term_list as $term ) {
				
			?>

				<option value="<?php echo $term->term_id; ?>" <?php if( in_array( $term->term_id , $tax_present ) ) echo "selected='selected'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>

			<?php

			}

		echo "</select>";
	}	

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'school' , "hide_empty=0" );

	if( !empty( $term_list ) ) {
		echo "<label for='school' class='tax_label label_state'>Schools: </label>";
		echo "<select name='school' id='school' class='inline-this'>";
			echo "<option value=''>All Schools</option>";

			//display all the terms as an option on the select element
			foreach( $term_list as $term ) {
				
			?>

				<option value="<?php echo $term->term_id; ?>" <?php if( in_array( $term->term_id , $tax_present ) ) echo "selected='selected'"; ?> key="<?php echo $term->name; ?>"><?php echo $term->name; ?></option>

			<?php 

			}

		echo "</select>";
	}

	// echo "<br>";

	//echo "<input type='hidden' name='search-form' value='no-val'>";

	echo "<input id='inline-this' type='submit' value='Search' style='display:inline;'>";

	echo "</form>";

	echo "</div>";

	?>

	<?php

}


/**
* Display all the categories
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function display_categories() {

	/* get all the terms from the first parameter taxonomy, the second parameter hide/show
	   all the terms that no post uses */
	$term_list = get_terms( 'type' , "hide_empty=0" );

	echo "<strong>Categories:</strong><br><br>";

	if( !empty( $term_list ) ) :

			//display all the terms from the category together with their icon
			foreach( $term_list as $term ) {
				
				?>

				<?php //$image =  get_field('thumbnail' , $term); ?>

				<a href="<?php echo $search_page_url->post_name; ?>/?type=<?php echo $term->term_id; ?>"><?php if( !empty( $image ) ) { ?><img src="<?php echo $image['sizes']['thumbnail']; ?>" height="50" width="50"><?php } echo $term->name; ?><br></a>

				<?php

				echo "<br>";

			}
	endif;

}


/**
* Display the keys and filters you use for searching
*
* @since 1.0
* @version 1.0
*
* @author
*
*/
function display_search_keys() {

	$string = $_GET['string'];
	$state = $_GET['state'];
	$school = $_GET['school'];


	echo "<div class='search-keys'>";

		echo "<strong>You searched for: </strong>";
		
		$tax_arr = from_get_to_term_name();

		if( !empty( $string ) )
			echo "<em>$string, </em>";

		echo "<em>" . implode( ", " , $tax_arr ) . "</em>";
		
	echo "</div>";

}
