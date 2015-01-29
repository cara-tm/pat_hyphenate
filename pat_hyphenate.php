<?php
/**
 * @plugin:  phpHyphenator for Textpattern CMS
 * @author:  © Patrick LEFEVRE, all rights reserved. <patrick[dot]lefevre[at]gmail[dot]com
 * @link:    http://pat-hyphenate.cara-tm.com
 * @type:    Admin+Public
 * @prefs:   Has prefs
 * @order:   5
 * @version: 0.3.3
 * @license: GPLv2
*/


/* **************** Public side **************** */

/**
 * pat_hyphenate - public side: tag to be use instead of &lt;txp:body /&gt; or &lt;txp:excerpt /&gt;
 * @param  array  $atts  tag attributes	
 *
 */
function pat_hyphenate($atts) {

	global $thisarticle, $prefs;

	extract(lAtts(array(
		'content'	=> 'body',
		'lang'		=> $prefs['language'],
	), $atts));

	$text = $thisarticle[$content];

	if ( $content === 'body' || $content === 'excerpt')  {

		if ( preg_match('/^([a-z]{2}-[a-z]{2})$/', $lang) )
			$hyphenate_ready = false;

		if ( preg_match('/(^[<]).*(^[>])$/', $prefs['pat_hyp_exclude_tags']) )
			$hyphenate_ready = false;

		$hyphenate_ready = true;

		// Sets global variables for hyphenation() function.
		$GLOBALS['pat_hyp_language'] = $lang;
		$GLOBALS['pat_path_to_patterns'] = txpath.'/_hyphenator/patterns/';
		$GLOBALS['pat_hyp_dictionary'] = txpath.'/_hyphenator/dictionary-'.$lang.'.txt';

		// Get patterns.
		if(file_exists($GLOBALS['pat_path_to_patterns'] . $GLOBALS['pat_hyp_language'] . '.php')) {
			include($GLOBALS['pat_path_to_patterns'] . $GLOBALS['pat_hyp_language'] . '.php');
			$GLOBALS['pat_patterns'] = convert_patterns($patterns);
		}
		else
		{
			$GLOBALS['pat_patterns'] = array();
		}

		// Get dictionary.
		file_exists($GLOBALS['pat_hyp_dictionary']) ? $GLOBALS['pat_hyp_dictionary'] = file($GLOBALS['pat_hyp_dictionary']) : $GLOBALS['pat_hyp_dictionary'] = array();

		foreach($GLOBALS['pat_hyp_dictionary'] as $entry) {
			$entry = trim($entry);
			$GLOBALS['pat_hyp_dictionary words'][str_replace('-', '', mb_strtolower($entry))] = str_replace('-', $GLOBALS['pat_hyp_hyphen'], $entry);
		}

		$GLOBALS['pat_hyp_exclude_tags'] = explode(',', $prefs['pat_hyp_exclude_tags']);

	} else {
		// Debug mode warning.
		return trigger_error(gTxt('invalid_attribute_value', array('{name}' => 'content')), E_USER_WARNING);
	}

	return ( $hyphenate_ready ? hyphenation($text) : $text );

}

/*
	phpHyphenator 1.5
	Developed by yellowgreen designbüro
	PHP version of the JavaScript Hyphenator 10 by Matthias Nater

	Licensed under Creative Commons Attribution-Share Alike 2.5 Switzerland
	http://creativecommons.org/licenses/by-sa/2.5/ch/deed.en

	Associated pages:
	http://yellowgreen.de/soft-hyphenation-generator/
	http://yellowgreen.de/phphyphenator/
	http://www.dokuwiki.org/plugin:hyphenation

	Special thanks to:
	Dave Gööck (webvariants.de)
	Markus Birth (birth-online.de)
*/
	global $prefs;
	// Set encoding for hyphen signs.
	mb_internal_encoding('utf-8');
	

// FUNCTIONS

	// Convert patterns.
	function convert_patterns($patterns) {
		$patterns = mb_split(' ', $patterns);
		$new_patterns = array();
		for($i = 0; $i < count($patterns); ++$i) {
			$value = $patterns[$i];
			$new_patterns[preg_replace('/[0-9]/', '', $value)] = $value;
		}
		return $new_patterns;
	}

	// Split string to array.
	function mb_split_chars($string) {
		$strlen = mb_strlen($string);
		while($strlen) {
			$array[] = mb_substr($string, 0, 1, 'utf-8');
			$string = mb_substr($string, 1, $strlen, 'utf-8');
			$strlen = mb_strlen($string);
		}
		return $array;
	}

// HYPHENATION

	// Word hyphenation.
	function word_hyphenation($word) {
		// GET DATA
		pat_hyphenate_get_datas();
		if(mb_strlen($word) < $GLOBALS['charmin'])
			return $word;
		if(mb_strpos($word, $GLOBALS['pat_hyp_hyphen']) !== false)
			return $word;
		if(isset($GLOBALS['pat_hyp_dictionary words'][mb_strtolower($word)]))
			return $GLOBALS['pat_hyp_dictionary words'][mb_strtolower($word)];

		$text_word = '_' . $word . '_';
		$word_length = mb_strlen($text_word);
		$single_character = mb_split_chars($text_word);
		$text_word = mb_strtolower($text_word);
		$hyphenated_word = array();
		$numb3rs = array('0' => true, '1' => true, '2' => true, '3' => true, '4' => true, '5' => true, '6' => true, '7' => true, '8' => true, '9' => true);

		for($position = 0; $position <= ($word_length - $GLOBALS['charmin']); $position++) {
			$maxwins = min(($word_length - $position), $GLOBALS['charmax']);

			for($win = $GLOBALS['charmin']; $win <= $maxwins; ++$win) {
				if(isset($GLOBALS['pat_patterns'][mb_substr($text_word, $position, $win)])) {
					$pattern = $GLOBALS['pat_patterns'][mb_substr($text_word, $position, $win)];
					$digits = 1;
					$pattern_length = mb_strlen($pattern);

					for($i = 0; $i < $pattern_length; ++$i) {
						$char = $pattern[$i];
						if(isset($numb3rs[$char])) {
							$zero = ($i == 0) ? $position - 1 : $position + $i - $digits;
							if(!isset($hyphenated_word[$zero]) || $hyphenated_word[$zero] != $char) $hyphenated_word[$zero] = $char;
							$digits++;				
						}
					}
				}
			}
		}

		$inserted = 0;
		for($i = $GLOBALS['leftmin']; $i <= (mb_strlen($word) - $GLOBALS['rightmin']); ++$i) {
			if(isset($hyphenated_word[$i]) && $hyphenated_word[$i] % 2 != 0) {
				array_splice($single_character, $i + $inserted + 1, 0, $GLOBALS['pat_hyp_hyphen']);
				$inserted++;
			}
		}

		return implode('', array_slice($single_character, 1, -1));
	}

	// Text hyphenation.
	function hyphenation($text) {
		global $pat_hyp_exclude_tags; $word = ''; $tag = ''; $tag_jump = 0; $output = array();
		$word_boundaries = "<>\t\n\r\0\x0B !\"§$%&/()=?….,;:-–_„”«»‘’'/\\‹›()[]{}*+´`^|©℗®™℠¹²³";
		$text = $text . ' ';

		for($i = 0; $i < mb_strlen($text); $i++) {
			$char = mb_substr($text, $i, 1);
			if(mb_strpos($word_boundaries, $char) === false && $tag == '') {
				$word .= $char;
			} else {
				if($word != '') { $output[] = word_hyphenation($word); $word = ''; }
				if($tag != '' || $char == '<') $tag .= $char;
				if($tag != '' && $char == '>') {
					$tag_name = (mb_strpos($tag, ' ')) ? mb_substr($tag, 1, mb_strpos($tag, ' ') - 1) : mb_substr($tag, 1, mb_strpos($tag, '>') - 1);
					if($tag_jump == 0 && in_array(mb_strtolower($tag_name), $pat_hyp_exclude_tags)) { 
						$tag_jump = 1;
					} else if($tag_jump == 0 || mb_strtolower(mb_substr($tag, -mb_strlen($tag_name) - 3)) == '</' . mb_strtolower($tag_name) . '>') { 
						$output[] = $tag;
						$tag = '';
						$tag_jump = 0;
					} 
				}
				if($tag == '' && $char != '<' && $char != '>') $output[] = $char;
			}
		}

		$text = join($output);
		return substr($text, 0, strlen($text) - 1);
	}



/* **************** Admin side **************** */

if (@txpinterface == 'admin') {

	if (!version_compare(txp_version, '4.5.0', '>='))
		return 'Sorry, your Textpattern version isn\'t supported. Upgrade for the newest.';

	pat_hyphenate_init();
}

/**
 * Initiate all plugin variables.
 * @param
 */
function pat_hyphenate_get_datas()
{

		// Set defaults
		if(!isset($GLOBALS['pat_hyp_hyphen'])) $GLOBALS['pat_hyp_hyphen'] = $prefs['pat_hyp_hyphen'];
		if(!isset($GLOBALS['leftmin'])) $GLOBALS['leftmin'] = $prefs['pat_hyp_leftmin'];
		if(!isset($GLOBALS['rightmin'])) $GLOBALS['rightmin'] = $prefs['pat_hyp_rightmax'];
		if(!isset($GLOBALS['charmin'])) $GLOBALS['charmin'] = $prefs['pat_hyp_charmin'];
		if(!isset($GLOBALS['charmax'])) $GLOBALS['charmax'] = $prefs['pat_hyp_charmax'];
		if(!isset($GLOBALS['pat_hyp_exclude_tags'])) $GLOBALS['pat_hyp_exclude_tags'] = array('code', 'pre', 'script', 'style');

}


/**
 * Initiate all plugin functions.
 * @param
 */
function pat_hyphenate_init() {

	global $event, $pat_hyphenate_gTxt, $pat_hyphenate_plugin_status, $pat_hyphenate_url, $message, $ok;

	// Default plugin Textpack.
	$pat_hyphenate_gTxt = array(
		'pat_hyphenate' => 'Hyphen Dictionary',
		'pat_hyphenate_personal_dictionary' => 'Personal Hyphenation Dictionary',
		'pat_hyphenate_create_file_done' => 'The <b>dictionary-'.LANG.'.txt</b> file has been created! Please, reload this page.',
		'pat_hyphenate_dictionary_content' => 'Dictionary contents:',
		'pat_hyphenate_save' => 'Save changes',
		'pat_hyphenate_help' => 'One word per lines. Add hyphens (-) in each word where needed. <br /><a href="http://en.wikipedia.org/wiki/Hyphen" title=" More informations online " target="_blank">More about hyphens</a>.',
		'pat_hyphenate_dictionary_saved' => 'Dictionary contents correctly saved.',
		'pat_hyphenate_dictionary_exemple' => 'ex-am-ple',
		'pat_hyphenate_php_failure' => 'Sorry, your PHP version is\'nt supported.',
		'pat_hyphenate_new_version' => 'A new <a href="index.php?event=pat_hyphenate_options" title=" See details "><b>pat_hyphenate</b> version</a> is available!',
		'pat_hyphenate_download' => 'Download the',
		'pat_hyphenate_download_link_title' => 'latest version of <b>pat_hyphenate</b>',
		'pat_hyphenate_tooltip' => 'Download now',
		'pat_hyphenate_options' => 'Hyphenate (Options)',
		'pat_hyphenate_prefs_option' => 'Item',
		'pat_hyphenate_prefs_values' => 'Value',
		'pat_hyphenate_prefs_license_code' => 'Your Paypal transaction ID',
		'pat_hyp_transaction' => 'Your PayPal Transaction ID:',
		'pat_hyphenate_prefs_license_info' => 'Plugin license number to get updates',
		'pat_hyphenate_prefs_hyphen_sign' => 'Sign for hyphens',
		'pat_hyphenate_prefs_hyphen_sign_info' => 'Sign to use for hyphens',
		'pat_hyp_hyphen' => 'Character for hyphens:',
		'pat_hyphenate_prefs_left_min' => 'Minimum of characters to keep on the left',
		'pat_hyp_leftmin' => 'Minimum characters on the left:',
		'pat_hyphenate_prefs_left_min_info' => 'Characters before an hyphen',
		'pat_hyphenate_prefs_right_max' => 'Maximum of characters on the right',
		'pat_hyp_rightmax' => 'Maximum of characters on the right:',
		'pat_hyphenate_prefs_right_max_info' => 'Characters after hyphens',
		'pat_hyphenate_prefs_char_min' => 'Minimum characters for hyphenation',
		'pat_hyp_charmin' => 'Minimum characters:',
		'pat_hyphenate_prefs_char_min_info' => 'Minimum characters to apply hyphens',
		'pat_hyphenate_prefs_char_max' => 'Maximum characters:',
		'pat_hyp_charmax' => 'Maximum characters:',
		'pat_hyphenate_prefs_char_max_info' => 'Maximum characters to stop hyphens',
		'pat_hyphenate_prefs_exclude' => 'Code to exclude from hyphenation',
		'pat_hyp_exclude_tags' => 'Code to exclude from hyphenation:',
		'pat_hyphenate_prefs_exclude_info' => 'Comma separated list of tags',
		'pat_textpack_fail' => 'Textpack installation failed',
		'pat_textpack_feedback' => 'Textpack feedback',
		'pat_textpack_online' => 'Textpack also available online',
		'pat_hyphenate_plugin_prefs' => '<a href="?event=plugin_prefs.pat_hyphenate#prefs" title=" Check plugin preferences ">Access to plugin preferences</a>',
		'pat_hyphenate_plugin_help_link' => '<a href="index.php?event=plugin&amp;step=plugin_help&amp;name=pat_hyphenate#prefs" title=" See ">More details</a>. <br /><a href="http://bit.ly/1grfJiZ" title=" See " target="_blank">Full documentation</a>.',
		);


	// Textpack's URLs with current back-office language.
	$pat_hyphenate_url = array(
		'textpack' => 'http://cara-tm.com/upload/textpack/pat_hyphenate_textpack-'.LANG.'.txt',
		// English base for translators
		'textpack_download' => 'http://cara-tm.com/upload/textpack/pat_hyphenate_textpack-en-gb',
		// Feedback website
		'textpack_feedback' => 'http://cara-tm.com/upload/textpack/feedback.php',
	);

	 // Privs for pat_hyphenate: Everyone can access.
	add_privs('pat_hyphenate_admin', '1, 2, 3, 4, 5, 6');

	// Privs for plugin prefs.
	add_privs('plugin_prefs.pat_hyphenate', '1, 2, 3');

	// Add tab under 'Content' menu.
	register_tab('content', 'pat_hyphenate_admin', pat_hyphenate_gTxt('pat_hyphenate'));
	register_callback('pat_hyphenate_admin', 'pat_hyphenate_admin');

	// Add tab under 'Extensions' menu.
	register_tab('extensions', 'pat_hyphenate_options', pat_hyphenate_gTxt('pat_hyphenate_options'));
	register_callback('pat_hyphenate_options', 'pat_hyphenate_options');

	 // Tab for pat_hyphenate_options: Everyone can access.
	add_privs('pat_hyphenate_options', '1, 2, 3, 4, 5, 6');

	// Lifecycle for installation
	register_callback('pat_hyphenate_prefs_lifecycle', 'plugin_lifecycle.pat_hyphenate');

	// Emit additional CSS rules for the admin side.
	if ($event == 'pat_hyphenate_options' || $event == 'pat_hyphenate_admin' || $event == 'plugin_prefs.pat_hyphenate')
	register_callback('pat_hyphenate_style', 'admin_side', 'head_end');

	if ( is_dir('_hyphenator') === false ) {
		$ok = false;
		$message = pat_hyphenate_gTxt('pat_hyphenate_open_directory_failure');
	} elseif ( file_exists('_hyphenator/dictionary-'.LANG.'.txt') === false ) {
		$ok = false;
		$message = pat_hyphenate_gTxt('pat_hyphenate_create_file_done');
	} else {
		$ok = true;
		$message = '';
	}

	// Plugin options.
	$pat_hyphenate_plugin_status = fetch('status', 'txp_plugin', 'name', 'pat_hyphenate');
	if ($pat_hyphenate_plugin_status) {
		// proper install - options under Plugins tab
		register_callback('plugin_prefs.pat_hyphenate', 'plugin_prefs.pat_hyphenate');
	}
	else
	{
		add_privs('pat_hyphenate_options', '1, 2, 3');
		register_tab('admin', 'pat_hyphenate_options', 'Hyphenate');
		register_callback('pat_hyphenate_options', 'pat_hyphenate_options');
		register_callback('_pat_hyphenate_prefs_setup', 'pat_hyphenate_options');
	}

	$pat_hyphenate_prefs = pat_hyphenate_load_prefs();
	add_privs('pat_hyphenate_prefs');
	register_callback('pat_hyphenate_prefs', 'plugin_prefs.pat_hyphenate');

}


/**
 * Dictionary plugin page.
 * @param   $event   $step   $rs
 */
function pat_hyphenate_admin($event, $step, $rs = '') {

	global $message, $ok;

	pat_hyphenate_verify_dictionary(LANG);

	// The tab content.

	// Thanks lot CEBE for this 'save' step ;)
	if ( $step == 'save' ) {
		// Get dictionary content.
		$dictionary = ps('dictionary');
		// Save dictionary new content accordingly to back-office's language.
		$rs = pat_hyphenate_add($dictionary, '_hyphenator/dictionary-'.LANG.'.txt');
		// Display a success message.
		$message = ( ps('pat_hyphenate_submit') ? pat_hyphenate_gTxt('pat_hyphenate_dictionary_saved') : '' );
	}

	// Generate the page.
	pagetop( pat_hyphenate_gTxt('pat_hyphenate_personal_dictionary' ), $message);

	if ( $ok ) {

		echo '<div id="pat-hyphenator-container">'
			.tag(pat_hyphenate_gTxt('pat_hyphenate_personal_dictionary').' ('.LANG.')', 'h1');

		// Load dictionary accordingly to back-office's language.
		$the_file = '_hyphenator/dictionary-'.LANG.'.txt';
		$dictionary = file_get_contents($the_file);

		// The write box.
		echo form(
			tag(
				inputLabel('personal_dictionary', '<textarea id="personal_dictionary" name="dictionary" cols="64" rows="8">'.$dictionary.'</textarea>', pat_hyphenate_gTxt('pat_hyphenate_dictionary_content'), '', '', '')
				.fInput('submit', 'pat_hyphenate_submit', gTxt('save'), 'publish smallerbox', pat_hyphenate_gtxt('pat_hyphenate_save'), '')
				.tag(pat_hyphenate_gTxt('pat_hyphenate_help').' | '.pat_hyphenate_gTxt('pat_hyphenate_plugin_prefs'), 'p', ' id="pat_hyphenate_help"')
				.eInput('pat_hyphenate_admin')
				.sInput('save')
				,'div'
			)
			,'div', 'verify(\''.gTxt('are_you_sure').'\')', 'post', 'pat_hyphenate_form'
		)
		// Notify users. But only admins can update.
		.graf( pat_hyphenate_check_version(pat_hyphenate_gTxt('pat_hyphenate_new_version')) , ' class="pat_hyphenate_center"').'</div>';
	}

}


/**
 * Check if file dictionary exists, otherwise create it.
 * @param   $lang
 */
function pat_hyphenate_verify_dictionary($lang) {

	// Dictionary file model for current language.
	$new_file = txpath.'/_hyphenator/dictionary-'.$lang.'.txt';

	// Check if present, otherwise create it.
	if ( is_file($new_file) === false ) {
		$target = (txpath.'/_hyphenator');
		$handle = fopen($new_file, 'w+b');
		chmod($new_file, 0777);
		fwrite($handle, gTxt('pat_hyphenate_dictionary_exemple')."\n");
		fclose($handle);
	}

}


/**
 * Add new entries into dictionary file.
 * @param   $dictionary   $target
 */
function pat_hyphenate_add($dictionary, $target) {

	// Function reviewed by CEBE. Tks.
	if ( function_exists('file_put_contents') && defined('LOCK_EX') ) {
		$flags = LOCK_EX;

		if ( file_put_contents($target, $dictionary, $flags) === false )
			$message = pat_hyphenate_gTxt('pat_hyphenate_open_file_failure');
		else
			$message = pat_hyphenate_gTxt('pat_hyphenate_dictionary_saved');

	} else {
		$message = pat_hyphenate_gTxt('pat_hyphenate_php_failure');
	}

	return $message;

}


/**
 * Plugin prefs page.
 * @param   $event   $step
 */
function pat_hyphenate_prefs($event, $step, $rs = '') {

	global $message, $plugins_ver, $ok;

	$link = tag(gTxt('edit'), 'a', ' href="index.php?event=prefs&step=advanced_prefs" class="publish navlink"');

	pagetop(gTxt('edit_preferences') . ' &#8250; pat_hyphenate', $message);
	$default_prefs = pat_hyphenate_defaults();

	// Inner content: table structure.
	$html = '
<table id="list" align="center" border="0" cellpadding="3" cellspacing="0">
<thead>
<tr>
<th colspan="2"><h1>pat_hyphenate v.' . $plugins_ver['pat_hyphenate'] .'. ' . gTxt('prefs') . '</h1></th>
</tr>
<tr class="line">
<th class="no-line">'.pat_hyphenate_gTxt('pat_hyphenate_prefs_option').'</th>
<th class="no-line center">'.pat_hyphenate_gTxt('pat_hyphenate_prefs_values').'</th>
</tr>
</thead>
<tbody>';

	// Loop for input elements.
	foreach ($default_prefs as $key => $pref) {
		$html .= '
<tr class="line">
<td><label for="'.$key.'">' . htmlspecialchars($pref['text']) . '</label> <br /><span><em>' . htmlspecialchars($pref['info']) . '</em></span></td>
<td class="center">' . fInput( 'text', $key, $pref['val'], '', '', '', '', '', $key, $pref['disabled'], $pref['required'] ) . '</td>
</tr>';
		}

		// End of the table element.
		$html .= n.'<tr class="no-line"><td><p>'.pat_hyphenate_gTxt('pat_hyphenate_plugin_help_link').'</p></td>'.n.'</tr>'.n.'</tbody>'.n.'</table>';

		// Put all things into a form.
		echo n.tag(
			    form(
				n.$html.n.
				tag(
					$link
					.eInput('plugin_prefs.pat_hyphenate')
					.sInput('_pat_hyphenate_prefs_update').n
					, 'div'
				)
				, '', '', 'post', 'pat_hyphenate_prefs_form'
			).n,
			'div', ' id="pat-hyphenate-prefs"');

		return;
}


/**
 * Content of prefs page.
 * @param   $values_only
 * @return  array()
 */
function pat_hyphenate_defaults($values_only = false) {

global $prefs;

	$defaults = array(
		'transaction' => array(
			'val'	   => (isset($prefs['pat_hyp_transaction']) ? $prefs['pat_hyp_transaction'] : 'XXXXXXXXXXXXXXXXX'),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_license_code'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_license_info'),
			'disabled' => true,
			'required' => false,
		),
		'hyphen' => array(
			'val'	   => (isset($prefs['pat_hyp_hyphen']) ? $prefs['pat_hyp_hyphen'] : '&shy;'),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_hyphen_sign'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_hyphen_sign_info'),
			'disabled' => true,
			'required' => true,
		),
		'leftmin' => array(
			'val'	   => pat_integer('pat_hyp_leftmin', '2'),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_left_min'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_left_min_info'),
			'disabled' => true,
			'required' => true,
		),
		'rightmax' => array(
			'val'	   => pat_integer('pat_hyp_rightmax', '2'),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_right_max'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_right_max_info'),
			'disabled' => true,
			'required' => true,
		),
		'charmin' => array(
			'val'	   => pat_integer('pat_hyp_charmin', '2'),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_char_min'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_char_min_info'),
			'disabled' => true,
			'required' => true,
		),
		'charmax' => array(
			'val'	   => pat_integer('pat_hyp_charmax', '10'),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_char_max'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_char_max_info'),
			'disabled' => true,
			'required' => true,
		),
		'exclude' => array(
			'val'	   => ( isset($prefs['pat_hyp_exclude_tags']) ? $prefs['pat_hyp_exclude_tags'] : 'pre, code, script, style' ),
			'html'	   => 'text_input',
			'text'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_exclude'),
			'info'	   => pat_hyphenate_gTxt('pat_hyphenate_prefs_exclude_info'),
			'disabled' => true,
			'required' => true,
		),
	);

	if ($values_only)
		foreach ($defaults as $name => $arr)
			$defaults[$name] = $arr['val'];
		return $defaults;
}

/**
 * Verify prefs values. Sets default if empty
 *
 * @param  $val $nb  Prefs entry  Default value
 * @return integer 
 */
function pat_integer($val, $nb)
{
	global $prefs;
	
	if ( isset($prefs[$val]) && is_int((int)$prefs[$val]) )
		$out = $prefs[$val];
	else
		$out = $nb;

	return $out;
}


/**
 * Prefs loader.
 * @param
 */
function pat_hyphenate_load_prefs() {

	return pat_hyphenate_defaults(true);

}


/**
 * Plugin options page.
 * @param   $event   $step
 */
function pat_hyphenate_options($event, $step) {

	global $prefs, $pat_hyphenate_url;

	$message = '';

	// Loop steps.
	if ($step == 'textpack') {
		$pat_textpack = file_get_contents($pat_hyphenate_url['textpack']);
		if ($pat_textpack) {
			$result = install_textpack($pat_textpack);
			$message = gTxt('textpack_strings_installed', array('{count}' => $result));
			$textarray = load_lang(LANG); // load in new strings
		}
		else
			$message = array( pat_hyphenate_gTxt('pat_textpack_fail'), E_ERROR );
	}

	// Generate page.
	pagetop('pat_hyphenate - '.gTxt('plugin_prefs'), $message);

	// Display options.
	echo tag(
		tag('pat_hyphenate '.gTxt('plugin_prefs'), 'h2')
		// Textpack links.
		.graf( href(gTxt('install_textpack'), '?event='.$event.'&amp;step=textpack', ' title=" '.gTxt('submit').' "') )
		// Textpack online preview.
		.graf( href(pat_hyphenate_gtxt('pat_textpack_online'), $pat_hyphenate_url['textpack_download'], ' title=" See " target="_blank"') )
		// Textpack feedback.
		.graf( href(pat_hyphenate_gTxt('pat_textpack_feedback'), $pat_hyphenate_url['textpack_feedback'], ' title=" Submit your feedback " target="_blank"') )
		// Admins can update if active.
		.graf( pat_hyphenate_check_version( pat_hyphenate_gTxt('pat_hyphenate_download'), $prefs['pat_hyp_transaction'] ), ' class="pat_hyphenate_highlight"' )
		// Plugin prefs access
		.graf( pat_hyphenate_gTxt('pat_hyphenate_plugin_prefs'), ' class="navlink"' )
		,'div'
		,' style="text-align:center"'
	);
}


/**
 * Plugin version compare from remote service.
 * @param
 */
function pat_hyphenate_define_version() {

	global $plugins_ver;

	// Remote service for upgrades.
	if ( @fopen('http://pat-hyphenate.cara-tm.com/version.ini', 'r') !== FALSE )
		define( 'PAT_HYPHENATE_REMOTE_VERSION', file_get_contents('http://pat-hyphenate.cara-tm.com/version.ini') );
	else
		define( 'PAT_HYPHENATE_REMOTE_VERSION', $plugins_ver );
}

ob_start('pat_hyphenate_define_version');


/**
 * Compare this plugin's version. Return a download Textpack link only if needed.
 * @param   $response   $pat_license
 * @return  String ()	String (Textpack link to upgrades remote service)
 */
function pat_hyphenate_check_version($response, $pat_license = NULL) {

	global $plugins_ver;

	if (has_privs('plugin_prefs.pat_hyphenate')) {
		$pat_hyphenate_remote_version = trim( PAT_HYPHENATE_REMOTE_VERSION );
		if ( version_compare( $plugins_ver['pat_hyphenate'], $pat_hyphenate_remote_version, '<' ) )
			return ( $pat_license ? $response.' '.href( gTxt('pat_hyphenate_download_link_title'), 'http://pat-hyphenate.cara-tm.com/decoder/?/'.$pat_license, ' title=" '.gTxt('pat_hyphenate_tooltip').' " target="_blank"' ) : '' );
		else
			return;
	}
}

ob_end_flush();


/**
 * Inject plugin styles into page.
 * @param   $event   $step
 */
function pat_hyphenate_style($event, $step) {

	// Display inline style properties according to new TXP page markup.
	echo <<<EOS

<style media="screen">
#pat-hyphenate-container{clear:both;margin-bottom:6em}
#page-pat_hyphenate_options .navlink a{text-decoration:none}
th.center{width:12em}
.pat_hyphenate_form{width:50%;margin:0 25% 2em}
.edit-personal-dictionary .txp-label{display:block;width:100%}
.pat_hyphenate_form label{display:block;margin:0 0 20px;cursor:pointer}
.pat_hyphenate_form input[type="submit"]{float:right;margin:.1em 0 0 0}
.pat_hyphenate_center{text-align:center}
.pat_hyphenate_highlight,.pat_hyphenate_highlight a{color:green;font-weight:bold}
.pat_hyphenate_highlight a{text-decoration:underline}
#list .line{border-bottom:1px solid #ddd}
#list thead .line{border-color:#000}
#list .no-line{border:none;line-height:3em}
#list .no-line p{line-height:1.2em}
#list td span{color:#aaa}
.center,.center input{width:100%;background:#fff;vertical-align:middle;text-align:center}
#pat-hyphenate-prefs{max-width:40em;margin:0 auto}
#pat-hyphenate-prefs table{width:100%;margin-bottom:.5em}
#pat-hyphenate-prefs td.center{padding:0 .5em 0 0}
#pat-hyphenate-prefs table label{display:inline-block;width:100%;cursor:pointer}
#pat-hyphenate-prefs .publish{display:block;float:right;min-width:8em;margin:-4em .5em 0 0;;text-align:center}
/* Visual effects */
#pat-hyphenate-prefs .center{width:13.5em;background-image:none;background:#f5f5f5}
#pat-hyphenate-prefs .line{background-image:-webkit-gradient(linear,left top,right top,color-stop(0.8,#fff),color-stop(1,#f5f5f5),color-stop(1,#fff));background-image:-moz-repeating-linear-gradient(left,#fff,#f5f5f5 80%,#fff);background-image:-o-repeating-linear-gradient(left,#fff,#f5f5f5 80%,#fff);background-image:linear-gradient(left,#fff,#f5f5f5 80%,#fff);line-height:1.6em}
</style>

EOS;

}


/**
 * i18n from adi_plugins. Tks ;)
 * @param   $phrase   $atts
 */
function pat_hyphenate_gTxt($phrase, $atts = array()) {
// will check installed language strings before embedded English strings - to pick up Textpack
// - for TXP standard strings gTxt() & pat_hyphenate_gTxt() are functionally equivalent
	global $pat_hyphenate_gTxt;

	if (strpos(gTxt($phrase, $atts), $phrase) !== FALSE) { // no TXP translation found
		if (array_key_exists($phrase, $pat_hyphenate_gTxt)) // translation found
			return strtr($pat_hyphenate_gTxt[$phrase], $atts);
		else // last resort
			return $phrase;
		}
	else // TXP translation
		return gTxt($phrase, $atts);
}


/**
 * Admin-side: plugin prefs creation or deletion.
 * @access private
 */
function pat_hyphenate_prefs_lifecycle($event, $step)
{
	if ($step == 'installed') {

		$prefs = array(
			  'pat_hyp_transaction' 	=> array( 'val' => 'XXXXXXXXXXXXXXXXX', 'html' => 'text_input' )
			, 'pat_hyp_hyphen' 		=> array( 'val' => '&shy;', 'html' => 'text_input' )
			, 'pat_hyp_leftmin' 		=> array( 'val' => '2', 'html' => 'text_input' )
			, 'pat_hyp_rightmax' 		=> array( 'val' => '2', 'html' => 'text_input' )
			, 'pat_hyp_charmin' 		=> array( 'val' => '2', 'html' => 'text_input' )
			, 'pat_hyp_charmax' 		=> array( 'val' => '10', 'html' => 'text_input' )
			, 'pat_hyp_exclude_tags'	=> array( 'val' => 'code, pre, script, style', 'html' => 'text_input' )
			);

		$sqlcmd = "INSERT INTO `" . safe_pfx( 'txp_prefs' )
                . "` (`prefs_id`, `name`, `val`, `type`, `event`, `html`, `position`) VALUES" ;
		$sqlopt = array() ;
		$position = 0 ;
		$type = 1 ;

		foreach( $prefs as $name => $vh ) {
			$sqlopt[] = " (1, '$name', '{$vh['val']}', '$type', 'pat_hyphenate', '{$vh['html']}', ".($position += 10).")" ;
		}

		if( $sqlopt ) {
			safe_query( $sqlcmd . join( ",", $sqlopt ) ) ;
		}

	} elseif ($step == 'deleted') {

		// Array of tables & rows to be removed
		$els = array('txp_prefs' => 'pat_hyp', 'txp_lang' => 'pat_hyphenate');

		// Process actions
		foreach ($els as $table => $row) {
			safe_delete($table, "name LIKE '".str_replace('_', '\_', $row)."\_%'");
			safe_repair($table);
		}

	}

      return;
}
