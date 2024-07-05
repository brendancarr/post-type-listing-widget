<?php
/*
Plugin Name: Blog Display Widget Plugin
Plugin URI: 
Description: Creates widget, stylesheets, and AJAX scripts for Article integration.
Version: 1.2
Author: Infinus Technology
Author URI: https://infinus.ca
License: GPL2
*/

// Register Scripts and Styles
function register_blog_scripts(){
    wp_enqueue_style( 'post-type-listing-style', plugins_url( 'post-type-listing-style.css' , __FILE__ ) );
	wp_enqueue_script( 'ajaxModal' );
	wp_localize_script( 
		'ajaxModal', 
		'ajax_object', 
		array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) 
	);
}
add_action('wp_enqueue_scripts','register_blog_scripts');

add_action( 'after_setup_theme', 'create_image_sizes', 11 );
function create_image_sizes() {
	add_image_size( 'article-sm', 600, 400, true );
	add_image_size( 'article-md', 900, 600, true );
	add_image_size( 'article-lg', 1100, 540, true );
}


// Register and load Listing Card Widget
function blog_display_load_widget() {
    register_widget( 'blog_display_widget' );
}
add_action( 'widgets_init', 'blog_display_load_widget' );

//Functions to trim excerpts to set length of words
function custom_read_more($permalink) {
    return ' <a class="read-more" href="'.$permalink.'">Read More</a>';
}
function trim_excerpt($excerpt, $limit) {
    return wp_trim_words($excerpt, $limit);
}

// WP Query Function and Listing Card creation
function widget_card_constructor_blog($post_type = "post", $displaymode = 'grid', $numresults = 6, $sortby = "date", $category = "all", $exclude = array(), $paged = NULL, $is_ajax = true) {
	
	//Verify defaults with Ajax Post values
	if ($is_ajax) {
		$post_type = $_POST['posttype'];
		$displaymode = $_POST['displaymode'];
		$numresults = $_POST['numresults'];
		$sortby = $_POST['sortby'];
		$category = $_POST['category'];
		$paged = $_POST['paged'];
	}
	
	if ( $sortby == "upcoming" && $post_type == "ajde_events" ) {
	    $queryargs = array(
	        'post_type' => $post_type,
	        'posts_per_page' => $numresults,
	        'paged' => $paged,
	        'orderby' => 'meta_value_num',
	        'order' => 'ASC',
	        'meta_key' => 'evcal_srow',
	        'post_status' => 'publish',
	    );
    }
    elseif ( $sortby == "featured" ) {
	    $queryargs = array(
	        'post_type' => $post_type,
	        'posts_per_page' => $numresults,
	        'paged' => $paged,
	        'orderby' => 'date',
	        'order' => 'DESC',
	        'post_status' => 'publish',
	    );
    }
    else {
	    //Main Query Args
	    $queryargs = array(
	        'post_type' => $post_type,
	        'posts_per_page' => $numresults,
	        'paged' => $paged,
	        'orderby' => $sortby,
	        'post_status' => 'publish',
	    );
    }
    
    //Taxonomy Query Args
    $tax_args = array('relation' => 'AND');
    if ($category != "all") {
        $cat_tax_query = array(
			'taxonomy' => 'category',
			'field' => 'slug',
			'terms' => $category,
		);
		$tax_args[] = $cat_tax_query;
    }
    if (!empty($exclude)) { //exclude must be array of terms
        $cat_tax_exclude = array(
			'taxonomy' => 'category',
			'field' => 'slug',
			'terms' => $exclude,
			'operator' => 'NOT IN',
		);
		$tax_args[] = $cat_tax_exclude;
    }
    
    //Meta Query Args
    $meta_args = array('relation' => 'AND');
    if ( $sortby == "upcoming" && $post_type == "ajde_events" ) {
        $event_meta_query = array(
			'key' => 'evcal_erow',
			'value' => time(),
			'compare' => '>',
			'type' => 'numeric',
		);
		$meta_args[] = $event_meta_query;
    }
    if ( $sortby == "upcoming" && $post_type == "ajde_events" ) {
    		$featured_meta_query = array(
			'key' => '_featured',
			'value' => 'yes',
			'compare' => '=',
		);
		$meta_args[] = $featured_meta_query;
    }
    if ( $sortby == "featured" ) {
	    $featured_post_meta_query = array(
			'key' => 'featured_post',
			'value' => 'yes',
			'compare' => 'LIKE',
		);
		$meta_args[] = $featured_post_meta_query;
    }
    
    //Construct finalized Query Args
    $queryargs['tax_query'] = $tax_args;
    $queryargs['meta_query'] = $meta_args;
    
    $query = new WP_Query( $queryargs );
	if ( $query->have_posts() ):
	
		//Count number of posts looped
		$post_count = 0;
		
		// Start looping over the query results.
		while ( $query->have_posts() ):
		
			$query->the_post();
    		
			global $post;
			
			$post_count++;
			
			if ($post_type == 'post') {
				//Get post category term
				$post_term_name = '';
				//Get first category assigned to post
				$post_terms = wp_get_post_terms( $post->ID, 'category');
				foreach ($post_terms as $post_term) {
				    if ($post_term->parent == 0) {
					    $post_term_name = $post_term->name;
					    $post_term_id = $post_term->term_id;
				        break;
				    }
				}
			}
			else if ($post_type == 'ajde_events') { 
				//Get post category term
				$post_term_name = '';
				//Get first category assigned to post
				$post_terms = wp_get_post_terms( $post->ID, 'event_location');
				foreach ($post_terms as $post_term) {
				    if ($post_term->parent == 0) {
					    $post_term_name = $post_term->name;
					    $post_term_id = $post_term->term_id;
				        break;
				    }
				}
			}
			
			
			$feature_image = get_post_thumbnail_id( $post->ID );
			$large_size = 'article-lg';
			$med_size = 'article-md';
			$size = 'article-sm';
			
			
			switch ($displaymode) {
				case 'event-feature': //Upcoming Events Section
				?>
					<div class="article-container feature-event" data-postid="<?php the_ID(); ?>" id="listing-<?php the_ID(); ?>" >
						
						<?php if ($post_count % 2 != 0): ?>
						<div class="event-image" style="background-image:url(<?php if ($feature_image){ echo get_the_post_thumbnail_url($post->ID, $size);} ?>)">
							<div class="event-excerpt">
								<div class="bg-up-arrow medium event left-arrow"></div>
								<?php echo trim_excerpt(get_the_content(), 27) . '<a href="/events/"> Learn More-></a>'; ?>
							</div>
						</div>
						<?php endif; ?>
						
						<div class="event-read-more">
							<div class="event-times">
								<div class="start-time">
									<span><?php echo date("d", get_field('evcal_srow')); ?></span><br>
									<?php echo date("M", get_field('evcal_srow')); ?>
								</div>
								<div class="end-time">
									<span><?php echo date("d", get_field('evcal_erow')); ?></span><br>
									<?php echo date("M", get_field('evcal_erow')); ?>
								</div>
							</div>
							<h4 class="post-title medium"><?php echo get_the_title(); ?></h4>
							<div class="open-time"><span></span>
								<?php echo date("g:i a", get_field('evcal_srow')); ?>
							</div>
							<div class="event-loc"><span></span>
								<?php echo $post_term_name; ?>
							</div>
						</div>
						
						<?php if ($post_count % 2 == 0): ?>
						<div class="event-image" style="background-image:url(<?php if ($feature_image){ echo get_the_post_thumbnail_url($post->ID, $size);} ?>)">
							<div class="event-excerpt">
								<div class="bg-up-arrow medium event left-arrow"></div>
								<?php echo trim_excerpt(get_the_content(), 27) . '<a href="/events/"> Learn More-></a>'; ?>
							</div>
						</div>
						<?php endif; ?>
						
					</div>
					
				<?php
				break;
				case 'grid': //Explore Pages
				?>
					<div class="article-container grid explore-blog-column" data-postid="<?php the_ID(); ?>" id="listing-<?php the_ID(); ?>" style="background-image:url(<?php if ($feature_image){ echo get_the_post_thumbnail_url($post->ID, $med_size);} else { echo '/wp-content/uploads/2019/06/placeholder-img.png';} ?>);" >
						
						<div class="explore-blog-read-more">
							<div class="post-category"><?php echo '<a href="' . get_term_link($post_term_id) . '">' . $post_term_name . '</a>'; ?></div>
							<h4 class="post-title medium"><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title(); ?></a></h4>
							<div class="read-more-container">
								<?php echo custom_read_more(get_permalink()); ?>
							</div>
						</div>
						
					</div>
					
				<?php
				break;
				case 'single-content': //Home Feature Post
				?>
					<div class="article-container single-content" data-postid="<?php the_ID(); ?>" id="listing-<?php the_ID(); ?>" >
						<style>
							#homegrown-image-bg {background-image: url(<?php echo get_the_post_thumbnail_url($post->ID, 'full'); ?>)!important;}
						</style>
						
						
						<h4 class="post-title medium"><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title(); ?></a></h4>
						<div class="post-excerpt">
							<?php echo trim_excerpt(get_the_excerpt(), 60); ?>
						</div>
						<div class="white-sep"></div>
						<a href="<?php echo get_permalink(); ?>"><p class="learn-more"><span></span> READ FULL POST</p></a>
							
						
					</div>
					
				<?php
				break;
				case 'blog': //Full Blog
				?>
				
					<div class="article-container grid full-blog" data-postid="<?php the_ID(); ?>" id="listing-<?php the_ID(); ?>" >
						
						<div class="feature-img small" style="min-height:100px;">
							<?php
							if ($feature_image)
								echo wp_get_attachment_image( $feature_image, $size );
							else
								echo '';
							?>
						</div>
						
						<div class="full-blog-read-more">
							<div class="bg-up-arrow large green left-arrow"></div>
							<div class="post-category"><?php echo '<a href="' . get_term_link($post_term_id) . '">' . $post_term_name . '</a>'; ?></div>
							<h4 class="post-title medium"><a href="<?php echo get_permalink(); ?>"><?php echo get_the_title(); ?></a></h4>
							<p style="color:#fff;text-align: justify; margin-top:15px; margin-bottom: 0px;">
								<?php echo trim_excerpt(get_the_excerpt(), 32); ?>
							</p>
							<div class="read-more-container">
								<?php echo custom_read_more(get_permalink()); ?>
							</div>
						</div>
						
					</div>
					
				<?php
				break;
				?>
				
			<?php
			}
		
		endwhile;
	endif;
	
	
	if ($displaymode === "blog"):
	?>
		
		<div class="pagination">
	    <?php 
	        echo paginate_links( array(
	            'base'         => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
	            'total'        => $query->max_num_pages,
	            'current'      => max( 1, get_query_var( 'paged' ) ),
	            'format'       => '?paged=%#%',
	            'show_all'     => false,
	            'type'         => 'plain',
	            'end_size'     => 1,
	            'mid_size'     => 3,
	            'prev_next'    => true,
	            'prev_text'    => sprintf( '<i></i> %1$s', __( 'Prev', 'text-domain' ) ),
	            'next_text'    => sprintf( '%1$s <i></i>', __( 'Next', 'text-domain' ) ),
	            'add_args'     => false,
	            'add_fragment' => '',
	        ) );
		?>
		</div>
	
	<?php
	endif;
	

    // Reset post data.
    wp_reset_postdata();
    
    if ($is_ajax) {
	    die();
    }
    else {
	    return $query->max_num_pages;
    }
    
}
add_action( "wp_ajax_nopriv_loadlistings", "widget_card_constructor_blog" );
add_action( "wp_ajax_loadlistings", "widget_card_constructor_blog" );


// Create the widget 
class blog_display_widget extends WP_Widget {
    function __construct() {
        parent::__construct(
            // Base ID of your widget
            'blog_display_widget', 

            // Widget name will appear in UI
            __('Blog / Article Display Widget', 'blog_display_widget_domain'), 

            // Widget description
            array( 'description' => __( 'Loads post types or articles based on given parameters.', 'blog_display_widget_domain' ), ), 
            
            //Control Options
            array( 'width'  => 600 )
        );
    }
	
    // Creating widget front-end
    public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', $instance['title'] );
		$numresults = $instance['numresults'];
		$sortby = $instance['sortby'];
		$displaymode = $instance['displaymode'];
		$category = $instance['category'];
		$post_type = $instance['post_type'];
		
		$paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;

		
		echo $args['before_widget'];
		
		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);
		
		?>
		<div class="articles-wrapper <?php echo $displaymode; ?>">
		<?php
		
		if ( ! empty( $title ) )
			echo $args['before_title'] . $title . $args['after_title'];
			
		$exclude = array(); //exclude these terms (slugs) from the wp query
			
		//Listing Cards
		if ($category == "xeach") {
			$terms = get_terms( array(
				'taxonomy' => 'category',
				'parent' => 0,
				'hide_empty' => true,
				'exclude' => array(),
			) );
			foreach ($terms as $term){
				widget_card_constructor_blog($post_type, $displaymode, $numresults, $sortby, $term->slug, $exclude, $paged, false);
			}
		}
		else {
			widget_card_constructor_blog($post_type, $displaymode, $numresults, $sortby, $category, $exclude, $paged, false);
		}
		
		?>
		</div>
		
		<?php
		
		echo $args['after_widget'];
	}

    // Widget Backend 
    public function form( $instance ) {
        //Set defaults
        if (isset($instance[ 'title' ]))
        	$title = $instance[ 'title' ];
        else
        	$title = __( '', 'blog_display_widget_domain' );
        	
        	if (isset($instance[ 'post_type' ]))
        	$post_type = $instance[ 'post_type' ];
        else
        	$post_type = __( 'post', 'blog_display_widget_domain' );
        	
        if (isset( $instance[ 'numresults' ]))
        	$numresults = $instance[ 'numresults' ];
        else
        	$numresults = __( '4', 'blog_display_widget_domain' );
        	
        if (isset( $instance[ 'sortby' ]))
        	$sortby = $instance[ 'sortby' ];
        else
        	$sortby = __( 'date', 'blog_display_widget_domain' );
        	
        	if (isset( $instance[ 'displaymode' ]))
        	$displaymode = $instance[ 'displaymode' ];
        else
        	$displaymode = __( 'grid', 'blog_display_widget_domain' );
        	
		if (isset($instance[ 'category' ]))
        	$category = $instance[ 'category' ];
        else
        	$category = __( '', 'blog_display_widget_domain' );
        
        
		$terms = get_terms( array(
			'taxonomy' => 'category',
			'hide_empty' => false,
		) );
		
        // Widget backend form
        ?>
        <p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'post_type' ); ?>"><?php _e( 'Post Type:' ); ?></label> 
			<input class="widefat" id="<?php echo $this->get_field_id( 'post_type' ); ?>" name="<?php echo $this->get_field_name( 'post_type' ); ?>" type="text" value="<?php echo esc_attr( $post_type ); ?>" />
		</p>
		<p>
	        	<label for="<?php echo $this->get_field_id( 'numresults' ); ?>"><?php _e( 'Number of Posts:' ); ?></label> 
	        	<input class="widefat" id="<?php echo $this->get_field_id( 'numresults' ); ?>" name="<?php echo $this->get_field_name( 'numresults' ); ?>" type="number" value="<?php echo esc_attr( $numresults ); ?>" />
        </p>
		<p>
			<label for="<?php echo $this->get_field_id( 'sortby' ); ?>"><?php _e( 'Sort By:' ); ?></label> 
			<select class="widefat" id="<?php echo $this->get_field_id( 'sortby' ); ?>" name="<?php echo $this->get_field_name( 'sortby' ); ?>" >
				<option value="date" <?php if(esc_attr( $sortby ) == 'date') echo 'selected'; ?> >Newest</option>
				<option value="featured" <?php if(esc_attr( $sortby ) == 'featured') echo 'selected'; ?> >Newest Featured</option>
				<option value="rand" <?php if(esc_attr( $sortby ) == 'rand') echo 'selected'; ?> >Random</option>
				<option value="upcoming" <?php if(esc_attr( $sortby ) == 'upcoming') echo 'selected'; ?> >Upcoming (Featured Events)</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'displaymode' ); ?>"><?php _e( 'Display Mode:' ); ?></label> 
			<select class="widefat" id="<?php echo $this->get_field_id( 'displaymode' ); ?>" name="<?php echo $this->get_field_name( 'displaymode' ); ?>" >
				<option value="grid" <?php if(esc_attr( $displaymode ) == 'grid') echo 'selected'; ?> >Explore Page</option>
				<option value="single-content" <?php if(esc_attr( $displaymode ) == 'single-content') echo 'selected'; ?> >Home Page Feature</option>
				<option value="blog" <?php if(esc_attr( $displaymode ) == 'blog') echo 'selected'; ?> >Full Blog (with paging)</option>
				<option value="event-feature" <?php if(esc_attr( $displaymode ) == 'event-feature') echo 'selected'; ?> >Event Feature</option>
			</select>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'category' ); ?>"><?php _e( 'Category:' ); ?></label> 
			<select class="widefat" id="<?php echo $this->get_field_id( 'category' ); ?>" name="<?php echo $this->get_field_name( 'category' ); ?>" >
				<option value="all" <?php if(esc_attr( $category ) == 'all') echo 'selected'; ?> >All</option>
				<option value="xeach" <?php if(esc_attr( $category ) == 'xeach') echo 'selected'; ?> >X of Each Category</option>
				<?php foreach($terms as $term){ ?>
				<option value="<?php echo $term->slug; ?>" <?php if(esc_attr( $category ) == $term->slug) echo 'selected'; ?> ><?php echo $term->name; ?></option>
				<?php } ?>
			</select>
		</p>
        <?php 
    }

    // Updating widget replacing old instances with new
    public function update( $new_instance, $old_instance ) {
        $instance = array();
        $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
        $instance['post_type'] = ( ! empty( $new_instance['post_type'] ) ) ? strip_tags( $new_instance['post_type'] ) : '';
        $instance['category'] = ( ! empty( $new_instance['category'] ) ) ? strip_tags( $new_instance['category'] ) : '';
        $instance['numresults'] = ( ! empty( $new_instance['numresults'] ) ) ? strip_tags( $new_instance['numresults'] ) : '';
        $instance['sortby'] = ( ! empty( $new_instance['sortby'] ) ) ? strip_tags( $new_instance['sortby'] ) : '';
        $instance['displaymode'] = ( ! empty( $new_instance['displaymode'] ) ) ? strip_tags( $new_instance['displaymode'] ) : '';
        return $instance;
    }
} // Class blog_display_widget ends here