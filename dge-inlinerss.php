<?php
/*
Plugin Name: DGE_InlineRSS
Plugin URI: http://dev.wp-plugins.org/wiki/dge-inlinerss
Description: Allows inlcusion of RSS feeds, external html etc from any source in any format, and optionally transforms the feed via XSLT. Based on <a href="http://www.iconophobia.com/">inlineRSS</a> version 1.1 by Cal Demaine.
Version: 0.93
Author: Dave E
Author URI: http://dave.coolhandmook.com/
*/

/**
 * This is the body of the plugin. It does all the real work. It's the
 * method to call from other php functions.
 */
function DGE_InlineRSS($name, $url='', $options=array(), $xsltp=array())
{
    $maxage = 60 * 60 * 24; // Maximum file age is 1 day before an error is thrown

    error_reporting(E_ERROR);

    // Fetch some settings from the db
    $xsltpath = get_option('dge_irss_xsltpath');
    $cachepath = get_option('dge_irss_cachepath');
    $cacheprefix = get_option('dge_irss_cacheprefix');
    $processAsHTML = 0;

    // As a shortcut, when no url is passed in, use the name to
    // fetch a preset.
    if ($url == '')
	$presetname = $name;
    // otherwise, look for explicit preset call
    elseif (array_key_exists('preset', $options))
	$presetname = $options['preset'];

    if (isset($presetname))
    {
	if (($presets = get_option('dge_irss_presets')) &&
	    ($preset = $presets[$presetname]))
	{
	    if ($url == '') $url = $preset['url'];
	    $options = array_merge($preset['options'], $options);
 	    $xsltp = array_merge($preset['xsltp'], $xsltp);
	}
//	else
//	{
//	    return "<!-- Preset '$presetname' not found -->\n";
//	}
    }

    if (array_key_exists('timeout', $options))
	$timeout = $options['timeout'];
    else
	$timeout = get_option('dge_irss_def_timeout');
    if (array_key_exists('xslt', $options))
	$xslt = $options['xslt'];
    if (array_key_exists('xml', $options))
	$xml = $options['xml'];
    if (array_key_exists('html', $options))
	$processAsHTML = $options['html'];

    // ------------------------------------------------------------
    // Ok, ready to go.
    // ------------------------------------------------------------

    $cachefile = ABSPATH . "$cachepath/$cacheprefix$name.xml";

    if (strlen($xml) > 0)
    {
	$age = 0;
	$exists = TRUE;
    }
    else if (file_exists($cachefile))
    {
	// We have a local copy, get it just in case
	$xml = file_get_contents($cachefile);
	// And check its age
	$age = time() - filectime($cachefile);
	$exists = TRUE;
    }
    else
    {
	$age = 0;
	$exists = FALSE;
	$xml = "<!-- No valid XML -->";
    }

    if ( $exists == FALSE or $age > $timeout * 60 )
	// If there's no file, or it's old
    {
	// revised to now use CURL because, well, it's a tiny bit
	// safer.
	if (function_exists('curl_init') and stristr($url,"http:"))
	{
	    $curl_handle=curl_init();
	    curl_setopt($curl_handle,CURLOPT_URL,$url);
	    curl_setopt($curl_handle,CURLOPT_CONNECTTIMEOUT,10);
	    curl_setopt($curl_handle,CURLOPT_RETURNTRANSFER,1);
	    $curlxml = curl_exec($curl_handle);
	    curl_close($curl_handle);

	    if (empty($curlxml))
	    {
		if ($age > $maxage or $exists == FALSE)
		{
		    return "<!-- Error reading feed $name using curl. -->\n";
		}
		$writefile = FALSE;
	    }
	    else
	    {
		$xml = $curlxml;
		$writefile = TRUE;
	    }
	}
	else
	{
	    //  If CURL is giving you problems, use the line below instead.
	    $filegetxml = file_get_contents($url);
	    if (empty($filegetxml))
	    {
		if ($age > $maxage or $exists == FALSE)
		{
		    return "<!-- Error reading feed $name using file_get. -->\n";
		}
		$writefile = FALSE;
	    }
	    else
	    {
		$xml = $filegetxml;
		$writefile = TRUE;
	    }
	}

	if ($writefile)
	{
	    $handle = fopen($cachefile,'w');
	    if (!$handle)
	    {
		return "<!-- Error opening $cachefile - possible permissions issue - directory permissions are " . substr(sprintf('%o', fileperms(ABSPATH . $cachepath)), -4) . " -->\n";
	    }
	    fwrite($handle,$xml);
	    fclose($handle);
	}
    }

    if (strlen($xml) < 1)
    {
	return "<!-- XML feed $name is empty. -->\n";
    }

    if (!isset($xslt))
    {
	// Skip the XSLT processing, just bring in the file
	$xslt_result = $xml;
    }
    else
    {
	// Find XSLT file
	$xsltfile = dge_irss_findFile($xsltpath, $xslt);
	if ($xsltfile == '')
	    return "<!-- XSL file $xslt not found -->\n";

	// This is a switchboard to choose the proper XSLT
	// processing engine and grind through it.
	if (PHP_VERSION >= 5)
	{
	    $xsl = new DomDocument();
	    $xsl->load($xsltfile);

	    $xslt = new XsltProcessor();
	    $xslt->importStyleSheet($xsl);
	    $xslt->setParameter('',$xsltp);
	    if ($processAsHTML)
	    {
		$domresult = DomDocument::loadHTML($xml);
		if ($domresult == '')
		    $xslt_result .= "<!-- Failed to load HTML document -->";
	    }
	    else
	    {
		// First, try loading input as strict XML.
		$domresult = DomDocument::loadXML($xml);
		// If that fails, try a fallback to html.
		if ($domresult == '')
		{
		    $domresult = DomDocument::loadHTML($xml);
		    if ($domresult == '')
			$xslt_result .= "<!-- Not XML, and HTML fallack failed -->";
		}
	    }
	    $xslt_result .= $xslt->transformToXML($domresult);
	}
	else
	{
	    $xsl = file_get_contents($xsltfile);
	    if (!$xsl)
		return "<!-- Error reading XSL file $xsltfile -->\n";

	    if (function_exists('domxml_open_mem') &&
		function_exists('domxml_xslt_stylesheet'))
	{
	    // PHP 4 DOM_XML support
	    if (!$domXml = domxml_open_mem($xml))
	    {
		$result = "Error while parsing the xml document\n";
	    }
	    $domXsltObj = domxml_xslt_stylesheet( $xsl );
	    $domTranObj = $domXsltObj->process( $domXml, $xsltp );
	    $xslt_result = $domXsltObj->result_dump_mem( $domTranObj );
	}
	elseif (function_exists('xslt_create'))
	{
	    // PHP 4 XSLT library
	    $arguments = array ('/_xml' => $xml,
				'/_xsl' => $xsl);
	    $xslt_inst = xslt_create();
	    $xslt_result = xslt_process($xslt_inst,'arg:/_xml','arg:/_xsl', NULL, $arguments);
	    xslt_free($xslt_inst);
	}
	else
	{
	    // Nothing, no valid processor found.  Curses.
	    return "<!-- No valid XSLT processor found -->\n";
	}
	}
	if (empty($xslt_result))
	{
	    $xslt_result = "<!-- Horrific XSLT error - $name returned empty - verify that it's a valid XML file and check logs. -->\n";
	}
    } // End of Switch

    return $xslt_result . "<!-- Processed by DGE_InlineRSS ".get_option('dge_irss_version')." -->";
}

function dge_irss_findFile($path, $file)
{
    foreach (explode(';', $path) as $dir)
    {
	$f = ABSPATH . "$dir/$file";
	if (file_exists($f)) return $f;
    }
    return '';
}

// Pass in a string, an array will be returned.
function dge_irss_explodeParams($paramString)
{
    $params = array();
    foreach (explode(';', $paramString) as $param)
    {
	list($arg,$v) = explode('=', $param);
	$params[$arg] = $v;
    }
    return $params;
}

// Pass in an array, a string will be returned.
function dge_irss_implodeParams($params)
{
    $result = array();
    foreach ($params as $key=>$val)
    {
	if ($val == '') $result[]=$key;
	else $result[] = "$key=$val";
    }
    return implode(';', $result);
}

// This is a Wordpress content filter that replaces calls to DGE_InlineRSS
// Any entries like !inlineRSS:iconophobia will be replaced with the
// inlineRSS feed identified by "iconophobia".
function dge_irss_content ($content = '')
{
    $find[] = "//";
    $replace[] = "";

    preg_match_all('/!inlineRSS:(\w+)/', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $val)
    {
	$find[] = "/" . $val[0] . "/";
	$replace[] = DGE_InlineRSS($val[1]);
    }

    return preg_replace($find, $replace, $content);
}

// Filters out inline calls
function dge_irss_securityFilter($content = '')
{
    $find[] = "//";
    $replace[] = "";

    preg_match_all('/!inlineRSS:(\w+)/', $content, $matches, PREG_SET_ORDER);

    foreach ($matches as $val)
    {
	$find[] = "^$val[0]^";
	$replace[] = "<!-- inlineRSS call removed -->";
    }
    return preg_replace($find, $replace, $content);
}

function dge_irss_admin()
{
    if (function_exists('add_options_page'))
    {
	add_options_page('InlineRSS Options', 'InlineRSS', 8, basename(__FILE__), 'dge_irss_subpanel');
    }
}

function dge_irss_subpanel()
{
    $presets = get_option('dge_irss_presets');
    if (!$presets) $presets = array();

    // ----------------------------------------------------------------
    // PARSE $_POST PARAMETERS
    // ----------------------------------------------------------------
    if (isset($_POST['info_update']))
    {
	$updateText = '';
	// ------------------------------------------------------------
	// DEFAULTS
	// ------------------------------------------------------------
	if (isset($_POST['def_timeout']))
	{
	    $timeout = $_POST['def_timeout'];
	    if ($timeout == '')
	    {
		$updateText .= "<p><strong>Default timeout not updated. Invalid input.</strong></p>\n";
	    }
	    else
	    {
		$timeout = intval($timeout);
		if ($timeout != get_option('dge_irss_def_timeout'))
		{
		    update_option('dge_irss_def_timeout', $timeout);
		    $updateText .= "<p>Default timeout updated.</p>\n";
		}
	    }
	}
	// xslt path
	if (isset($_POST['xsltpath']))
	{
	    $xsltpath = $_POST['xsltpath'];
	    if ($xsltpath != get_option('dge_irss_xsltpath'))
	    {
		update_option('dge_irss_xsltpath', $xsltpath);
		$updateText .= "<p>XSLT path updated.</p>\n";
	    }
	}
	// cache path
	if (isset($_POST['cachepath']))
	{
	    $cachepath = $_POST['cachepath'];
	    if ($cachepath != get_option('dge_irss_cachepath'))
	    {
		update_option('dge_irss_cachepath', $cachepath);
		$updateText .= "<p>Cache path updated.</p>\n";
	    }
	}
	// cache prefix
	if (isset($_POST['cacheprefix']))
	{
	    $cacheprefix = $_POST['cacheprefix'];
	    if ($cacheprefix != get_option('dge_irss_cacheprefix'))
	    {
		update_option('dge_irss_cacheprefix', $cacheprefix);
		$updateText .= "<p>Cache file prefix updated.</p>\n";
	    }
	}
	// ------------------------------------------------------------
	// PRESETS
	// ------------------------------------------------------------
	$updatepresets = 0;
	// Check for new presets
	if ($_POST['pre_new_name']!='')
	{
	    $name = $_POST['pre_new_name'];
	    if (array_key_exists($name, $presets))
	    {
		$updateText .= "<p><strong>New preset '$name' already exists. Not updated.</strong></p>\n";
	    }
	    else
	    {
		$url = $_POST['pre_new_url'];
		$options = $_POST['pre_new_options'];
		$xsltp = $_POST['pre_new_xsltp'];
		if ($url != '')
		{
		    $presets[$name] = array();
		    $presets[$name]['url'] = $url;
		    $presets[$name]['options'] = dge_irss_explodeParams($options);
		    $presets[$name]['xsltp'] = dge_irss_explodeParams($xsltp);
		    $updateText .= "<p>New preset '$name' added.</p>\n";
		    $updatepresets = 1;
		}
		else
		{
		    $updateText .= "<p><b>New preset '$name' not added - no url provided.</b></p>\n";
		}
	    }
	}
	// Check for updates to existing presets.
	foreach ($presets as $name=>$preset)
	{
	    $urlKey = "pre_upd_url_".$name;
	    $url = $preset['url'];
	    $optionsKey = "pre_upd_opt_".$name;
	    $optionsStr = dge_irss_implodeParams($preset['options']);
	    $xsltpKey = "pre_upd_xsltp_".$name;
	    $xsltpStr = dge_irss_implodeParams($preset['xsltp']);
	    if ((array_key_exists($urlKey,$_POST) &&
		 $_POST[$urlKey] != $url) ||
		(array_key_exists($optionsKey,$_POST) &&
		 $_POST[$optionsKey] != $optionsStr) ||
		(array_key_exists($xsltpKey,$_POST) &&
		 $_POST[$xsltpKey] != $xsltpStr))
	    {
		$newurl = $_POST[$urlKey];
		$uoptions = $_POST[$optionsKey];
		$uxsltp = $_POST[$xsltpKey];
		if ($newurl == '' && $uoptions == '' && $uxsltp == '')
		{
		    unset($presets[$name]);
		    $updateText .= "<p>Preset '$name' removed.</p>\n";
		}
		else
		{
		    $presets[$name]['url'] = $newurl;
		    $presets[$name]['options'] = dge_irss_explodeParams($uoptions);
		    $presets[$name]['xsltp'] = dge_irss_explodeParams($uxsltp);
		    $updateText .= "<p>Preset '$name' updated.</p>\n";
		}
		$updatepresets = 1;
	    }
	}
	// Do all updates to the presets in one go.
	if ($updatepresets)
	{
	    update_option('dge_irss_presets', $presets);
	}
	// Output $updateText
	if ($updateText != '')
	    echo "<div class=\"updated\">\n$updateText</div>";
    }

    // ----------------------------------------------------------------
    // DISPLAY FORM
    // ----------------------------------------------------------------
 ?>
<div class="wrap">
  <form method="post">
    <h2>InlineRSS Options (v<?php echo get_option('dge_irss_version'); ?>)</h2>

    <h3>Setup</h3>
    <div><table>
    <tr><td>Timeout (mins)</td><td><input type="text" name="def_timeout" value="<?php echo get_option('dge_irss_def_timeout'); ?>"/></td><td><i>The default time to wait before refreshing the feed cache.</i></td></tr>
    <tr><td>Cache path</td><td><input type="text" name="cachepath" value="<?php echo get_option('dge_irss_cachepath'); ?>"/></td><td><i>The path to the cache dir. Must be writable by apache.<?php
     $cachepath = ABSPATH . get_option('dge_irss_cachepath');
     if (!file_exists($cachepath))
	 echo "<br/><span style=\"color:red\">$cachepath doesn't exist.</span>";
     else if (!is_dir($cachepath))
	 echo "<br/><span style=\"color:red\">$cachepath is not a directory.</span>";
     else if (!is_writeable($cachepath))
	 echo "<br/><span style=\"color:red\">$cachepath isn't writable.</span>";
?></i></td></tr>
    <tr><td>Cache prefix</td><td><input type="text" name="cacheprefix" value="<?php echo get_option('dge_irss_cacheprefix'); ?>"/></td><td><i>"Salt" prepended to every cache file. Consider making this a short, random string (e.g. 'aR3z5-'), but not essential.</i></td></tr>
    <tr><td>XSLT path</td><td><input type="text" name="xsltpath" value="<?php echo get_option('dge_irss_xsltpath'); ?>"/></td><td><i>Where to look for your XSLT files. Must be relative to your Wordpress install directory. Separate multiple directories with a semi-colon (';').</i></td></tr>
    </table></div>

    <h3>Presets</h3>
    <div>
<?php
if ($presets && count($presets)>0)
{
    echo "    <table>\n";
    echo "        <tr><td colspan=\"4\"><b>Existing Presets</b></td><tr>\n";
    echo "        <tr><td colspan=\"4\"><i>To remove an existing preset, delete the contents of all 3 fields.</i></td></tr>\n";
    echo "        <tr><td><b>Name</b></td><td><b>URL</b></td><td><b>Options</b></td><td><b>XSLT Parameters</b></td></tr>\n";
    foreach ($presets as $name=>$preset)
    {
	// NAME
	echo "      <tr><td>$name</td>";
	// URL
	echo "<td><input type=\"text\" name=\"pre_upd_url_$name\" size=\"50\" value=\"";
	echo $preset['url'] . "\" /></td>";
	// OPTIONS
	echo "<td><input type=\"text\" name=\"pre_upd_opt_$name\" size=\"25\" value=\"";
	echo dge_irss_implodeParams($preset['options']).'';
	// XSLT PARAMS
	echo "\"/></td><td><input type=\"text\" name=\"pre_upd_xsltp_$name\" size=\"25\" value=\"";
	echo dge_irss_implodeParams($preset['xsltp']);
	echo "\"/></td></tr>\n";
    }
    echo "</table>\n";
}
?>
        <table>
        <tr><td colspan="2"><b>Add preset</b></td><tr>
        <tr>
          <td>Name</td>
          <td><input type="text" name="pre_new_name"/></td>
	</tr>
        <tr>
          <td>URL</td>
          <td><input type="text" size="50" name="pre_new_url"/></td>
        </tr>
        <tr>
          <td>Options</td>
          <td><input type="text" size="50" name="pre_new_options"/></td>
        </tr>
        <tr>
          <td>XSLT Parameters</td>
          <td><input type="text" size="50" name="pre_new_xsltp"/></td>
        </tr>
    </table></div>
    <div class="submit">
      <input type="submit" name="info_update" value="Update options &raquo;" />
    </div>
  </form>
</div>
<?php
}

function dge_irss_activate()
{
    // This is version...
    $nversion = 0.93; // (n for new)
    // Get the previous version
    $pversion = floatval(get_option('dge_irss_version'));

    // Upgrade code between minor versions goes here.

    // Check for first-time install
    if ($pversion == 0)
    {
	add_option('dge_irss_version', $nversion, 'Version', 'no');
	add_option('dge_irss_cachepath', 'wp-content/cache/', 'Cache Path');
	add_option('dge_irss_cacheprefix', '', 'Cache file prefix');
	add_option('dge_irss_def_timeout', 60, 'Default cache refresh timeout');
    }
    else
	update_option('dge_irss_version', $nversion);
}

// This adds the filter to Wordpress.
add_filter('comment_author', 'dge_irss_securityFilter');
add_filter('comment_email', 'dge_irss_securityFilter');
add_filter('comment_text', 'dge_irss_securityFilter');
add_filter('comment_url', 'dge_irss_securityFilter');
add_filter('the_content', 'dge_irss_content');
add_action('admin_menu', 'dge_irss_admin');
add_action('activate_dge-inlinerss/dge-inlinerss.php', 'dge_irss_activate');

?>
