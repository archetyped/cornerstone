<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>{#cnr_inturl_dlg.title}</title>
	<?php 
		require_once('../../../../../../wp-load.php');
		$js_dir = get_bloginfo('url') . '/' . WPINC . '/js/';
		
		//Retrieve posts/pages
		add_filter('posts_fields', 'filter_posts_fields');
		$posts = get_posts(array(
								'numberposts'		=> -1,
								'orderby'			=> 'title',
								'order'				=> 'ASC',
								'suppress_filters'	=> false
								));
		remove_filter('posts_fields', 'filter_posts_fields');
		$pages = get_pages();
		
		//Array for shortlist of latest posts
		$latest_limit = 5;
		$posts_latest = $posts;
		
		//Sort posts by date
		function sort_by_date($a, $b) {
			$da = strtotime($a->post_date);
			$db = strtotime($b->post_date);
			if ($da == $db)
				return 0;
			return ($da < $db) ? 1 : -1;
		}
		
		/**
		 * Returns indent determined by level
		 * @param int $level Level to get indent for
		 * @return string Indent string
		 */
		function get_indent_level($level) {
			//Single level indent string
			$s = "&nbsp;";
			$indent = $s;
			
			$level = intval($level);
			
			if ($level) {
				$level = 1;
			}
			
			for ($i = 0; $i < $level; $i++) {
				$indent .= $s;
			}
			
			return $indent;
			
		}
		
		/**
		 * Specifies post fields to retrieve
		 * @param string $fields Default list of fields to retrieve in query
		 * @return string post fields to retrieve in query
		 */
		function filter_posts_fields($fields) {
			global $wpdb;
			$fields = "$wpdb->posts.ID, $wpdb->posts.post_title, $wpdb->posts.post_date, $wpdb->posts.post_parent";
			return $fields;
		}
		
		usort($posts_latest, 'sort_by_date');
		
		//Truncate posts
		$posts_latest = array_slice($posts_latest, 0, $latest_limit);
		
		//Separate posts into buckets that match their parent
		$posts_bucket = array();
		
		foreach ($posts as $post) {
			//Create new array to contain child posts (if not yet existing)
			if (!array_key_exists($post->post_parent, $posts_bucket))
				$posts_bucket[$post->post_parent] = array();
			//Add post to bucket
			$posts_bucket[$post->post_parent][] = $post;
		}
		
		$format_option = '<option value="%2$d" class="%3$s">%1$s</option>'
		
	?>
	<script type="text/javascript" src="<?php echo $js_dir; ?>tinymce/tiny_mce_popup.js"></script>
	<script type="text/javascript" src="<?php echo $js_dir; ?>jquery/jquery.js"></script>
	<script type="text/javascript" src="js/dialog.js"></script>
	<link rel="stylesheet" type="text/css" href="css/dialog.css" />
</head>
<body>

<form onsubmit="CnrIntUrl.insert();return false;" action="#">
	<div class="panel_wrapper">
		<div id="general_panel" class="panel current">
			<!-- Post Selection -->
			<div class="input_group">
				<label for="link_post">Post</label>
				<select id="link_post" name="link_post">
					<optgroup label="Latest Posts" class="group_latest">
					<?php
						foreach ($posts_latest as $post) {
							printf($format_option, $post->post_title, $post->ID, 'post');
						}
						unset($post);
					?>
					</optgroup>
					<optgroup label="All Content" class="group_all">
					<?php
						foreach ($pages as $page) {
							//TODO: List Pages by Parent/Child relationships
							printf($format_option, $page->post_title, $page->ID, 'page');
							if (array_key_exists($page->ID, $posts_bucket)) {
								//Print child posts below page item
								$level = 1;
								foreach ($posts_bucket[$page->ID] as $post) {
									printf($format_option, get_indent_level($level) . $post->post_title, $post->ID, 'post child');
								}
							}
						}
						//List all posts without a parent
						if (array_key_exists(0, $posts_bucket)) {
							printf($format_option, '', 0, 'div');
							foreach($posts_bucket[0] as $post)
								printf($format_option, $post->post_title, $post->ID, 'post');
						}
					?>
					</optgroup>
				</select>
			</div>
			<!-- Anchor -->
			<div class="input_group">
				<label for="link_anchor" class="required">Anchor</label>
				<input id="link_anchor" name="link_anchor" type="text" class="text" />
				<div class="note">
					Optional: Link to a specific point in the page (e.g. 'comments')
				</div>
			</div>
			<!-- Title -->
			<div class="input_group">
				<label for="link_title">Title</label>
				<input id="link_title" name="link_title" type="text" class="text" />
				<div class="note">
					Optional: If no title is specified, title of linked page is used.
				</div>
			</div>
			<input type="hidden" id="link_text" class="link_text" />
		</div>
	</div>
	<div class="mceActionPanel">
		<div style="float: left">
			<input type="button" id="cancel" name="cancel" value="{#cancel}" onclick="tinyMCEPopup.close();" />
		</div>

		<div style="float: right">
			<input type="submit" id="insert" name="insert" value="{#insert}" />
		</div>
	</div>
	
</form>
</body>
</html>
