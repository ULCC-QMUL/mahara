<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {list_tags} function plugin
 *
 * Type:     function<br>
 * Name:     str<br>
 * Date:     June 22, 2006<br>
 * Purpose:  Render a list of tags
 * @author   Richard Mansfield <richard.mansfield@catalyst.net.nz>
 * @author   Penny Leach <penny@mjollnir.org>
 * @version  1.0
 * @param array
 * @param Smarty
 * @return Internationalized string
 */
function Dwoo_Plugin_list_tags(Dwoo $dwoo, $tags, $owner) {
    global $USER;
    if (!is_array($tags)) {
        return '';
    }

    // Format the tags to display any defined
    // owner prefix if required.
    $formatted = array();
    foreach ($tags as $tag) {
        if (isset($tag->prefix) && !empty($tag->prefix)) {
            $formatted[] = $tag->prefix . ': '. $tag->tag;
        } else {
            $formatted[] = $tag->tag;
        }
    }

    if ($owner != $USER->get('id')) {
        return join(', ', array_map('hsc', $formatted));
    }

    foreach ($formatted as &$t) {
        $t = '<a class="tag" href="' . get_config('wwwroot') . 'tags.php?tag=' . urlencode($t) . '">' . hsc(str_shorten_text($t, 50)) . '</a>';
    }

    return join(', ', $formatted);
}

?>
