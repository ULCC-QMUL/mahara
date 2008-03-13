<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
    <head>
        <meta http-equiv="Content-type" content="text/html; charset=UTF-8">
        <title>{$PAGETITLE|escape}</title>
        <script type="text/javascript">
        var config = {literal}{{/literal}
            'theme': {$THEMELIST},
            'sesskey' : '{$SESSKEY}',
            'wwwroot': '{$WWWROOT}',
            'loggedin': {$USER->is_logged_in()|intval},
            'userid': {$USER->get('id')}
        {literal}}{/literal};
        </script>
        {$STRINGJS}
{foreach from=$JAVASCRIPT item=script}        <script type="text/javascript" src="{$script}"></script>
{/foreach}
{foreach from=$HEADERS item=header}        {$header}
{/foreach}
{if isset($INLINEJAVASCRIPT)}
        <script type="text/javascript">
{$INLINEJAVASCRIPT}
        </script>
{/if}
{foreach from=$STYLESHEETLIST item=cssurl}
        <link rel="stylesheet" type="text/css" href="{$cssurl}">
{/foreach}
        <link rel="stylesheet" type="text/css" href="{theme_path location='style/print.css'}" media="print">
    </head>
    <body>
    <div id="containerX">
        <div id="loading_box" style="display: none;"></div>
        <div id="topwrapper">
                <div id="logo"><a href="{$WWWROOT}"><img src="{theme_path location='images/logo.gif'}" border="0" alt=""></a></div>
                <h1 class="hidden"><a href="{$WWWROOT}">{$hiddenheading|default:"Mahara"|escape}</a></h1>
        </div>
        <div id="mainwrapper">
            {insert name="messages"}
            <div class="maincontent">
                {if $PAGEHELPNAME && $heading} <h2>{$heading|escape}<span id="{$PAGEHELPNAME}_container" class="pagehelpicon">{$PAGEHELPICON}</span></h2>{/if}
