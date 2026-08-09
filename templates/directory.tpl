{**
 * templates/directory.tpl
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com.br)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @brief Diretório interno de avaliadores + nominata (página de backend).
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{translate key="plugins.generic.reviewerDirectory.displayName"}
	</h1>

	{* v-pre: impede o Vue do backend de recompilar este conteúdo server-rendered *}
	<div class="rd-wrapper" v-pre>

		<div class="rd-tabs">
			<button type="button" class="rd-tab-btn {if !$nominataRequested}rd-active{/if}" data-tab="directory">{translate key="plugins.generic.reviewerDirectory.tabDirectory"}</button>
			<button type="button" class="rd-tab-btn {if $nominataRequested}rd-active{/if}" data-tab="nominata">{translate key="plugins.generic.reviewerDirectory.tabNominata"}</button>
		</div>

		{* ============================ DIRETÓRIO ============================ *}
		<div class="rd-panel {if !$nominataRequested}rd-active{/if}" data-panel="directory">

			<div class="rd-toolbar">
				<input type="search" id="rd-search" placeholder="{translate key='plugins.generic.reviewerDirectory.searchPlaceholder'}" autocomplete="off">
				<label><input type="checkbox" id="rd-orcid"> {translate key="plugins.generic.reviewerDirectory.onlyOrcid"}</label>
				<button type="button" class="rd-btn" data-export-table="rd-table-directory" data-export-name="avaliadores.csv">{translate key="plugins.generic.reviewerDirectory.export"}</button>
				<span class="rd-count">{translate key="plugins.generic.reviewerDirectory.showing"} <strong><span id="rd-shown">{$reviewerCount}</span></strong> {translate key="plugins.generic.reviewerDirectory.of"} {$reviewerCount}</span>
			</div>

			<div class="rd-colpicker">
				<strong>{translate key="plugins.generic.reviewerDirectory.columns"}:</strong>
				{foreach from=$columns item=col}
					<label><input type="checkbox" data-col-key="{$col.key|escape}"{if $col.default} checked{/if}> {$col.label|escape}</label>
				{/foreach}
			</div>

			{if $reviewerCount == 0}
				<div class="rd-empty">{translate key="plugins.generic.reviewerDirectory.noReviewers"}</div>
			{else}
				<div class="rd-table-scroll">
					<table class="rd-table" id="rd-table-directory">
						<thead>
							<tr>
								<th class="rd-col-name" data-col="0" data-sort="text">{translate key="user.name"}</th>
								{foreach from=$columns item=col key=i}
									<th class="rd-col-{$col.key|escape}{if !$col.default} rd-hidden{/if}" data-col="{$i+1}" data-sort="{$col.sort}"{if $col.title} title="{$col.title|escape}"{/if}>{$col.label|escape}</th>
								{/foreach}
							</tr>
						</thead>
						<tbody>
							{foreach from=$reviewers item=r}
								{capture assign="hay"}{$r.fullName} {$r.affiliation} {$r.country} {$r.interestsString} {$r.email} {$r.username} {$r.activeIdsString}{/capture}
								<tr data-rd-row data-search="{$hay|strip|lower|escape}" data-orcid="{if $r.orcid}1{else}0{/if}">
									<td class="rd-col-name rd-name" data-val="{$r.fullName|escape}">{$r.fullName|escape}</td>
									<td class="rd-col-affiliation rd-hidden rd-affil">{$r.affiliation|escape}</td>
									<td class="rd-col-country rd-hidden">{$r.country|escape}</td>
									<td class="rd-col-orcid rd-hidden" data-val="{if $r.orcid}1{else}0{/if}">
										{if $r.orcid}<a href="{$r.orcid|escape}" target="_blank" rel="noopener noreferrer">{if $r.orcidVerified}<span class="rd-orcid-badge" title="{translate key='plugins.generic.reviewerDirectory.orcidVerified'}">&#10003;</span> {/if}{$r.orcid|replace:"https://orcid.org/":""|replace:"http://orcid.org/":""|escape}</a>{else}&mdash;{/if}
									</td>
									<td class="rd-col-username rd-hidden">{$r.username|escape}</td>
									<td class="rd-col-email rd-hidden"><a href="mailto:{$r.email|escape}">{$r.email|escape}</a></td>
									<td class="rd-col-interests rd-interests">{$r.interestsString|escape}</td>
									<td class="rd-col-completed rd-num" data-val="{$r.reviewsCompleted}">{$r.reviewsCompleted}</td>
									<td class="rd-col-active rd-num" data-val="{$r.activeCount}" data-export="{$r.activeCount}{if $r.activeCount} ({$r.activeIdsString}){/if}">
										{if $r.activeCount}{$r.activeCount} <span class="rd-ids">({foreach from=$r.activeSubs item=s name=subs}<a href="{$s.url|escape}">#{$s.id}</a>{if !$s@last}, {/if}{/foreach})</span>{else}0{/if}
									</td>
									<td class="rd-col-declined rd-num" data-val="{$r.reviewsDeclined}">{$r.reviewsDeclined}</td>
									<td class="rd-col-average rd-num" data-val="{$r.averageDays}">{if $r.averageDays}{$r.averageDays}{else}&mdash;{/if}</td>
									<td class="rd-col-rating rd-num" data-val="{if $r.rating}{$r.rating}{else}0{/if}">{if $r.rating}{$r.rating}/5{else}&mdash;{/if}</td>
									<td class="rd-col-lastAssigned" data-val="{$r.lastAssigned}">{if $r.lastAssigned}{$r.lastAssigned}{else}&mdash;{/if}</td>
									<td class="rd-col-lastCompleted" data-val="{$r.lastCompleted}">{if $r.lastCompleted}{$r.lastCompleted}{else}&mdash;{/if}</td>
								</tr>
							{/foreach}
						</tbody>
					</table>
				</div>
			{/if}
		</div>

		{* ============================ NOMINATA ============================ *}
		<div class="rd-panel {if $nominataRequested}rd-active{/if}" data-panel="nominata">

			<p class="rd-hint">{translate key="plugins.generic.reviewerDirectory.nominataIntro"}</p>

			<form class="rd-nom-form" method="get" action="{$directoryUrl|escape}">
				<input type="hidden" name="rdNominata" value="1">
				<div class="rd-field">
					<label for="rdDateFrom">{translate key="plugins.generic.reviewerDirectory.dateFrom"}</label>
					<input type="date" id="rdDateFrom" name="rdDateFrom" value="{$rdDateFrom|escape}">
				</div>
				<div class="rd-field">
					<label for="rdDateTo">{translate key="plugins.generic.reviewerDirectory.dateTo"}</label>
					<input type="date" id="rdDateTo" name="rdDateTo" value="{$rdDateTo|escape}">
				</div>
				<div class="rd-field">
					<label for="rdSeriesId">{translate key="plugins.generic.reviewerDirectory.series"}</label>
					<select id="rdSeriesId" name="rdSeriesId">
						<option value="0">{translate key="plugins.generic.reviewerDirectory.allSeries"}</option>
						{foreach from=$series item=iss}
							<option value="{$iss.id}"{if $iss.id == $rdSeriesId} selected{/if}>{$iss.label|escape}</option>
						{/foreach}
					</select>
				</div>
				<div class="rd-field">
					<button type="submit" class="rd-btn">{translate key="plugins.generic.reviewerDirectory.generate"}</button>
				</div>
			</form>

			{if $nominataRequested}
				{if $nominata.count == 0}
					<div class="rd-empty">{translate key="plugins.generic.reviewerDirectory.nominataEmpty"}</div>
				{else}
					<div class="rd-nom-summary">
						{translate key="plugins.generic.reviewerDirectory.nominataSummary" n=$nominata.count reviews=$nominata.reviewsTotal}
						{if $nominata.seriesLabel} &mdash; <strong>{$nominata.seriesLabel|escape}</strong>{/if}
						<button type="button" class="rd-btn" style="margin-left:0.75rem;" data-export-table="rd-table-nominata" data-export-name="nominata.csv">{translate key="plugins.generic.reviewerDirectory.export"}</button>
					</div>
					<div class="rd-table-scroll">
						<table class="rd-table" id="rd-table-nominata">
							<thead>
								<tr>
									<th data-col="0" data-sort="text">{translate key="user.name"}</th>
									<th data-col="1" data-sort="text">{translate key="user.affiliation"}</th>
									<th data-col="2" data-sort="text">ORCID</th>
									<th data-col="3" data-sort="text">{translate key="user.email"}</th>
									<th data-col="4" data-sort="num" title="{translate key='plugins.generic.reviewerDirectory.reviewsInScope'}">{translate key="plugins.generic.reviewerDirectory.completedShort"}</th>
									<th data-col="5" data-sort="text">{translate key="plugins.generic.reviewerDirectory.submissions"}</th>
									<th data-col="6" data-sort="text">{translate key="plugins.generic.reviewerDirectory.firstDate"}</th>
									<th data-col="7" data-sort="text">{translate key="plugins.generic.reviewerDirectory.lastDate"}</th>
								</tr>
							</thead>
							<tbody>
								{foreach from=$nominata.rows item=n}
									<tr data-rd-row>
										<td class="rd-name" data-val="{$n.fullName|escape}">{$n.fullName|escape}</td>
										<td>{$n.affiliation|escape}</td>
										<td>{if $n.orcid}<a href="{$n.orcid|escape}" target="_blank" rel="noopener noreferrer">{$n.orcid|replace:"https://orcid.org/":""|replace:"http://orcid.org/":""|escape}</a>{else}&mdash;{/if}</td>
										<td><a href="mailto:{$n.email|escape}">{$n.email|escape}</a></td>
										<td class="rd-num" data-val="{$n.count}">{$n.count}</td>
										<td>{$n.submissionsString|escape}</td>
										<td>{$n.firstDate|escape}</td>
										<td>{$n.lastDate|escape}</td>
									</tr>
								{/foreach}
							</tbody>
						</table>
					</div>
				{/if}
			{/if}
		</div>

	</div>
{/block}
