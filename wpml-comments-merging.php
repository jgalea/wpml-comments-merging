<?php
/*
Plugin Name: WPML comments merging
Plugin URI: https://github.com/jgalea/wpml-comments-merging
Description: This plugin merges comments from all translations of the posts and pages, so that they all are displayed on each other. Comments are internally still attached to the post or page they were made on.
Version: 2.0
Author: Jean Galea
Author URI: http://www.jeangalea.com
License: GPL

This is a fixed version of the no longer maintained WPML Comment Merging plugin:
http://wordpress.org/extend/plugins/wpml-comment-merging/
Thanks to Simon Wheatley for contributing the fix.
*/

function sort_merged_comments($a, $b) { 
	return date('U', strtotime($a->comment_date_gmt)) - date('U', strtotime($b->comment_date_gmt));
}

function merge_comments($comments, $post_ID) {
	global $sitepress;
	remove_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ) );
	// get all the languages for which this post exists
	$languages = icl_get_languages('skip_missing=1');
	$post = get_post( $post_ID );
	$type = $post->post_type;
	foreach($languages as $code => $l) {
		// in $comments are already the comments from the current language
		if(!$l['active']) {
			$otherID = icl_object_id($post_ID, $type, false, $l['language_code']);
			$othercomments = get_comments( array('post_id' => $otherID, 'status' => 'approve', 'order' => 'ASC') );
			$comments = array_merge($comments, $othercomments);
		}
	}
	if ($languages) {
		// if we merged some comments in we need to reestablish an order
		usort($comments, 'sort_merged_comments');
	}
	//
	add_filter( 'comments_clauses', array( $sitepress, 'comments_clauses' ) );

	return $comments;
}
function merge_comment_count($count, $post_ID) {
	// get all the languages for which this post exists
	$languages = icl_get_languages('skip_missing=1');
	$post = get_post( $post_ID );
	$type = $post->post_type;

	foreach($languages as $l) {
		// in $count is already the count from the current language
		if(!$l['active']) {
			$otherID = icl_object_id($post_ID, $type, false, $l['language_code']);
			if($otherID) {
				// cannot use call_user_func due to php regressions
				if ($type == 'page') {
					$otherpost = get_page($otherID);
				} else {
					$otherpost = get_post($otherID);
				}
				if ($otherpost) {
					// increment comment count using translation post comment count.
					$count = $count + $otherpost->comment_count;
				}
			}
		}
	}
	return $count;
}

add_filter('comments_array', 'merge_comments', 100, 2);
add_filter('get_comments_number', 'merge_comment_count', 100, 2);
