<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


/**
 * Smarty {mahara_performance_info} function plugin
 *
 * Type:     function<br>
 * Name:     mahara_performance_info<br>
 * Date:     June 22, 2006<br>
 * Purpose:  Fetch internationalized strings
 * @author   Catalyst IT Ltd
 * @version  1.0
 * @param array
 * @param Smarty
 * @return html to display in the footer.
 */
function smarty_function_mahara_performance_info($params, &$smarty) {

    if (!get_config('perftofoot') && !get_config('perftolog')) {
        return;
    }

    $info = get_performance_info();

    $smarty = smarty_core();

    foreach ($info as $key => $value) {
        $smarty->assign('perf_' . $key, $value);
    }

    // extras
    $smarty->assign('perf_memory_total_display',  display_size($info['memory_total']));
    $smarty->assign('perf_memory_growth_display', display_size($info['memory_growth']));

    if (get_config('perftolog')) {
        perf_to_log($info);
    }

    if (get_config('perftofoot')) {
        return $smarty->fetch('performancefooter.tpl');
    }

}

?>
