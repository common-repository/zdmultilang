<?php
/*
Plugin Name: ZdMultiLang
Plugin URI: http://blog.zen-dreams.com/en/zdmultilang
Description: This plugin adds multilingual capabilities to wordpress
Version: 1.2.5
Author: Anthony PETITBOIS, Pau Sanchez
Author URI: http://www.zen-dreams.com/

Copyright 2008  Anthony PETITBOIS  (email : anthony@zen-dreams.com)

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
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$ZdmlCache=array();
$BaseURL=$_SERVER['PHP_SELF'].'?page='.plugin_basename (__FILE__);
$default_language_option="zd_multilang_defaultlanguage";
$insert_lang_switch_option="zd_multilang_lang_switcher";
$show_flags_option="zd_multilang_show_flags";
$show_languages_option="zd_multilang_langnames";
$lang_switcher_class_option="zd_multilang_switcher_class";
$permalink_default="zd_multilang_permalink_def";
$display_untranslated="zd_multilang_display_untranslated";
$display_google_translate="zd_multilang_display_glink";
$display_original_option="zd_multilang_display_original";
$keep_separate_comments="zd_multilang_keep_separate_comments";
$Allowed_Access="zd_multilang_access";
$Autosave_Option="zdmultilang_autosave";
$CurrentLanguagePermalink="";
$PluginDIR = '../'.PLUGINDIR . '/' . dirname(plugin_basename (__FILE__));

load_plugin_textdomain('zd_multilang',PLUGINDIR . '/' . dirname(plugin_basename (__FILE__)) . '/lang');
register_activation_hook(__FILE__,'zd_multilang_install');
add_action('admin_menu', 'zd_multilang_add_pages');
add_filter('get_term', 'zd_multilang_translate_term',2);	// Translate single terms
add_filter('admin_head','zd_multilang_tinymce');
add_filter('the_category','zd_multilang_translate_link_cat');  // Categories for posts
add_filter('get_pages','zd_multilang_translate_post');  // Translation of Pages
add_filter('get_tags','zd_multilang_translate_tags');  // Translation of tags
add_filter('list_cats','zd_multilang_translate_cat');	 // Categories for lists (widget)
add_filter('get_bookmarks','zd_multilang_translate_bookmarks');
add_filter('wp_list_bookmarks','zd_multilang_translate_list_bookmarks');
add_filter('wp_tag_cloud','zd_multilang_translate_link_cat');
add_filter('category_description','zd_multilang_cat_desc',10,2);
add_filter('get_categories','zd_multilang_cat');
add_filter('the_posts','zd_multilang_translate_post',10,1);
add_filter('query_vars', 'zd_multilang_queryvars');
add_filter('rewrite_rules_array', 'zd_multilang_rewrite');
add_filter('next_post_link','zd_multilang_postlink');
add_filter('previous_post_link','zd_multilang_postlink');
add_filter('post_link','zd_multilang_permalink',1);
add_filter('page_link','zd_multilang_permalink',1);
add_filter('category_link','zd_multilang_permalink',1);
add_filter('get_category','zd_multilang_cat',1);
add_filter('tag_link','zd_multilang_permalink',1);
add_filter('year_link','zd_multilang_permalink',1);
add_filter('month_link','zd_multilang_permalink',1);
add_filter('day_link','zd_multilang_permalink',1);
add_filter('feed_link','zd_multilang_permalink',1);
add_filter('author_link','zd_multilang_permalink',1);
add_filter('wp','zd_multilang_set_locale');
add_filter('manage_posts_columns', 'zd_multilang_manage_col_def');
add_filter('manage_pages_columns', 'zd_multilang_manage_col_def');
add_filter('posts_where','zd_multilang_where_filter');
add_filter('get_next_post_where','zd_multilang_np_where_filter');
add_filter('get_previous_post_where','zd_multilang_np_where_filter');
/* Filters for comments */
add_action ('wp_insert_comment', 'zd_multilang_insert_comment', 10, 2);
add_action ('delete_comment', 'zd_multilang_delete_comment');
add_filter ('comments_array','zd_multilang_comments_array', 10, 2);
add_filter ('get_comments_number', 'zd_multilang_get_comments_number');

/* Options short circuit */
add_filter('option_blogname','zd_multilang_blogname');
add_filter('option_blogdescription','zd_multilang_blogdescription');
/* Options short circuit */

add_action('manage_posts_custom_column', 'zd_multilang_manage_col', 10, 2);
add_action('manage_pages_custom_column', 'zd_multilang_manage_col', 10, 2);
add_action('media_buttons', 'zd_media_button', 30);

/** Action handler for autosave feature **/
add_action('wp_ajax_zdmultilang_autosave', 'zd_multilang_autosave');
//add_action('wp_ajax_nopriv_my_special_action', 'my_action_callback');

zd_multilang_set_locale("");

function zd_multilang_tinymce() {
	if (($_GET['fct']=="posts")||($_POST['fct']=="posts")) {
		wp_enqueue_script( 'common' );
		wp_enqueue_script( 'jquery-color' );
		wp_print_scripts('editor');
		if (function_exists('add_thickbox')) add_thickbox();
		wp_print_scripts('media-upload');
		if (function_exists('wp_tiny_mce')) wp_tiny_mce();
		wp_admin_css();
		wp_enqueue_script('utils');
		do_action("admin_print_styles-post-php");
		do_action('admin_print_styles');
	}
}

function zd_multilang_queryvars( $qvars ) {
  $qvars[] = 'lang';
  return $qvars;
}

function zd_multilang_manage_col_def ($cols) {
	$cols['translate']=__('Translate','zd_multilang');
	return $cols;
}

function zd_multilang_manage_col($column_name, $id) {
	global $ZdmlCache, $PluginDIR;
	$BaseURL='admin.php?page='.plugin_basename (__FILE__);
	if( $column_name == 'translate' ) {
		if ($ZdmlCache['Languages']) foreach ($ZdmlCache['Languages'] as $Permalink => $LanguageID) {
			if ($LanguageID!=$ZdmlCache['DefLang']) echo '<a href="'.$BaseURL.'&amp;fct=posts&amp;id='.$id.'.'.$LanguageID.'"><img src="'.$PluginDIR.'/flags/'.$LanguageID.'.png" alt="'.$LanguageID.'" title="'.$LanguageID.'"></a> ';
		}
	}
}

function zd_media_button() {
	global $ZdmlCache, $PluginDIR;
	$id=$_GET['post'];
	if ($id) {
		$BaseURL='admin.php?page='.plugin_basename (__FILE__);
		if ($ZdmlCache['Languages']) foreach ($ZdmlCache['Languages'] as $Permalink => $LanguageID) {
			if ($LanguageID!=$ZdmlCache['DefLang']) echo '<a href="'.$BaseURL.'&amp;fct=posts&amp;id='.$id.'.'.$LanguageID.'" target="_blank" title="'.__('Translate','zd_multilang').'"><img src="'.$PluginDIR.'/flags/'.$LanguageID.'.png" alt="'.$LanguageID.'" title="'.$LanguageID.'"></a>';
		}
	}
}

function zd_multilang_where_filter($filter) {
	global $ZdmlCache, $locale,$display_untranslated, $wpdb, $wp_query;

	if ((get_option('show_on_front')=='page')&&(is_array($wp_query->query))&&(count($wp_query->query)<=1)&&(isset($wp_query->query['lang']))) {
		$lang=$WP->query['lang'];
		$filter=" AND ".$wpdb->posts.".ID = ".get_option('page_on_front');
		$wp_query->is_page=1;
		$wp_query->is_home=0;
	}
	
	if ($locale==$ZdmlCache['DefLang']) return $filter;
	if (get_option($display_untranslated)=="hide") {
		$filter.=" AND ".$wpdb->posts.".ID in (".$ZdmlCache['TranslatedPosts'][$locale].')';
	}
	return $filter;
}

function zd_multilang_np_where_filter($filter) {
	global $ZdmlCache, $locale,$display_untranslated, $wpdb;
	if ($locale==$ZdmlCache['DefLang']) return $filter;
	if (get_option($display_untranslated)=="hide") {
		$filter.=" AND p.ID in (".$ZdmlCache['TranslatedPosts'][$locale].')';
	}
	return $filter;
}

function zd_multilang_set_locale($WP) {
	global $wp_query,$wpdb, $wp_rewrite,$default_language_option,$locale,$CurrentLanguagePermalink,$CurrentLang;
	global $ZdmlCache;
	
	$language_table=$wpdb->prefix.'zd_ml_langs';
	$termtrans=$wpdb->prefix.'zd_ml_termtrans';
	$posttrans=$wpdb->prefix.'zd_ml_trans';
	$linktrans=$wpdb->prefix.'zd_ml_linktrans';

	$ZdmlCache['DefLang']=get_option("zd_multilang_defaultlanguage");
	$ZdmlCache['InsertInPosts']=get_option("zd_multilang_lang_switcher");
	$ZdmlCache['ShowLanguages']=get_option("zd_multilang_langnames");
	$ZdmlCache['ShowFlags']=get_option("zd_multilang_show_flags");
	$ZdmlCache['SwitcherPosition']=get_option('zd_multilang_position');
	$ZdmlCache['Lang_Switch_Class']=(get_option("zd_multilang_switcher_class")!="") ? get_option("zd_multilang_switcher_class") : "lang_switch";

	$TheLocale=$ZdmlCache['DefLang'];
	
	if (!isset($ZdmlCache['Languages'])) {
		$query="SELECT * FROM $language_table ORDER BY LanguageName";
		$results=$wpdb->get_results($query, ARRAY_A);
		if ($results) {
			foreach ($results as $ID => $V) {
				$ZdmlCache['Languages'][$V['LangPermalink']]=$V['LanguageID'];
				$ZdmlCache['LanguageNames'][$V['LangPermalink']]=$V['LanguageName'];
				$ZdmlCache['Options'][$V['LanguageID']]['blogname']=$V['BlogName'];
				$ZdmlCache['Options'][$V['LanguageID']]['blogdescription']=$V['BlogDescription'];
			}
		}
	}	
	 else {
		foreach ($ZdmlCache['Languages'] as $Permalink => $LangID) {
			$results[$i]['LangPermalink']=$Permalink;
			$results[$i]['LanguageID']=$LangID;
			$i++;
		}
	}
	
	if ($_SERVER['HTTPS']) $QUERY="https://".$_SERVER['HTTP_HOST'];
	else $QUERY="http://".$_SERVER['HTTP_HOST'];
	$QUERY.=$_SERVER['REQUEST_URI'];

	if ($results) {
		foreach ($results as $ID => $Lang) {
			$regexp.=$Lang['LangPermalink']."|";
		}
		$regexp=substr($regexp, 0, -1);
	}
	if (strstr($QUERY,"?")) $RegularExpression="`".get_bloginfo('url')."\/.*lang=($regexp)(.*)?`U";
	else $RegularExpression="`".get_bloginfo('url')."\/($regexp)\/(.*)?`U";
	
	if (preg_match($RegularExpression,$QUERY,$res)==TRUE) {
		$Lang=$res[1];
		$CurrentLanguagePermalink=$res[1];
		foreach ($ZdmlCache['Languages'] as $Permalink => $LangFound) {
			if ($Permalink==$Lang) {
				$Language= $LangFound;
				break;
			}
		}
		$CurrentLang=$Language;
		if ($Language) $TheLocale=$Language;
	}
	$locale=$TheLocale;

	if (!isset($ZdmlCache['Terms'])) {
		$query="SELECT tt.LanguageID, tt.name as t_name, tt.description as description, t.* FROM $termtrans tt RIGHT JOIN ".$wpdb->prefix."terms t on (tt.term_id=t.term_id)";
		$term=$wpdb->get_results($query, ARRAY_A);
		foreach ($term as $ID => $Value) {
			if ($Value['t_name']!=NULL) $ZdmlCache['Terms'][$Value['term_id']][$Value['LanguageID']]['name']=$Value['t_name'];
			else $ZdmlCache['Terms'][$Value['term_id']][$Value['LanguageID']]['name']=$Value['name'];
			$ZdmlCache['Terms'][$Value['term_id']][$Value['LanguageID']]['o_name']=$Value['name'];
			$ZdmlCache['Terms'][$Value['term_id']][$Value['LanguageID']]['description']=$Value['description'];
		}
	}
	
	if (!isset($ZdmlCache['Links'])) {
		$query="SELECT * from $linktrans";
		$links=$wpdb->get_results($query);
		foreach ($links as $id => $Values) {
			$ZdmlCache['Links'][$Values->link_id][$Values->LanguageID]['name']=$Values->link_name;
			$ZdmlCache['Links'][$Values->link_id][$Values->LanguageID]['url']=$Values->link_url;
			$ZdmlCache['Links'][$Values->link_id][$Values->LanguageID]['description']=$Values->link_description;
		}
	}

	if (!isset($ZdmlCache['TranslatedPosts'])) {
		if ($ZdmlCache['Languages']) foreach ($ZdmlCache['Languages'] as $Permalink => $LangID) {
			if ($LangID!=$ZdmlCache['DefLang']) {
				$query="SELECT ID from $posttrans WHERE LanguageID='$LangID' and post_status='published'";
				$res=$wpdb->get_results($query, ARRAY_A);
				if ($res) {
					$ZdmlCache['TranslatedPosts'][$LangID]="";
					foreach ($res as $key => $V) {
						$ZdmlCache['TranslatedPosts'][$LangID].=$V['ID'].",";
					}
					$ZdmlCache['TranslatedPosts'][$LangID]=substr($ZdmlCache['TranslatedPosts'][$LangID],0,-1);
				}
			}
		}
	}
	
	return $WP;
}

function zd_multilang_permalink($permalink) {
	global $wpdb, $wp_rewrite,$default_language_option, $CurrentLanguagePermalink,$CurrentLang,$permalink_default, $locale, $wp_query;
	global $ZdmlCache;
	$langstable=$wpdb->prefix.'zd_ml_langs';
	
	$Lang=$CurrentLanguagePermalink;
	if ($Lang=="") {
		$CurrentLang=$ZdmlCache['DefLang'];
		$query="SELECT LangPermalink FROM $langstable where LanguageID='$CurrentLang'";
		$CurrentLanguagePermalink=$wpdb->get_var($query);
		$Lang=$CurrentLanguagePermalink;
	}
	$link=$permalink;

	$PermalinkDef=get_option($permalink_default);
	if (($PermalinkDef=="no")&&($ZdmlCache['DefLang']==$locale)) return $link;
	
	if ($wp_rewrite->using_permalinks()) {
		$url=get_bloginfo('url');
		$end=substr($link,strlen($url));
		if ($Lang=="") $link=$url.$end;
		else $link=$url.'/'.$Lang.$end;
	} else if ($Lang) $link.= ((!strpos($link,'?'))? '?': '') ."&lang=".$Lang;

	return $link;
}

function zd_multilang_rewrite($permalink_structure) {
	global $ZdmlCache;
	global $wpdb, $wp_rewrite;
	$langs=$wpdb->prefix.'zd_ml_langs';
	
	$query="SELECT * FROM $langs order by LanguageID";
	$Lines=$wpdb->get_results($query, ARRAY_A);
	if ($Lines) {
		$regexp='(';
		foreach ($Lines as $Value) {
			$regexp.=$Value['LangPermalink'].'|';
		}
		$regexp=substr($regexp,0,-1);
		$regexp.=')';

		if ($permalink_structure) foreach ($permalink_structure as $Rule => $Definition) {
			$def=explode('?',$Definition);
			$rule=$Definition;
			if (preg_match_all('/(.*matches)\[([0-9]+)\]/U',$rule,$res)) {
				$rule="";
				foreach ($res[1] as $index => $text) {
					$rule.=$text.'['.($index+2).']';
				}
			}
			$rule.='&lang=$matches[1]';
			$new_rules[$regexp.'/'.$Rule]=$rule;
		}
		$new_rules2[$regexp.'/?']='index.php?lang=$matches[1]';
		if ($permalink_structure) $permalink_structure = $new_rules+ $new_rules2 + $permalink_structure;
	}
	return $permalink_structure;
}

function zd_multilang_is_translated($id, $lang) {
	global $ZdmlCache, $locale;
	if ($lang==$ZdmlCache['DefLang']) return TRUE;
	$Posts=explode(',',$ZdmlCache['TranslatedPosts'][$lang]);
	foreach ($Posts as $key => $ID) {
		if ($ID==$id) {
			return TRUE;
		}
	}
	return FALSE;
}

function zd_multilang_install() {
	global $ZdmlCache;
	global $wpdb;
	$termtrans=$wpdb->prefix.'zd_ml_termtrans';
	$langs=$wpdb->prefix.'zd_ml_langs';
	$posttrans=$wpdb->prefix.'zd_ml_trans';
	$linktrans=$wpdb->prefix.'zd_ml_linktrans';
	$commenttrans=$wpdb->prefix.'zd_ml_comments';
	
	$dbversion=get_option('zd_multilang_dbschema');
	if ($dbversion<"125") {
		$sql="CREATE TABLE $langs (
			LanguageID varchar(5) NOT NULL,
			LanguageName varchar(100) character set utf8 NOT NULL,
			LangPermalink varchar(50) NOT NULL UNIQUE,
			BlogName longtext,
			BlogDescription longtext,
			primary key (LanguageID)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);

		$sql="CREATE TABLE $termtrans (
			term_id varchar(5) NOT NULL,
			LanguageID varchar(5) NOT NULL,
			name varchar(255) character set utf8 NOT NULL,
			description longtext NOT NULL,
			primary key (term_id,LanguageID)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		$sql="CREATE TABLE $posttrans (
			ID BIGINT(20) NOT NULL,
			LanguageID varchar(5),
			post_content longtext character set utf8,
			post_excerpt text character set utf8,
			post_title text character set utf8 NOT NULL,
			post_status varchar(10),
			primary key (ID, LanguageID)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		$sql="CREATE TABLE $linktrans (
			link_id BIGINT(20) NOT NULL,
			LanguageID varchar(5),
			link_url varchar(255),
			link_name varchar(255) character set utf8,
			link_description varchar(255) character set utf8,
			primary key (link_id, LanguageID)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
	
		$sql="UPDATE $posttrans set post_status='published' where post_status is NULL";
		$wpdb->query($sql);
		
		// Comment translations only should keep track of the language where a single comment has been submitted
		$sql="CREATE TABLE $commenttrans (
			comment_id BIGINT(20) UNSIGNED NOT NULL,
			post_id BIGINT(20) UNSIGNED NOT NULL,
			LanguageID varchar(5),
			primary key (comment_id)
		);";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		update_option('zd_multilang_dbschema',125);
		update_option('zd_multilang_access',10);
	}
}

function zd_multilang_add_pages() {
	global $Allowed_Access;
	$Allowed_Access_Level=get_option($Allowed_Access);
	add_menu_page('Zd_MultiLang', __('Translations','zd_multilang'), $Allowed_Access_Level,  __FILE__, 'zd_multilang_page');
	add_submenu_page(__FILE__,__('Posts','zd_multilang'),__('Posts','zd_multilang'),$Allowed_Access_Level,__FILE__.'&amp;fct=posts&amp;tr=posts','zd_multilang_page');
	add_submenu_page(__FILE__,__('Pages','zd_multilang'),__('Pages','zd_multilang'),$Allowed_Access_Level,__FILE__.'&amp;fct=posts&amp;tr=pages','zd_multilang_page');
	add_submenu_page(__FILE__,__('Categories','zd_multilang'),__('Categories','zd_multilang'),$Allowed_Access_Level,__FILE__.'&amp;fct=translations&amp;tr=cat','zd_multilang_page');
	add_submenu_page(__FILE__,__('Tags','zd_multilang'),__('Tags','zd_multilang'),$Allowed_Access_Level,__FILE__.'&amp;fct=translations&amp;tr=tags','zd_multilang_page');
	add_submenu_page(__FILE__,__('Links','zd_multilang'),__('Links','zd_multilang'),$Allowed_Access_Level,__FILE__.'&amp;fct=links','zd_multilang_page');
	add_submenu_page(__FILE__,__('Link Categories','zd_multilang'),__('Link Categories','zd_multilang'),$Allowed_Access_Level,__FILE__.'&amp;fct=translations&amp;tr=linkcat','zd_multilang_page');
	add_submenu_page(__FILE__,__('Languages','zd_multilang'),__('Languages','zd_multilang'),'manage_options',__FILE__.'&amp;fct=languages','zd_multilang_page');
	add_submenu_page(__FILE__,__('Options','zd_multilang'),__('Options','zd_multilang'),'manage_options',__FILE__.'&amp;fct=options','zd_multilang_page');
}

function zd_multilang_page() {
	global $BaseURL, $current_user;
	get_currentuserinfo();
	echo "\n".'<div class="wrap">';
	if ($_POST['fct']) $_GET['fct']=$_POST['fct'];
	switch ($_GET['fct']) {
		case 'options':
			if ($current_user->allcaps['manage_options']==1)
				zd_multilang_options();
			else {
				echo '<div id="message" class="error"><p>Only the administrator can edit the options</p></div>';
				zd_multilang_dashboard();
			}
			break;
		case 'edit':
			if ($current_user->allcaps['manage_options']==1)
				zd_multilang_edit_language();
			else {
				echo '<div id="message" class="error"><p>Only the administrator can edit the options</p></div>';
				zd_multilang_dashboard();
			}
			break;
		case 'delete':
			if ($current_user->allcaps['manage_options']==1)
				zd_multilang_delete_language();
			else {
				echo '<div id="message" class="error"><p>Only the administrator can edit the options</p></div>';
				zd_multilang_dashboard();
			}
			break;
		case 'posts':
			zd_multilang_post_translations();
			break;
		case 'translations';
			zd_multilang_term_translations();
			break;
		case 'languages':
			if ($current_user->allcaps['manage_options']==1)
				zd_multilang_languages();
			else {
				echo '<div id="message" class="error"><p>Only the administrator can edit the options</p></div>';
				zd_multilang_dashboard();
			}
			break;
		case 'links':
			zd_multilang_link_translations();
			break;
		default:
			zd_multilang_dashboard();
			break;
	}
	echo "\n</div>";
}

function zd_multilang_dashboard() {
	global $BaseURL, $current_user;
	get_currentuserinfo();;
	echo '<h2>'.__('Language Dashboard','zd_multilang').'</h2>';
	echo '<ul>';
		echo '<li><a href="'.$BaseURL.'&amp;fct=posts&amp;tr=posts">'.__('Translate posts','zd_multilang').'</a></li>';
		echo '<li><a href="'.$BaseURL.'&amp;fct=posts&amp;tr=pages">'.__('Translate pages','zd_multilang').'</a></li>';
		echo '<li><a href="'.$BaseURL.'&amp;fct=translations&amp;tr=cat">'.__('Translate categories','zd_multilang').'</a></li>';
		echo '<li><a href="'.$BaseURL.'&amp;fct=translations&amp;tr=tags">'.__('Translate tags','zd_multilang').'</a></li>';
		echo '<li><a href="'.$BaseURL.'&amp;fct=links">'.__('Translate links','zd_multilang').'</a></li>';
		echo '<li><a href="'.$BaseURL.'&amp;fct=translations&amp;tr=linkcat">'.__('Translate link categories','zd_multilang').'</a></li>';
		if ($current_user->allcaps['manage_options']==1) echo '<li><a href="'.$BaseURL.'&amp;fct=languages">'.__('Define languages','zd_multilang').'</a></li>';
		if ($current_user->allcaps['manage_options']==1) echo '<li><a href="'.$BaseURL.'&amp;fct=options">'.__('Manage options','zd_multilang').'</a></li>';
	echo '</ul>';
	echo '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="8262513">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
}

function zd_multilang_options() {
	global $wpdb, $BaseURL,$default_language_option,$insert_lang_switch_option,$show_flags_option,$display_google_translate;
	global $show_languages_option,$lang_switcher_class_option,$permalink_default,$display_untranslated, $display_original_option,$keep_separate_comments, $Allowed_Access,$Autosave_Option;
	global $ZdmlCache;
	
	$language_table=$wpdb->prefix.'zd_ml_langs';
	$termtrans=$wpdb->prefix.'zd_ml_termtrans';
	$posttrans=$wpdb->prefix.'zd_ml_trans';
	$hidden_field="zd_multilang_update_options";
	$language_table= $wpdb->prefix.'zd_ml_langs';
	
	echo '<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="8262513">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>';
	
	if ($_POST[$hidden_field]) {
		$OldLang=get_option($default_language_option);
		$DefLang=$_POST['def_lang'];
		$DisplayOriginal=$_POST['display_original'];
		$InsertInPosts=$_POST['show_language_switcher'];
		$Show_Languages=$_POST['show_languages'];
		$Show_Flags=$_POST['show_flags'];
		$Lang_Switch_Class=$_POST['lang_switch_class'];
		$SwitcherPosition=$_POST['language_switcher_position'];
		$KeepSeparateComments=$_POST['keep_separate_comments'];
		$PermalinkDef=$_POST['permalink_for_default'];
		$Exchange=$_POST['exchange_lang'];
		$DisplayUntranslated=$_POST['display_untranslated'];
		$DisplayGLink=$_POST['display_glink'];
		$Allowed_Access_Level=$_POST['access_level'];
		$Autosave=$_POST['autosave'];
		
		update_option('zd_multilang_position',$SwitcherPosition);
		update_option($default_language_option,$DefLang);
		update_option($display_original_option, $DisplayOriginal);
		update_option($insert_lang_switch_option,$InsertInPosts);
		update_option($show_languages_option,$Show_Languages);
		update_option($show_flags_option,$Show_Flags);
		update_option($lang_switcher_class_option,$Lang_Switch_Class);
		update_option($permalink_default, $PermalinkDef);
		update_option($display_untranslated,$DisplayUntranslated);
		update_option($display_google_translate,$DisplayGLink);
		update_option($keep_separate_comments, $KeepSeparateComments);
		update_option($Allowed_Access,$Allowed_Access_Level);
		update_option($Autosave_Option,$Autosave);
		
		echo '<div id="message" class="updated fade">';
		if ($Exchange=="on") {
			$query="SELECT * FROM $posttrans where LanguageID='$DefLang'";
			$TrPosts=$wpdb->get_results($query, ARRAY_A);
			$query="SELECT * FROM $termtrans where LanguageID='$DefLang'";
			$TrTerms=$wpdb->get_results($query, ARRAY_A);

			if ($TrPosts) foreach ($TrPosts as $key => $V) {
				$query="SELECT * from ".$wpdb->posts." WHERE ID=".$V['ID'];
				$res=$wpdb->get_row($query);
				$OriginalTitle=$res->post_title;
				$OriginalContent=$wpdb->escape($res->post_content);
				$NewContent=$wpdb->escape($V['post_content']);
				$NewTitle=$V['post_title'];
				$q1="UPDATE ".$wpdb->posts." set post_title='$NewTitle', post_content='$NewContent' WHERE ID=".$V['ID'];
				$wpdb->query($q1);
				$q1="UPDATE $posttrans set post_title='$OriginalTitle', post_content='$OriginalContent', LanguageID='$OldLang' WHERE ID=".$V['ID']." and LanguageID='$DefLang'";
				$wpdb->query($q1);
			}
			if ($TrTerms) foreach ($TrTerms as $key => $V) {
				$query="SELECT * from ".$wpdb->terms." WHERE term_id=".$V['term_id'];
				$res=$wpdb->get_row($query);
				$OriginalTerm=$res->name;
				$NewTerm=$V['name'];
				$q1="UPDATE ".$wpdb->terms." SET name='$NewTerm' WHERE term_id=".$V['term_id'];
				$wpdb->query($q1);
				$q1="UPDATE $termtrans SET name='$OriginalTerm', LanguageID='$OldLang' WHERE LanguageID='$DefLang' and term_id=".$V['term_id'];

				$wpdb->query($q1);
			}
			echo '<p>'.__('Default Languages Exchanged','zd_multilang').'</p>';
		}
		echo '<p>'.__('Options updated','zd_multilang').'</p>';
		echo '</div>';
	}
	$query="SELECT * FROM $language_table order by LanguageName";
	$Languages=$wpdb->get_results($query, ARRAY_A);
	$DefaultLanguage=get_option($default_language_option);
	$InsertInPosts=get_option($insert_lang_switch_option);
	$ShowLanguages=get_option($show_languages_option);
	$ShowFlags=get_option($show_flags_option);
	$SwitcherPosition=get_option('zd_multilang_position');
	$DisplayUntranslated=get_option($display_untranslated);
	$DisplayGlink=get_option($display_google_translate);
	$DisplayOriginal=get_option($display_original_option);
	$Lang_Switch_Class=(get_option($lang_switcher_class_option)!="") ? get_option($lang_switcher_class_option) : "lang_switch";
	$Allowed_Access_Level=get_option($Allowed_Access);
	$Autosave=get_option($Autosave_Option);
	
	echo '<style>
	td select { width: 150px;}
	td input { width: 150px;}
	</style>';

	echo "\n\t<h2>".__('General Options','zd_multilang')."</h2>";
	echo "\n\t".'<form action="'.$BaseURL.'" method="post">';
	echo "\n\t".'<input type="hidden" name="'.$hidden_field.'" value="update" />';
	echo "\n\t".'<input type="hidden" name="fct" value="options" />';
	echo "\n\t".'<table class="form-table">';
	echo "\n\t\t<tr><td width=\"400\">".__('Level required to translate items','zd_multilang')."</td><td>";
		echo '<select name="access_level">';
			echo '<option value="0"';if ($Allowed_Access_Level=="0") echo ' selected="selected"';echo '>'.__('Subscriber').'</option>';
			echo '<option value="1"';if ($Allowed_Access_Level=="1") echo ' selected="selected"';echo '>'.__('Contributor').'</option>';
			echo '<option value="2"';if ($Allowed_Access_Level=="2") echo ' selected="selected"';echo '>'.__('Author').'</option>';
			echo '<option value="7"';if ($Allowed_Access_Level=="7") echo ' selected="selected"';echo '>'.__('Editor').'</option>';
			echo '<option value="10"';if ($Allowed_Access_Level=="10") echo ' selected="selected"';echo '>'.__('Administrator').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Default Language','zd_multilang')."</td><td>";
		echo '<select name="def_lang" onchange="getElementById(\'hidden_option\').style.display=\'table-row\';">';
			if ($Languages) foreach ($Languages as $Index => $Values) {
				echo '<option value="'.$Values['LanguageID'].'"';
					if ($DefaultLanguage==$Values['LanguageID']) echo ' selected="selected"';
				echo '>'.$Values['LanguageName'].'</option>';
			}
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr id=\"hidden_option\" style=\"display: none;\"><td>".__('Exchange Languages','zd_multilang')."<br /><small>".__('Only use this option if you want to switch old default language with new one. This will exchange translations between them','zd_multilang')."</small></td><td>";
		echo '<input type="checkbox" name="exchange_lang">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Display Original post while translating','zd_multilang')."</td><td>";
		echo '<select name="display_original">';
			echo '<option value="yes"';if ($DisplayOriginal=="yes") echo ' selected="selected"';echo '>'.__('Yes','zd_multilang').'</option>';
			echo '<option value="no"';if ($DisplayOriginal=="no") echo ' selected="selected"';echo '>'.__('No','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Automatically save translation every 5 minutes','zd_multilang')."</td><td>";
		echo '<select name="autosave">';
			echo '<option value="yes"';if ($Autosave=="yes") echo ' selected="selected"';echo '>'.__('Yes','zd_multilang').'</option>';
			echo '<option value="no"';if ($Autosave=="no") echo ' selected="selected"';echo '>'.__('No','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Generate permalink for Default language','zd_multilang')."</td><td>";
		echo '<select name="permalink_for_default">';
			echo '<option value="yes"';if ($PermalinkDef=="yes") echo ' selected="selected"';echo '>'.__('Yes','zd_multilang').'</option>';
			echo '<option value="no"';if ($PermalinkDef=="no") echo ' selected="selected"';echo '>'.__('No','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Keep separate comments for each language','zd_multilang')."</td><td>";
		echo '<select name="keep_separate_comments">';
			echo '<option value="yes"';if ($KeepSeparateComments=="yes") echo ' selected="selected"';echo '>'.__('Yes','zd_multilang').'</option>';
			echo '<option value="no"';if ($KeepSeparateComments=="no") echo ' selected="selected"';echo '>'.__('No','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Display untranslated posts','zd_multilang')."</td><td>";
		echo '<select name="display_untranslated">';
			echo '<option value="show"';if ($DisplayUntranslated=="show") echo ' selected="selected"';echo '>'.__('Show','zd_multilang').'</option>';
			echo '<option value="hide"';if ($DisplayUntranslated=="hide") echo ' selected="selected"';echo '>'.__('Hide','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('If Yes, display link "Translate Original post with Google Translate"','zd_multilang')."</td><td>";
		echo '<select name="display_glink">';
			echo '<option value="show"';if ($DisplayGlink=="show") echo ' selected="selected"';echo '>'.__('Show','zd_multilang').'</option>';
			echo '<option value="hide"';if ($DisplayGlink=="hide") echo ' selected="selected"';echo '>'.__('Hide','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo '</table>';
	echo '<br /><h2>'.__('Language Switcher','zd_multilang').'</h2>';
	echo "\n\t".'<table class="form-table">';
	echo "\n\t\t<tr><td width=\"400\">".__('Show Language Switcher in post','zd_multilang')."</td><td>";
		echo '<select name="show_language_switcher">';
			echo '<option value="show"';if ($InsertInPosts=="show") echo ' selected="selected"';echo '>'.__('Show','zd_multilang').'</option>';
			echo '<option value="hide"';if ($InsertInPosts=="hide") echo ' selected="selected"';echo '>'.__('Hide','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Language Switcher Position','zd_multilang')."</td><td>";
		echo '<select name="language_switcher_position">';
			echo '<option value="top"';if ($SwitcherPosition=="top") echo ' selected="selected"';echo '>'.__('Top','zd_multilang').'</option>';
			echo '<option value="footer"';if ($SwitcherPosition=="footer") echo ' selected="selected"';echo '>'.__('Bottom','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Show Language names in switcher','zd_multilang')."</td><td>";
		echo '<select name="show_languages">';
			echo '<option value="show"';if ($ShowLanguages=="show") echo ' selected="selected"';echo '>'.__('Show','zd_multilang').'</option>';
			echo '<option value="hide"';if ($ShowLanguages=="hide") echo ' selected="selected"';echo '>'.__('Hide','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Show Flags in switcher','zd_multilang')."</td><td>";
		echo '<select name="show_flags">';
			echo '<option value="show"';if ($ShowFlags=="show") echo ' selected="selected"';echo '>'.__('Show','zd_multilang').'</option>';
			echo '<option value="hide"';if ($ShowFlags=="hide") echo ' selected="selected"';echo '>'.__('Hide','zd_multilang').'</option>';
		echo '</select>';
	echo "</td></tr>";	echo "\n\t\t<tr><td>".__('Language Switcher CSS class','zd_multilang')."</td><td>";
		echo '<input name="lang_switch_class" value="'.$Lang_Switch_Class.'">';
	echo "</td></tr>";
	echo "\n\t".'</table>';
	echo "\n\t".'<p class="submit"><input class="button" type="submit" value="'.__('Update options','zd_multilang').'" name="submit"/></p>';
	echo "\n".'</form>';
}

function zd_multilang_edit_language() {
	global $wpdb, $BaseURL,$default_language_option, $BaseURL, $PluginDIR, $wp_rewrite;
	global $ZdmlCache;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$hidden_field="zd_multilang_edit_language";


	if ($_POST[$hidden_field]) {
		$Action=$_POST[$hidden_field];
		$LangCode=$_POST['lang_code'];
		$LangName=$_POST['lang_name'];
		$LangPermalink=$_POST['lang_permalink'];
		$BlogName=$_POST['blog_name'];
		$BlogDesc=$_POST['blog_description'];
		echo '<div id="message" class="updated fade"><p>';
		if ($Action=="edit") {
			$query="UPDATE $language_table set LanguageName='$LangName',LangPermalink='$LangPermalink', BlogName='$BlogName', BlogDescription='$BlogDesc' WHERE LanguageID='$LangCode'";
			$wpdb->query($query);
			if ($_POST['def_lang']=="on") update_option($default_language_option,$LangCode);
			echo __('Language Edited','zd_multilang');
		}
		echo '</p></div>';
		zd_multilang_languages();
		return;
	}
	$DefaultLanguage=$ZdmlCache['DefLang'];
	$Code=$_GET['lang'];
	$query="SELECT * FROM $language_table where LanguageID='$Code'";
	$row=$wpdb->get_row($query, ARRAY_A);
	
	echo "<h2>".__('Languages','zd_multilang').'</h2><br />';
	echo "<h3>".__('Edit Language','zd_multi').' <em>'.$_GET['lang'].'</em></h3>';
	echo "\n\t".'<form action="'.$BaseURL.'" method="post">';
	echo "\n\t".'<input type="hidden" name="lang_code" value="'.$Code.'" />';
	echo "\n\t".'<input type="hidden" name="'.$hidden_field.'" value="edit" />';
	echo "\n\t".'<input type="hidden" name="fct" value="edit" />';
	echo "\n\t".'<table>';
	echo "\n\t\t<tr><td>".__('Language Name','zd_multilang')."</td><td>";
		echo '<input type="text" name="lang_name" value="'.$row['LanguageName'].'">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Language Permalink','zd_multilang')."</td><td>";
		echo '<input type="text" name="lang_permalink" value="'.$row['LangPermalink'].'">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Blog name','zd_multilang')."</td><td>";
		echo '<input type="text" name="blog_name" value="'.$row['BlogName'].'">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Blog description','zd_multilang')."</td><td>";
		echo '<input type="text" name="blog_description" value="'.$row['BlogDescription'].'">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Default Language','zd_multilang')." ?</td><td>";
		if ($row['LanguageID']==$DefaultLanguage) $selected='checked="on"';
		else $selected="";
		echo '<input type="checkbox" name="def_lang" '.$selected.'>';
	echo "</td></tr>";
	echo "\n\t".'</table>';
	echo "\n\t".'<p class="submit"><input class="button" type="submit" value="'.__('Edit Language','zd_multilang').'" name="submit"/></p>';
	echo "\n\t</form>";
}

function zd_multilang_languages() {
	global $ZdmlCache;
	global $wpdb, $BaseURL,$default_language_option, $BaseURL, $PluginDIR, $wp_rewrite;
	$language_table= $wpdb->prefix.'zd_ml_langs';

	$hidden_field="zd_multilang_add_language";

	echo "<h2>".__('Languages','zd_multilang').'</h2><br />';

	if ($_POST[$hidden_field]) {
		$Action=$_POST[$hidden_field];
		$LangCode=$_POST['lang_code'];
		$LangName=$_POST['lang_name'];
		$LangPermalink=$_POST['lang_permalink'];
		echo '<div id="message" class="updated fade"><p>';
		if ($Action=="add") {
			$query="INSERT INTO $language_table VALUES ('$LangCode','$LangName','$LangPermalink',NULL,NULL)";
			$wpdb->query($query);
			if ($_POST['def_lang']=="on") update_option($default_language_option,$LangCode);
			echo __('Language Added','zd_multilang');
		}
		echo '</p></div><br />';
	}

	$DefaultLanguage=$ZdmlCache['DefLang'];
	$query="SELECT * FROM $language_table order by LanguageName";
	$Languages=$wpdb->get_results($query, ARRAY_A);
	if ($Languages) {
		echo '<table class="widefat">';
		echo '<tr style="background: #E4F2FD";><th>'.__('Action','zd_multilang').'</th><th>'.__('Language Name','zd_multilang').'</th><th>'.__('Language Code','zd_multilang').'</th><th>'.__('Permalink','zd_multilang').'</th><th>'.__('Default','zd_multilang').'</th></tr>';
		foreach ($Languages as $Index => $Values) {
			echo '<tr>';
				echo '<td><span class="edit_language"><a href="'.$BaseURL.'&amp;fct=edit&amp;lang='.$Values['LanguageID'].'">'.__('Edit','zd_multilang').'</a>&nbsp;-&nbsp;<a href="'.$BaseURL.'&amp;fct=delete&amp;lang='.$Values['LanguageID'].'">'.__('Delete','zd_multilang').'</a></span></td>';
				echo '<td><img src="'.$PluginDIR.'/flags/'.$Values['LanguageID'].'.png">&nbsp;'.$Values['LanguageName'].'</td>';
				echo '<td>'.$Values['LanguageID'].'</td>';
				echo '<td>'.$Values['LangPermalink'].'</td>';
				echo '<td>&nbsp;';
					if ($Values['LanguageID']==$DefaultLanguage) echo "<strong>".__('Default Language', 'zd_multilang')."</strong>";
				echo '</td>';
			echo '</tr>';
		}
		echo '</table>';
	}
	
	echo "<div id=\"form\" style=\"float: left; margin-right: 30px;\"><h3>".__('Add Language','zd_multilang').'</h3>';
	echo "\n\t".'<form action="'.$BaseURL.'" method="post">';
	echo "\n\t".'<input type="hidden" name="'.$hidden_field.'" value="add" />';
	echo "\n\t".'<input type="hidden" name="fct" value="languages" />';
	echo "\n\t".'<table>';
	echo "\n\t\t<tr><td>".__('Language Name','zd_multilang')."</td><td>";
		echo '<input type="text" name="lang_name" id="lang_name">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Language Code','zd_multilang')."</td><td>";
		echo '<input type="text" name="lang_code" id="lang_code">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Language Permalink','zd_multilang')."</td><td>";
		echo '<input type="text" name="lang_permalink" id="lang_permalink">';
	echo "</td></tr>";
	echo "\n\t\t<tr><td>".__('Default Language','zd_multilang')." ?</td><td>";
		echo '<input type="checkbox" name="def_lang">';
	echo "</td></tr>";
	echo "\n\t".'</table>';
	echo "\n\t".'<p class="submit"><input class="button" type="submit" value="'.__('Add Language','zd_multilang').'" name="submit"/></p>';
	echo "\n\t</form></div><p><a href=\"#\" onclick=\"jQuery('div#help').toggle();\">".__('Show/Hide Available default codes','zd_multilang')."</a></p>";
	
	$DefaultLanguagesCodes=array (
		'ar' => array ('Arabian', 'ar'),'bn_BD' => array ('Bengali','bn'),'be_BY' => array ('Belarusian','be'),
		'bg_BG' => array ('Bulgarian','bg'),'ca' => array ('Catalan','ca'),'zh_CN' => array ('Chinese','cn'),
		'zh_HK' => array ('Hong Kong','hk'),'zh_TW' => array ('Taiwan','tw'),'hr' => array ('Croatian','hr'),
		'cz_CZ' => array ('Czech','cz'),'da_DK' => array ('Danish','dk'),'nl_NL' => array ('Dutch','nl'),
		'en_US' => array ('English','en'),'eu' => array ('Euskadi','eu'),'eo' => array ('Esperanto','eo'),
		'et' => array ('Estonian','et'),'fo' => array ('Faroe','fo'),'fi_FI' => array ('Finnish','fi'),
		'fr_FR' => array ('French','fr'),'gl_ES' => array ('Galician','gl'),'de_DE' => array ('German','de'),
		'el' => array ('Greek','gr'),'he_IL' => array ('Hebrew','il'),'hu_HU' => array ('Hungarian','hu'),
		'is_IS' => array ('Icelandic','is'),'id_ID' => array ('Indonesian','id'),'it_IT' => array ('Italian','it'),
		'ja' => array ('Japanese','jp'),'km_KH' => array ('Khmer','km'),'ko_KR' => array ('Korean','ko'),
		'ku' => array ('Kurdish','ku'),'lv' => array ('Latvian','lv'),'lt' => array ('Lithuanian','lt'),
		'mk_MK' => array ('Macedonian','mk'),'mg_MG' => array ('Malgasy','mg'),'ms_MY' => array ('Malay','my'),
		'nb_NO' => array ('Norwegian','no'),'pl_PL' => array ('Polish','pl'),'pt_BR' => array ('Brazilian Portuguese','br'),
		'pt_PT' => array ('European Portuguese','pt'),'ro' => array ('Romanian','ro'),'ru_RU' => array ('Russian','ru'),
		'sr_RS' => array ('Serbian','sr'),'si_LK' => array ('Sinhala','lk'),'sl_SI' => array ('Slovenian','sl'),
		'sk' => array ('Slovak','sk'),'es_ES' => array ('Spanish','es'),'sv_SE' => array ('Swedish','se'),
		'th' => array ('Thai','th'),'tr' => array ('Turkish','tr'),'ua_UA' => array ('Ukrainian','ua'),
		'uz_UZ' => array ('Uzbek','uz'),'vi_VN' => array ('Vietnamse','vn'),'cy' => array ('Welsh','cy')
	);
	
	echo '<div id="help" style="height: 300px; overflow: auto !important; display: none;">
	<table>
		<tr><th width="16">Flag</th><th width="200">'.__('Language Name','zd_multilang').'</th><th>'.__('Language Permalink','zd_multilang').'</th><th>'.__('Language Code','zd_multilang').'</th></tr>';
	foreach ($DefaultLanguagesCodes as $lang_code => $v) {
		echo '<tr><td><img src="'.$PluginDIR.'/flags/'.$lang_code.'.png" alt="'.$v[0].'" title="'.$v[0].'" onclick="zdml_fillInfos(\''.$v[0].'\',\''.$lang_code.'\',\''.$v[1].'\');"/></td><td><a href="javascript:zdml_fillInfos(\''.$v[0].'\',\''.$lang_code.'\',\''.$v[1].'\')">'.$v[0].'</a></td><td>'.$v[1].'</td><td>'.$lang_code.'</td></tr>';
	}
	echo '		</table>
</div>
<script type="text/javascript">
	function zdml_fillInfos(name, code, permalink) {
		document.getElementById("lang_name").value=name;
		document.getElementById("lang_code").value=code;
		document.getElementById("lang_permalink").value=permalink;
	}
</script>';
	
	$wp_rewrite->flush_rules();
}

function zd_multilang_delete_language() {
	global $ZdmlCache;
	global $BaseURL, $wpdb,$default_language_option;
	$DefaultLanguage=$ZdmlCache['DefLang'];

	$language_table= $wpdb->prefix.'zd_ml_langs';$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$LangCode=$_GET['lang'];
	$query="DELETE FROM $language_table WHERE LanguageID='$LangCode'";
	$wpdb->query($query);
	$query="DELETE FROM $termtrans_table WHERE LanguageID='$LangCode'";
	$wpdb->query($query);
	
	echo '<br /><div id="message" class="updated fade"><p>';
	echo __('Language deleted','zd_multilang');
	echo '</p></div>';
	zd_multilang_languages();
}

function zd_multilang_post_translations() {
	global $ZdmlCache;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR, $display_original_option;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$posttrans=$wpdb->prefix.'zd_ml_trans';
	$hidden_field="zd_multilang_edit_translation";
	
	$Autosave=get_option('zdmultilang_autosave');

	echo "<h2>".__('Posts & Pages','zd_multilang').'</h2>';
	
	$query="SELECT * FROM $language_table order by LanguageName";
	$Languages=$wpdb->get_results($query, ARRAY_A);
	if (!$Languages) {
		echo '<p>'.__('No languages defined, please define some first','zd_multilang').'</p>';
		return;
	}
	$DefaultLanguage=$ZdmlCache['DefLang'];
	
	if ($_POST['id']) {
		list($ID, $Lang) = split("\.",$_POST['id']);
		$Content=$_POST['content'];
		$Title=$_POST['post_title'];
		$Status=$_POST['post_status'];
		$Excerpt=$_POST['post_excerpt'];
		$var=$wpdb->get_var("SELECT ID from $posttrans WHERE ID='$ID' and LanguageID='$Lang'");
		if ($var==$ID) {
			$query="UPDATE $posttrans set post_content='$Content', post_title='$Title', post_status='$Status', post_excerpt='$Excerpt' WHERE ID='$ID' and LanguageID='$Lang'";
		} else $query="INSERT INTO $posttrans (`ID`, `LanguageID`, `post_content`, `post_title`, `post_status`, `post_excerpt`) values ('$ID', '$Lang', '$Content', '$Title','$Status', '$Excerpt')";
		$wpdb->query($query);
		echo '<div id="message" class="updated fade"><p>';
		echo __('Post or Page updated','zd_multilang');
		echo '</p></div><br />';
		$_GET['id']=$_POST['id'];
	}
	if ($_GET['id']) {
		list($ID, $Lang) = split('\.', $_GET['id']);
		$query='SELECT * FROM  '.$wpdb->prefix.'posts WHERE ID='.$ID;
		$res=$wpdb->get_results($query);
		$OriginalText=str_replace(array("\r","\n"),array ("",""), strip_tags($res[0]->post_content));
		$OriginalPost=$res[0]->post_content;
		echo '<div id="poststuff">';
		$query="SELECT * FROM $posttrans WHERE LanguageID='$Lang' and ID=".$ID;
		$res=$wpdb->get_results($query, ARRAY_A);
		
		if ($res[0]['post_status']==NULL) $res[0]['post_status']='draft';

		$From=array_search($DefaultLanguage,$ZdmlCache['Languages']);
		$To=array_search($Lang,$ZdmlCache['Languages']);

		echo '<form action="'.$BaseURL.'" method="post">';
		echo '<input type="hidden" name="fct" value="posts" />';
		echo '<input type="hidden" name="id" value="'.$_GET['id'].'" />';
		echo '<div id="poststuff" class="metabox-holder has-right-sidebar">';
		echo '<div id="side-info-column" class="inner-sidebar submitbox">
			<div id="side-sortables" class="meta-box-sortables ui-sortable" style="position: relative;">
				<div id="submitpost" class="postbox inside">
					<p><strong><label for="post_status">'.__('Translation Status','zd_multilang').'</label></strong></p>
					<p>
					<select tabindex="4" id="post_status" name="post_status">
					<option value="published"';if ($res[0]['post_status']=="published") echo ' selected="selected"';echo '>'.__('Published','zd_multilang').'</option>
					<option value="draft"';if ($res[0]['post_status']=="draft") echo ' selected="selected"';echo '>'.__('Draft','zd_multilang').'</option>
					</select>
					</p>
					<p id="autosave_status" style="display: none;"><strong>Post autosaved</strong></p>
					</div>
					<p class="submit">
					<input type="submit" class="button button-highlighted" tabindex="4" value="Save" id="save-post" name="save"/>
					<br class="clear"/>
					</p>
				<div class="side-info">
					<h5>'.__('Actions','zd_multilang').'</h5>
					<ul>
					<li><a href="post.php?action=edit&post='.$ID.'" target="_blank">'.__('See Original post','zd_multilang').'</a></li>
					<li><a href="http://translate.google.com/translate_t#'.$From.'|'.$To.'|'.addslashes(htmlentities($OriginalText,ENT_COMPAT,'UTF-8')).'" target="_blank">'.__('See translation in Google Translate','zd_multilang').'</a></li>
					</ul>
					<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="hosted_button_id" value="8262513">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_SM.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
				</div>
			</div>';
		echo '</div>';
		
			echo '<div id="post-body" class="has-sidebar">';
			echo '<div id="post-body-content" class="has-sidebar-content">';
				echo '<div id="titlediv"><h3>'.__('Title','zd_multilang').'</h3>';
					echo '<div id="titlewrap"><input id="title" type="text" autocomplete="off" tabindex="1" size="30" name="post_title" value="'.$res[0]['post_title'].'"></div>';
				echo '</div>';
				echo '<div id="';echo user_can_richedit() ? 'postdivrich' : 'postdiv';echo '" class="postarea">';
					echo '<h3>'.__('Translations','zd_multilang').'</h3>';
					echo '<input type="hidden" id="user-id" name="user_ID" value="'.$user_ID.'" />';
					the_editor($res[0]['post_content']);
				echo '</div>';
				echo '<div id="titlediv" style="margin-bottom: 0px;"><h3>'.__('Excerpt','zd_multilang').'</h3></div>
				<div id="tr_excerpt" class="postarea">
					<textarea id="post_excerpt" name="post_excerpt" style="width: 98%; height: 150px;">'.$res[0]['post_excerpt'].'</textarea>
				</div>';
				$DisplayOriginal=get_option($display_original_option);
				if ($DisplayOriginal=='yes') {
					$OriginalPost = apply_filters('the_content', $OriginalPost);
					$OriginalPost = str_replace(']]>', ']]&gt;', $OriginalPost);
					echo '<div id="titlediv" style="margin-bottom: 0px;"><h3>'.__('Original post','zd_multilang').'</h3></div>
					<div style="border:1px solid #CCCCCC; padding: 5px;overflow: auto; height: 300px;">'.$OriginalPost.'</div>';
				}
			echo '</div>';
		echo '</div>';
		if ($Autosave=='yes') {
			wp_print_scripts('sack');
			echo '<script type="text/javascript">
		setTimeout(\'zdml_autosave();\',300000);
		function zdml_autosave() {
			content=tinyMCE.activeEditor.getContent();
			var mysack = new sack("'.get_bloginfo( 'wpurl' ).'/wp-admin/admin-ajax.php" );
			if (jQuery(\'#post_status\').val()=="published") return;
			mysack.execute = 1;
			mysack.method = \'POST\';
			mysack.setVar( "id", \''.$_GET['id'].'\' );
			mysack.setVar( "content", content );
			mysack.setVar( "action", "zdmultilang_autosave" );
			mysack.setVar( "post_title", jQuery(\'#title\').val() );
			mysack.setVar( "post_status", jQuery(\'#post_status\').val() );
			mysack.setVar( "post_excerpt", jQuery(\'#post_excerpt\').val() );
			mysack.onError = function() { alert(\'Ajax error in outlink collection\' )};
			mysack.runAJAX();
			setTimeout(\'zdml_autosave();\',300000);
		}
	</script>';
		}
		echo '<br class="clear" />
		</div>';
		echo '</form>';
	} else if ($_GET['tr']) {
		switch ($_GET['tr']) {
			case 'posts':
				$query='SELECT * FROM '.$wpdb->prefix.'posts WHERE post_type="post" order by post_status, post_date desc';
				break;
			case 'pages':
				$query='SELECT * FROM '.$wpdb->prefix.'posts WHERE post_type="page" order by post_status, post_date desc';
				break;
			default:
				$query="";
				break;
		}
		
		$q="SELECT * from $posttrans order by ID";
		$Translated=$wpdb->get_results($q, ARRAY_A);
		if ($Translated) foreach ($Translated as $Idx => $Row) {
			$Posts[$Row['ID']][$Row['LanguageID']]=$Row['post_status'];
		}
		
		$results=$wpdb->get_results($query, ARRAY_A);
		if ($results) {
			echo '<table class="widefat">';
			echo '<tr style="background: rgb(228, 242, 253);"><th>'.__('Original title','zd_multilang').'</th><th>'.__('Published date', 'zd_multilang').'</th>';
			foreach ($Languages as $Lang) {
					if ($Lang['LanguageID']!=$DefaultLanguage) echo '<td><img src="'.$PluginDIR.'/flags/'.$Lang['LanguageID'].'.png" alt="'.$Lang['LanguageName'].'"></td>';
				}
			echo '</tr>';
			foreach ($results as $ID => $row) {
				echo '<tr>';
				echo '<td><a href="post.php?action=edit&post='.$row['ID'].'">'.$row['post_title'].'</a></td>';
				echo '<td>'.date_i18n(get_option('date_format').' - '.get_option('time_format'),strtotime($row['post_date'])).'</td>';
				foreach ($Languages as $Lang) {
					if ($Lang['LanguageID']!=$DefaultLanguage) {
						echo '<td><a href="'.$BaseURL.'&amp;fct=posts&amp;id='.$row['ID'].'.'.$Lang['LanguageID'].'">';
						if ($Posts[$row['ID']][$Lang['LanguageID']]) echo '<img src="'.$PluginDIR.'/images/edit.png" style="vertical-align: middle;" /> '.__($Posts[$row['ID']][$Lang['LanguageID']],'zd_multilang');
						else echo '<img src="'.$PluginDIR.'/images/add.png" style="vertical-align: middle;" /> '.__('Translate','zd_multilang');
						echo '</a></td>';
					}
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	} else {
		$query="SELECT distinct(ID) from $posttrans";
		$res=$wpdb->get_results($query);
		foreach ($res as $Line) {
			$count++;
		}
		printf(__('%d posts or pages are translated','zd_multilang'),$count);
	}
}

function zd_multilang_autosave() {
	global $wpdb;
	$posttrans=$wpdb->prefix.'zd_ml_trans';
	list($ID, $Lang) = split("\.",$_POST['id']);
	$Content=$_POST['content'];
	$Title=$_POST['post_title'];
	$Status=$_POST['post_status'];
	$Excerpt=$_POST['post_excerpt'];
	$var=$wpdb->get_var("SELECT ID from $posttrans WHERE ID='$ID' and LanguageID='$Lang'");
	if ($var==$ID) {
		$query="UPDATE $posttrans set post_content='$Content', post_title='$Title', post_status='$Status', post_excerpt='$Excerpt' WHERE ID='$ID' and LanguageID='$Lang'";
	} else $query="INSERT INTO $posttrans (`ID`, `LanguageID`, `post_content`, `post_title`, `post_status`, `post_excerpt`) values ('$ID', '$Lang', '$Content', '$Title','$Status', '$Excerpt')";
	$wpdb->query($query);
	die ('jQuery(\'#autosave_status\').html("<strong>Transaltion has been automatically saved at '.strftime("%H:%M on %Y/%m/%d").'</strong>");
	jQuery(\'#autosave_status\').show();');
}

function zd_multilang_link_translations() {
	global $ZdmlCache, $locale;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$linktrans=$wpdb->prefix.'zd_ml_linktrans';
	$hidden_field="zd_multilang_edit_link";
	
	echo "<h2>".__('Links','zd_multilang').'</h2>';
	$DefLang=get_option($default_language_option);
	if (isset($_POST['link_id'])) {
		$_GET['link_id']=$_POST['link_id'];
		$url=$_POST['link_url'];
		$name=$_POST['link_name'];
		$description=$_POST['link_description'];
		$Language=$_POST['lng'];
		$link_id=$_POST['link_id'];
		$check="SELECT count(*) from $linktrans where link_id=$link_id and LanguageID='$Language'";
		$res=$wpdb->get_var($check);
		if ($res) $query="UPDATE $linktrans set link_name='$name', link_url='$url', link_description='$description' WHERE link_id=$link_id and LanguageID='$Language'";
		else $query="INSERT INTO $linktrans VALUES ($link_id, '$Language', '$url', '$name', '$description')";
		$wpdb->query($query);
	}
	if (isset($_GET['link_id'])) {
		$Language=$_GET['lng'];
		$link_id=$_GET['link_id'];
		$query="SELECT l.link_id, lt.link_url, lt.link_name, lt.link_description, lt.LanguageID, l.link_url o_url, l.link_name o_name, l.link_description o_desc FROM $linktrans lt RIGHT JOIN $wpdb->links l on (l.link_id=lt.link_id) where l.link_id=$link_id";
		$results=$wpdb->get_results($query);
		$found=0;
		if ($results) {
			foreach ($results as $Link) {
				if ($Link->LanguageID==$Language) {
					$found=1;
					break;
				}
			}
			if (!$found) {
				$Link->link_name="";
				$Link->link_url="";
				$Link->link_description="";
			}
			echo '<table class="widefat">
				<tr>
					<td><img src="'.$PluginDIR.'/flags/'.$DefLang.'.png" /></td>
					<td>'.$Link->o_name.'</td>
					<td>'.$Link->o_url.'</td>
					<td>'.$Link->o_desc.'</td>
				</tr>
				</table>';
				echo '<form action="" method="post">
				<input type="hidden" name="link_id" value="'.$link_id.'" />
				<input type="hidden" name="lng" value="'.$Language.'" />
				<label for="link_name"><img src="'.$PluginDIR.'/flags/'.$Language.'.png" /> '.__('Name','zd_multilang').'</label><input type="text" id="link_name" name="link_name" value="'.$Link->link_name.'" />
				<label for="link_url"><img src="'.$PluginDIR.'/flags/'.$Language.'.png" /> '.__('URL','zd_multilang').'</label><input type="text" id="link_url" name="link_url" value="'.$Link->link_url.'" />
				<label for="link_description"><img src="'.$PluginDIR.'/flags/'.$Language.'.png" /> '.__('Description','zd_multilang').'</label><input type="text" id="link_description" name="link_description" value="'.$Link->link_description.'" />
				<p style="text-align: right;"><input type="submit" class="button" value="'.__('Translate','zd_multilang').'" /></p>
				</form>';
		}
	} else {
		$query="SELECT l.link_id, lt.LanguageID, lt.link_name, l.link_url o_url, l.link_name o_name FROM $linktrans lt RIGHT JOIN $wpdb->links l on (l.link_id=lt.link_id)";
		$results=$wpdb->get_results($query);
		if ($results) 
		foreach ($results as $row) {
			$Link[$row->link_id]['name_'.$row->LanguageID]=$row->link_name;
			$Link[$row->link_id]['o_name']=$row->o_name;
			$Link[$row->link_id]['o_url']=$row->o_url;
		}
		
		echo '<table class="widefat">';
		echo '<tr><th><img src="'.$PluginDIR.'/flags/'.$DefLang.'.png" /> '.__('Original Link','zd_multilang').'</th>';
		foreach ($ZdmlCache['Languages'] as $LanguageID => $Lang) {
			if ($Lang!=$DefLang) echo '<th><img src="'.$PluginDIR.'/flags/'.$Lang.'.png" /> '.$ZdmlCache['LanguageNames'][$LanguageID].'</th>';
		}
		echo '</tr>';
		if ($Link) 
		foreach ($Link as $link_id => $L) {
			echo '<tr>';
				echo '<td><a href="'.$L['o_url'].'" title="'.$L['o_url'].'">'.$L['o_name'].'</a></td>';
			foreach ($ZdmlCache['Languages'] as $LanguageID => $Lang) {
				if ($Lang!=$DefLang) echo '<td><a href="'.$BaseURL.'&amp;fct=links&amp;link_id='.$link_id.'&amp;lng='.$Lang.'"><img src="'.$PluginDIR.'/images/edit.png" style="vertical-align: middle;"/> '.__('Translate','zd_multilang').'</a></td>';
			}
			echo '</tr>';
		}
		echo '</table>';
	}
}

function zd_multilang_term_translations() {
	global $ZdmlCache;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$hidden_field="zd_multilang_edit_translation";

	echo "<h2>".__('Translations','zd_multilang').'</h2>';
	$Option=$_GET['tr'];
	$DefaultLanguage=$ZdmlCache['DefLang'];
	
	$query="SELECT * FROM $language_table order by LanguageName";
	$Languages=$wpdb->get_results($query, ARRAY_A);
	if (!$Languages) {
		echo '<p>'.__('No languages defined, please define some first','zd_multilang').'</p>';
		return;
	}
	
	if ($_POST[$hidden_field]=="update") {
		$termid=$_POST['term_id'];
		$Option=$_POST['tr'];
		foreach ($Languages as $Lang) {
			if ($Lang['LanguageID']!=$DefaultLanguage) {
				$LangID=$Lang['LanguageID'];
				$Translation=$_POST[$termid."_".$LangID];
				$Desc=$_POST['desc_'.$termid."_".$LangID];
				$query="SELECT name FROM $termtrans_table WHERE LanguageID='$LangID' and term_id='$termid' ";
				$oldvalue=$wpdb->get_var($query);
				if ($oldvalue) {
					$query="UPDATE $termtrans_table set name='$Translation', description='$Desc' where term_id=$termid and LanguageID='$LangID'";
				} else {
					if ($Translation) $query="INSERT INTO $termtrans_table VALUES ('$termid','$LangID','$Translation', '$Desc')";
				}
				$wpdb->query($query);
				$_GET['id']=$termid;
			}
		}
	}

	if ($_GET['id']) {
		$term_id=$_GET['id'];
		$query="SELECT t.name, tt.term_id, tt.description FROM ".$wpdb->prefix."term_taxonomy tt, ".$wpdb->prefix."terms t WHERE t.term_id=tt.term_id and t.term_id=".$term_id." order by name";
		$res=$wpdb->get_row($query, ARRAY_A);
		
		$query="SELECT * FROM $termtrans_table WHERE term_id=$term_id";
		$Trans=$wpdb->get_results($query, ARRAY_A);
		if ($Trans) foreach ($Trans as $Values) {
			$Translations[$Values['LanguageID']]['term']=$Values['name'];
			$Translations[$Values['LanguageID']]['desc']=$Values['description'];
		}
		
		echo '<form action="'.$BaseURL.'" method="post">';
		echo "\n\t".'<input type="hidden" name="'.$hidden_field.'" value="update" />';
		echo "\n\t".'<input type="hidden" name="fct" value="translations" />';
		echo "\n\t".'<input type="hidden" name="tr" value="'.$_GET['tr'].'" />';
		echo "\n\t".'<input type="hidden" name="term_id" value="'.$term_id.'" />';
		
		echo "\n\t".'<table style="width:100%">';
		echo '<tr><td><strong>'.__('Original Term','zd_multilang').'</strong></td><td><strong>'.$res['name'].'</strong></td><td><strong>'.$res['description'].'</strong></td></tr>';
		foreach ($Languages as $Lang) {
			if ($Lang['LanguageID']!=$DefaultLanguage) {
				echo '<tr>';
				echo '<td>'.$Lang['LanguageName'].'</td>';
				echo '<td><input type="text" name="'.$term_id.'_'.$Lang['LanguageID'].'" size="50" value="'.$Translations[$Lang['LanguageID']]['term'].'" /></td>';
				echo '<td><textarea name="desc_'.$term_id.'_'.$Lang['LanguageID'].'" rows="4" cols="50">'.$Translations[$Lang['LanguageID']]['desc'].'</textarea></td>';
				echo '</tr>';
			}
		}
		echo '</table>';
		echo "\n\t".'<p class="submit"><input class="button" type="submit" value="'.__('Update Translation','zd_multilang').'" name="submit"/></p>';
		echo "</form>";
	} else {
		switch ($Option) {
			default:
				$Option="cat";
			case 'cat':
				$query="SELECT t.name, tt.term_id FROM ".$wpdb->prefix."term_taxonomy tt, ".$wpdb->prefix."terms t WHERE t.term_id=tt.term_id and taxonomy='category' order by name";
				$Results=$wpdb->get_results($query, ARRAY_A);
				break;
			case 'tags':
				$query="SELECT t.name, tt.term_id FROM ".$wpdb->prefix."term_taxonomy tt, ".$wpdb->prefix."terms t WHERE t.term_id=tt.term_id and taxonomy='post_tag' order by name";
				$Results=$wpdb->get_results($query, ARRAY_A);
				break;
			case 'linkcat':
				$query="SELECT t.name, tt.term_id FROM ".$wpdb->prefix."term_taxonomy tt, ".$wpdb->prefix."terms t WHERE t.term_id=tt.term_id and taxonomy='link_category' order by name";
				$Results=$wpdb->get_results($query, ARRAY_A);
				break;
		}
		if ($Results) {
			foreach ($Results as $Line) {
				$in.=$Line['term_id'].",";
			}
			$in=substr($in, 0, -1);
			$query="SELECT * FROM $termtrans_table WHERE term_id in ($in)";
			$Trans=$wpdb->get_results($query, ARRAY_A);
			if ($Trans) foreach ($Trans as $Values) {
				$Translations[$Values["term_id"]][$Values['LanguageID']]=$Values['name'];
			}
			echo '<table class="widefat">';
			echo '<tr style="background: #E4F2FD";><td><img src="'.$PluginDIR.'/flags/'.$DefaultLanguage.'.png">&nbsp;'.__('Original Term','zd_multilang').'</td>';
			foreach ($Languages as $Lang) {
				if ($Lang['LanguageID']!=$DefaultLanguage) echo '<td><img src="'.$PluginDIR.'/flags/'.$Lang['LanguageID'].'.png">&nbsp;'.$Lang['LanguageName'].'</td>';
			}
			echo '</tr>';
			foreach ($Results as $Id => $Value) {
				$term_id=$Value['term_id'];
				echo '<tr><td><a href="'.$BaseURL.'&amp;fct=translations&amp;tr='.$Option.'&amp;id='.$term_id.'">'.$Value['name'].'</a></td>';
				foreach ($Languages as $Lang) {
					if ($Lang['LanguageID']!=$DefaultLanguage) echo '<td>'.$Translations[$term_id][$Lang['LanguageID']].'</td>';
				}
				echo '</tr>';
			}
			echo '</table>';
		}
	}
}

function zd_multilang_translate_term($args="", $taxonomy="") {
	global $ZdmlCache, $locale;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR,$CurrentLanguagePermalink,$CurrentLang;
	
	if ($ZdmlCache['DefLang']==$locale) return $args;

	if ($ZdmlCache['Terms']) {
		if ($ZdmlCache['Terms'][$args->term_id][$locale]) {
			$args->cat_name=$ZdmlCache['Terms'][$args->term_id][$locale]['name'];
			$args->name=$ZdmlCache['Terms'][$args->term_id][$locale]['name'];
			$args->description=$ZdmlCache['Terms'][$args->term_id][$locale]['description'];
		}
	}
	return $args;
}

function zd_multilang_translate_link_cat($terms="") {
	global $ZdmlCache, $locale;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR,$CurrentLanguagePermalink,$CurrentLang;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$DefaultLanguage=$ZdmlCache['DefLang'];
	
	if ($ZdmlCache['DefLang']==$locale) return $terms;

	$termes=explode("\n",$terms);
	$term_list="";
	foreach ($termes as $ID => $term) {
		preg_match_all("|(.*<a.*>)(.*)(</a>.*)|i", $term,$res);
		$t="";
		foreach ($ZdmlCache['Terms'] as $termid => $Values) {
			foreach ($Values as $LangID => $V) {
				if (($t=="")&&($LangID==$locale)&&($V['o_name']==$res[2][0])) {
					$t=$V['name'];
				}
			}
		}
		if ($t=="") $term_list.=$res[0][0]."\n";
		else $term_list.=$res[1][0].$t.$res[3][0]."\n";
		echo $res[3][0];
	}
	return $term_list."\n";
}

function zd_multilang_postlink($arg) {
	global $locale, $ZdmlCache, $wpdb;
	$posttrans=$wpdb->prefix.'zd_ml_trans';

	preg_match_all("|(.*<a.*>)(.*)(</a>.*)|ms",$arg,$res);
	$postname=$res[2][0];
	
	$query="SELECT pt.post_title FROM $posttrans pt, $wpdb->posts p WHERE pt.ID=p.ID and LanguageID='".$locale."' AND p.post_title='$postname' and pt.post_status='published'";
	$title=$wpdb->get_var($query);
	$link=$res[1][0];
	if ($title) return $link.$title.$res[3][0];
	return $arg;
}

function zd_multilang_translate_cat($term="") {
	global $ZdmlCache, $locale;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR,$CurrentLanguagePermalink,$CurrentLang;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$DefaultLanguage=$ZdmlCache['DefLang'];

	if ($ZdmlCache['DefLang']==$locale) return $term;
	
	if ($ZdmlCache['Terms']) {
		foreach ($ZdmlCache['Terms'] as $TermID => $V) {
			foreach ($ZdmlCache['Terms'][$TermID] as $Language => $V) {
				if (($Language==$locale)&&($V['o_name']==$term)) {
					return $V['name'];
				}
			}
		}
	}
	return $term;
}

function zd_multilang_cat($arg) {
	global $ZdmlCache, $locale;
	$termid=$arg->term_id;

	if (isset($ZdmlCache['Terms'][$termid][$locale])) {
		$arg->name=$ZdmlCache['Terms'][$termid][$locale]['name'];
		$arg->description=$ZdmlCache['Terms'][$termid][$locale]['description'];
		$arg->category_description=$ZdmlCache['Terms'][$termid][$locale]['description'];
	}
	return $arg;
}

function zd_multilang_cat_desc($arg, $arg2) {
	if (is_object($arg2)) {
		$cat=zd_multilang_cat($arg2);
		return $cat->description;
	}
	return $arg;
}

function zd_multilang_translate_bookmarks($links) {
	global $ZdmlCache, $locale;
	if (!is_admin()) {
		foreach ($links as $Idx => $Datas) {
			$termid=$Datas->term_taxonomy_id;
			$linkid=$Datas->link_id;
			if ($ZdmlCache['Terms'][$termid][$locale]['description']) $links[$Idx]->description=$ZdmlCache['Terms'][$termid][$locale]['description'];
			if ($ZdmlCache['Links'][$linkid][$locale]['description']) $links[$Idx]->link_description=$ZdmlCache['Links'][$linkid][$locale]['description'];
			if ($ZdmlCache['Links'][$linkid][$locale]['name']) $links[$Idx]->link_name=$ZdmlCache['Links'][$linkid][$locale]['name'];
			if ($ZdmlCache['Links'][$linkid][$locale]['url']) $links[$Idx]->link_url=$ZdmlCache['Links'][$linkid][$locale]['url'];
		}
	}
	return $links;
}

function zd_multilang_translate_list_bookmarks($bm) {
	global $ZdmlCache, $locale;
	$bookmarks=explode("\n",$bm);
	$bm_return=array();
	foreach ($bookmarks as $line) {
		if (preg_match_all('|(<h[0-9].*>)(.*)(</h[0-9]>)|U',$line,$res)) {
			$bm_return[]=$res[1][0].zd_multilang_translate_cat($res[2][0]).$res[3][0];
		} else {
			$bm_return[]=$line;
		}
	}
	return implode("\n",$bm_return);
}

function zd_multilang_translate_post($posts) {
	global $ZdmlCache;
	global $BaseURL, $wpdb,$default_language_option, $wp_query, $wp_rewrite,$insert_lang_switch_option,$CurrentLanguagePermalink,$CurrentLang;
	global $locale, $display_google_translate;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';
	$posttrans=$wpdb->prefix.'zd_ml_trans';

	$Lang = $CurrentLanguagePermalink;
	if ($Lang=="") $Language=$ZdmlCache['DefLang'];
	$Language=$CurrentLang;
	
	if ($ZdmlCache['DefLang']!=$CurrentLang) {
		foreach ($posts as $Idc => $Post) {
			$post_list.=$Post->ID.',';
		}
		$post_list=substr($post_list,0, -1);
		$query="SELECT post_content, post_title, ID, post_excerpt FROM $posttrans WHERE LanguageID='".$Language."' AND ID in (".$post_list.") AND post_status='published'";
		$rows=$wpdb->get_results($query, ARRAY_A);
		if ($rows) foreach ($rows as $Id => $P) {
			$row[$P['ID']]->post_content=$P['post_content'];
			$row[$P['ID']]->post_title=$P['post_title'];
			$row[$P['ID']]->post_excerpt=$P['post_excerpt'];
		}
	}
	
	foreach ($posts as $Idx => $Post) {
		$postid=$Post->ID;
		if ($row[$Post->ID]) {
			$posts[$Idx]->post_content=$row[$Post->ID]->post_content;
			$posts[$Idx]->post_excerpt=$row[$Post->ID]->post_excerpt;
			if ($ZdmlCache['SwitcherPosition']=="footer") $posts[$Idx]->post_content.=zd_multilang_lang_switcher($posts[$Idx]->ID);
			else $posts[$Idx]->post_content=zd_multilang_lang_switcher($posts[$Idx]->ID).$posts[$Idx]->post_content;
			$posts[$Idx]->post_title=$row[$Post->ID]->post_title;
		} else {
			if ((!zd_multilang_is_translated($postid,$Language))&&($ZdmlCache['DefLang']!=$locale)&&(get_option($display_google_translate)=="show")) {
				$posts[$Idx]->post_content="<p><a href=\"http://translate.google.com/translate?u=".urlencode(get_permalink($postid))."&hl=en&ie=UTF8&sl=".$ZdmlCache['DefLang']."&tl=".$CurrentLang."\">".__('Translate original post with Google Translate',"zd_multilang").'</a></p>'
					.$posts[$Idx]->post_content;
			}
			if ($ZdmlCache['SwitcherPosition']=="footer") $posts[$Idx]->post_content.=zd_multilang_lang_switcher($posts[$Idx]->ID);
			else $posts[$Idx]->post_content=zd_multilang_lang_switcher($posts[$Idx]->ID).$posts[$Idx]->post_content;
		}
		// always get the appropriate number of comments (otherwise it won't work with default language)
		$comment_count = zd_multilang_get_comment_count ($Post->ID, $Post);
		if ($comment_count !== false)
			$posts[$Idx]->comment_count = $comment_count;	
	}
	return $posts;
}

function zd_multilang_translate_tags($Tags) {
	global $ZdmlCache;
	global $BaseURL, $wpdb,$default_language_option,$PluginDIR,$CurrentLanguagePermalink,$CurrentLang;
	$language_table= $wpdb->prefix.'zd_ml_langs';
	$termtrans_table = $wpdb->prefix.'zd_ml_termtrans';

	$Lang = $CurrentLanguagePermalink;
	if ($Lang=="") $Language=$ZdmlCache['DefLang'];
	$Language=$CurrentLang;
	
	if ($ZdmlCache['DefLang']==$CurrentLang) return $Tags;

	foreach ($Tags as $ID => $args) {
		if ($ZdmlCache['Terms']) {
			foreach ($ZdmlCache['Terms'] as $TermID => $V) {
				if (($TermID==$args->term_id) and ($V['LanguageID']==$Language)) 
					$Tags[$ID]->name=$V['name'];
			}
		}
	}
	return $Tags;
}

function zd_multilang_lang_switcher($post_id) {
	global $ZdmlCache;
	global $wpdb,$wp_rewrite,$display_untranslated;
	global $insert_lang_switch_option,$insert_lang_switch_option,$show_flags_option,$show_languages_option,$lang_switcher_class_option,$permalink_default, $locale;
	
	if (get_option($insert_lang_switch_option)=="show") {
		$PluginDIR = get_bloginfo('wpurl').'/'.PLUGINDIR . '/' . dirname(plugin_basename (__FILE__));
		$language_table= $wpdb->prefix.'zd_ml_langs';

		$langswitch_class=get_option($lang_switcher_class_option);
		if ($langswitch_class) $class=' class="'.$langswitch_class.'"';
		$retour="<ul$class>";
		
		if (!isset($ZdmlCache['Languages'])) {
			$query="SELECT * FROM $language_table ORDER BY LanguageName";
			$results=$wpdb->get_results($query, ARRAY_A);
			if ($results) {
				foreach ($results as $ID => $V)
				$ZdmlCache['Languages'][$V['LangPermalink']]=$V['LanguageID'];
			}
		}
		 else {
			foreach ($ZdmlCache['Languages'] as $Permalink => $LangID) {
				$results[$i]['LangPermalink']=$Permalink;
				$results[$i]['LanguageID']=$LangID;
				$i++;
			}
		}		
		if ($results) {
			foreach ($results as $ID => $Lang) {
				$regexp.=$Lang['LangPermalink']."|";
			}
			$regexp=substr($regexp, 0, -1);
		}
		$QUERY=get_permalink($post_id);
		if ($results) foreach ($results as $ID => $row) {
			if (get_option($permalink_default)=="no") {
				$regexp="";
				if ($results) {
					foreach ($results as $ID => $Lang) {
						if ($Lang['LanguageID']!=$ZdmlCache['DefLang']) $regexp.=$Lang['LangPermalink']."|";
					}
					$regexp=substr($regexp, 0, -1);
				}
				if ($wp_rewrite->using_permalinks()) {
					if ($ZdmlCache['DefLang']==$row['LanguageID']) $QUERY=preg_replace("`".get_bloginfo('url')."\/($regexp)\/(.*)`U",get_bloginfo('url')."/${3}",$QUERY);
					else {
						if ($locale==$ZdmlCache['DefLang']) {
							$QUERY=preg_replace("`".get_bloginfo('url')."\/(.*)`U",get_bloginfo('url').'/'.$row['LangPermalink']."/${2}",$QUERY);
						} else $QUERY=preg_replace("`".get_bloginfo('url')."\/($regexp)\/(.*)`U",get_bloginfo('url').'/'.$row['LangPermalink']."/${3}",$QUERY);
					}
				} else {
					if ($ZdmlCache['DefLang']==$row['LanguageID']) $QUERY=preg_replace("`(".get_bloginfo('url')."\/.*)&amp;lang=($regexp)(.*)`U",'${1}'."${3}",$QUERY);
					else {
						if ($locale==$ZdmlCache['DefLang']) {
							$QUERY=preg_replace("`(".get_bloginfo('url')."\/.*)$`U",'${1}&amp;lang='.$row['LangPermalink'],$QUERY);
						} else $QUERY=preg_replace("`(".get_bloginfo('url')."\/.*lang=)($regexp)(.*)`U",'${1}'.$row['LangPermalink']."${3}",$QUERY);
					}
				}
			} else {		
				if ($wp_rewrite->using_permalinks()) {
					if ($QUERY!=get_bloginfo('url').'/') $QUERY=preg_replace("`".get_bloginfo('url')."\/($regexp)\/(.*)`U",get_bloginfo('url').'/'.$row['LangPermalink']."/${3}",$QUERY);
					else $QUERY.=$row['LangPermalink'].'/';
				} else {
					$QUERY=preg_replace("`(".get_bloginfo('url')."\/.*lang=)($regexp)(.*)`U",'${1}'.$row['LangPermalink']."${3}",$QUERY);
				}
			}
			if ((zd_multilang_is_translated($post_id,$row['LanguageID']))&&($locale!=$row['LanguageID'])) {
					$retour.="<li$class>";
					$retour.='<a href="'.$QUERY.'">';
					if (get_option($show_flags_option)=="show") $retour.='<img src="'.$PluginDIR.'/flags/'.$row['LanguageID'].'.png" alt="'.$ZdmlCache['LanguageNames'][$row['LangPermalink']].'" title="'.$ZdmlCache['LanguageNames'][$row['LangPermalink']].'" border="0">';
					if (get_option($show_languages_option)=="show") $retour.=$ZdmlCache['LanguageNames'][$row['LangPermalink']];
					$retour.='</a>';
					$retour.='</li>';
			}
		}
		$retour.='</ul>';
		return $retour;
	}
	return "";
}

/******** Short Circuit Options ********/
function zd_multilang_blogname($value) {
	$v=zd_multilang_option("blogname");
	if ($v) return $v;
	return $value;
}
function zd_multilang_blogdescription($value) {
	$v=zd_multilang_option("blogdescription");
	if ($v) return $v;
	return $value;	
}

function zd_multilang_option($optionname) {
	global $locale, $ZdmlCache;
	return $ZdmlCache['Options'][$locale][$optionname];
}


/******* Widgets *******/

function zd_multilang_menu($show_name=true, $show_flags=true) {
	global $wpdb,$wp_rewrite,$locale;     // ... MG - Oct. 27, 2008 - need locale for check below ...
	$PluginDIR = get_bloginfo('wpurl').'/'.PLUGINDIR . '/' . dirname(plugin_basename (__FILE__));
	$language_table= $wpdb->prefix.'zd_ml_langs';

	$query="SELECT * FROM $language_table ORDER BY LanguageName";
	$results=$wpdb->get_results($query, ARRAY_A);

	if ($results) {
		foreach ($results as $ID => $Lang) {
			$regexp.=$Lang['LangPermalink']."|";
		}
		$regexp=substr($regexp, 0, -1);
	}
	$retour="";
	if ($results) foreach ($results as $ID => $row) {
		if ($wp_rewrite->using_permalinks()) {   // ... MG - Oct. 27, 2008 - not on the home page when switching language - deal with it ...
			if ( is_front_page() && !is_paged() ) $QUERY=get_bloginfo('url').'/'.$row['LangPermalink'].'/';
			else $QUERY=str_replace('/'.substr($locale,0,2).'/', '/'.$row['LangPermalink'].'/', $_SERVER['REQUEST_URI']);
		} else {
			if ( is_front_page() && !is_paged() ) $QUERY=get_bloginfo('url').'/?lang='.$row['LangPermalink'];
			else $QUERY=str_replace('lang='.substr($locale,0,2), 'lang='.$row['LangPermalink'], $_SERVER['REQUEST_URI']);
		}
		if ( $locale != $row['LanguageID'] )  {// ... MG - Oct. 27, 2008 - no need to display the flag for active language ...
			$retour.='<li><a href="'.$QUERY.'">';
			if ($show_flags) $retour.='<img src="'.$PluginDIR.'/flags/'.$row['LanguageID'].'.png" border="0">';
			if ($show_name) $retour.='&nbsp;'.$row['LanguageName'];
			$retour.='</a></li>';
		}
	}
	return $retour;
}

function zd_multilang_widget($args) {
	global $ZdmlCache;
	global $wpdb,$wp_rewrite;
	global $insert_lang_switch_option,$insert_lang_switch_option,$show_flags_option,$show_languages_option,$lang_switcher_class_option,$permalink_default, $locale;
	$PluginDIR = get_bloginfo('wpurl').'/'.PLUGINDIR . '/' . dirname(plugin_basename (__FILE__));
	$language_table= $wpdb->prefix.'zd_ml_langs';

	extract($args);
	$WidgetTitle=get_option('zdml_widget_title');
	$WidgetOptions=get_option('zdml_widget_options');
	if (!$WidgetTitle) echo $before_widget.$before_title.__('Language','zd_multilang').$after_title;
	else echo $before_widget.$before_title.$WidgetTitle.$after_title;

	echo '<ul class="zd_multilang_list">';
	echo zd_multilang_menu($WidgetOptions,get_option('zdml_widget_options_flag'));
	echo '</ul>';

	echo $after_widget;
}

function zd_multilang_widget_options() {
	global $ZdmlCache;
	global $wpdb;
	if ((isset($_POST['zdml_widget_title']))||(isset($_POST['zdml_sname']))) {
		update_option('zdml_widget_title',$_POST['zdml_widget_title']);
		$Options=($_POST['zdml_sname']=='on') ? '1' : '0';
		update_option('zdml_widget_options',$Options);
		$Options=($_POST['zdml_sflag']=='on') ? '1' : '0';
		update_option('zdml_widget_options_flag',$Options);
	}	
	$WidgetTitle=get_option('zdml_widget_title');
	$WidgetOptions=(get_option('zdml_widget_options')==1)? 'checked="checked"': '';
	$WidgetFlagOptions=(get_option('zdml_widget_options_flag')==1)? 'checked="checked"': '';
	echo '<p><label for="zds_widget_title">'.__('Title').':<br /><input type="text" name="zdml_widget_title" value="'.$WidgetTitle.'" /></label></p>';
	echo '<p><label for="zdml_sname"><input type="checkbox" name="zdml_sname" id="zdml_sname" '.$WidgetOptions.' /> '.__('Display Languages Name','zd_multilang').'</label></p>';
	echo '<p><label for="zdml_sflag"><input type="checkbox" name="zdml_sflag" id="zdml_sflag" '.$WidgetFlagOptions.' /> '.__('Display Flag','zd_multilang').'</label></p>';
}


/** comments */
/** Author of this section Pau Sanchez **/
 
 /**
  * When a new comment is created, we should add it to the database, no matter if it has been approved or not
  */
 function zd_multilang_insert_comment ($comment_id, $comment) {
 	global $locale, $wpdb;
 	$commenttrans=$wpdb->prefix.'zd_ml_comments';
 
 	// escape comment pulled from DB
 	$post_id = $comment->comment_post_ID;
 
 	$wpdb->query( $wpdb->prepare("INSERT INTO $commenttrans (comment_id, post_id, LanguageID) VALUES (%d, %d, %s)", $comment_id, $post_id, $locale) );
 	return true;
 }
 
 /**
  * When the post is deleted, we should delete it from the database
  */
 function zd_multilang_delete_comment ($comment_id) {
 	global $locale, $wpdb;
 	$commenttrans=$wpdb->prefix.'zd_ml_comments';
 	$wpdb->query( $wpdb->prepare("DELETE FROM $commenttrans WHERE comment_ID = %d LIMIT 1", $comment_id) );
 	return true;
 }
 
 /**
  * Filter comments for the wp-content themes only (for the current language), not for the admin
  */
 function zd_multilang_comments_array ($comments, $post_id) 
 {
 	global $locale, $wpdb, $keep_separate_comments;
 
 	if (empty ($comments) || !is_array ($comments) || (get_option($keep_separate_comments) != 'yes'))
 		return $comments;
 
 	$commenttrans=$wpdb->prefix.'zd_ml_comments';
 
 	// get ALL comments for current post, no matter the language 
 	// this is to allow compability with old posts before installing the plugin
 	$query=$wpdb->prepare ("SELECT * FROM $commenttrans WHERE post_id = %d", $post_id);
 	$results=$wpdb->get_results($query, ARRAY_A);
 
 	// no i18n comments?
 	if ($results === NULL) 
 		return $comments;
 
 	// create a map of comment IDs written in current language
 	// and another list of comments written in any other language (it will make sense later)
 	$commentsInLocale		= array();
 	$commentsNotInLocale = array();
 	foreach ($results as $row) {
 		if (strcasecmp ($row['LanguageID'], $locale) == 0)
 			$commentsInLocale[] = $row ['comment_id'];
 		else
 			$commentsNotInLocale[] = $row ['comment_id'];
 	}
 
 	// now generate the new list of commets (please note that comments is an array of objects)
 	$oldComments = $comments;
 	$comments		= array ();
 
 	foreach ($oldComments as $comment) 
 	{
 		// if the comment is written in the current $locale OR we have no information
 		// about which language was used (not in our table), display comment
 		if (
 			in_array ($comment->comment_ID, $commentsInLocale) ||
 			!in_array ($comment->comment_ID, $commentsNotInLocale) // this makes the plugin compatible with old comments
 		)
		{
 			$comments [] = $comment;
 		}
 	}
 
 	// intersect posts in current language with comments provided
 	return $comments;
 }
 
 /**
  * This is an internal function, be careful to avoid calling this function if it's not needed
  * Returns the number of comments in current locale or FALSE if it could not retrieve the number of comments 
  * (only when keep_separate_comments = false and no $Post is passed)
  */
 function zd_multilang_get_comment_count ($post_id, $Post = NULL)
 {
 	global $locale, $wpdb, $keep_separate_comments;
 
 	// do not keep separate comments
 	if (get_option($keep_separate_comments) != 'yes') {
 		if ($Post !== NULL) {
 			return $Post->comment_count;
 		}
 
 		// in theory this should never happen
 		return false;
 	}
 
 	$commenttrans = $wpdb->prefix.'zd_ml_comments';
 
 	// check if it's the admin interface => in the admin interface show the actual number of comments (no matter the language)
 	$isAdminInterface = (strpos ($_SERVER['PHP_SELF'], '/wp-admin/') !== false);
 	if ($isAdminInterface) {
 		if ($Post === NULL) {
 			$Post = get_post ($post_id);
 			if ($Post === NULL)
 				return 0;
 		}
 		return $Post->comment_count;
 	}
 
 	// this is a bit tricky, it could be optimized, but will be clearer if left seprate
 	$comment_count = 0;
 
 	// 1st sum all comments in current language
 	$query=$wpdb->prepare ("SELECT COUNT(*) AS total FROM $commenttrans WHERE post_id = %d AND LanguageID = %s", $post_id, $locale);
 	$results=$wpdb->get_results($query, ARRAY_A);
 	if ($results !== NULL)
 		$comment_count += $results[0]['total'];
 
 	// 2nd sum all comments that are not available in any language
 	$query=$wpdb->prepare (
 		"SELECT COUNT(*) AS total FROM wp_comments AS c1 WHERE (comment_post_ID = %d) AND NOT EXISTS (SELECT * FROM wp_zd_ml_comments AS c2 WHERE c1.comment_ID = c2.comment_id)",
 		$post_id
 	);
 	$results=$wpdb->get_results($query, ARRAY_A);
 	if ($results !== NULL)
 		$comment_count += $results[0]['total'];
 
 	return $comment_count;
 }
 
 // filter that returns the appropriate number of comments (themes usually use comments_number function)
 function zd_multilang_get_comments_number($count)
 {
 	global $id;
 	$comment_count = zd_multilang_get_comment_count($id);
 	return ($comment_count === false ? $count : $comment_count);
 }
 
 /** misc */
 
 // function to return the current locale (to avoid using global variables)
 function zd_multilang_get_locale () {
 	global $locale;
 	return $locale;
 }

function zd_multilang_initwidget() {
	global $ZdmlCache;
	if ( !function_exists('register_sidebar_widget') || !function_exists('register_widget_control') )
		return;
	register_sidebar_widget(array('Zd Multilang','widgets'),'zd_multilang_widget');
	register_widget_control(array('Zd Multilang', 'widgets'), 'zd_multilang_widget_options');
}
add_action('widgets_init', 'zd_multilang_initwidget');

?>
