{**
 * summary.tpl
 *
 * Copyright (c) 2005-2007 Alec Smecher and John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 * * Edited and modified by Kennedy Onyancha - DoKS (KHK Kempen) (2007)
 * Display a summary of a ETD-MS record.
 *
 * $Id$
 *}

<span class="title">{foreach name=title from=$entries.title item=entry}{$entry.value|escape|truncate:90|default:"&mdash"}{if !$smarty.foreach.title.last}<br/>{/if}{/foreach}</span><br />
<div class="recordContents">
	{foreach from=$entries.creator item=creator}<span class="author">{$creator.value|escape|default:"&mdash;"}</span><br />{/foreach}
    {foreach from=$entries.date item=date}<span class="date">{$date.value|escape|default:"&mdash;"}</span><br />{/foreach}
	
	<a href="{url page="record" op="view" path=$record->getRecordId()}" class="action">{translate key="browse.viewRecord"}</a>{if $record->getUrl($entries)|assign:"recordUrl":true}&nbsp;|&nbsp;<a href="{$recordUrl}" class="action">{translate key="browse.viewOriginal"}</a>{/if}
</div>