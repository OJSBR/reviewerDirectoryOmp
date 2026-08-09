<?php

/**
 * @file ReviewerDirectoryHandler.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com.br)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerDirectoryHandler
 *
 * @brief Renderiza a página de backend com o diretório de avaliadores e a
 *  nominata (avaliadores que concluíram avaliações em um período/edição).
 */

namespace APP\plugins\generic\reviewerDirectory;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;

class ReviewerDirectoryHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /** @var ReviewerDirectoryPlugin */
    protected $plugin;

    public function __construct(ReviewerDirectoryPlugin $plugin)
    {
        parent::__construct();
        $this->plugin = $plugin;

        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_SITE_ADMIN],
            ['index']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Página principal.
     */
    public function index($args, $request)
    {
        $context = $request->getContext();
        $contextId = $context->getId();
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $columns = $this->getColumns();
        $reviewers = $this->getReviewers($request, $contextId);
        $series = $this->getSeries($contextId);

        // Nominata (opcional): só é calculada quando o formulário é enviado.
        $dateFrom = $this->sanitizeDate($request->getUserVar('rdDateFrom'));
        $dateTo = $this->sanitizeDate($request->getUserVar('rdDateTo'));
        $seriesId = (int) $request->getUserVar('rdSeriesId');
        $nominataRequested = (bool) $request->getUserVar('rdNominata');
        $nominata = $nominataRequested
            ? $this->getNominata($contextId, $dateFrom, $dateTo, $seriesId)
            : null;

        $templateMgr->assign([
            'pageComponent' => 'Page',
            'pageTitle' => __('plugins.generic.reviewerDirectory.displayName'),
            'reviewers' => $reviewers,
            'reviewerCount' => count($reviewers),
            'columns' => $columns,
            'series' => $series,
            'directoryUrl' => $this->plugin->getDirectoryUrl($request),
            'nominataRequested' => $nominataRequested,
            'nominata' => $nominata,
            'rdDateFrom' => $dateFrom,
            'rdDateTo' => $dateTo,
            'rdSeriesId' => $seriesId,
        ]);

        $templateMgr->addStyleSheet(
            'reviewerDirectory',
            $this->getInlineStyles($columns),
            ['inline' => true, 'contexts' => ['backend']]
        );
        $templateMgr->addJavaScript(
            'reviewerDirectory',
            $this->getInlineScript(),
            ['inline' => true, 'contexts' => ['backend']]
        );

        $templateMgr->display($this->plugin->getTemplateResource('directory.tpl'));
    }

    /**
     * Definição das colunas alternáveis (fora a coluna Nome, sempre visível).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getColumns(): array
    {
        $p = 'plugins.generic.reviewerDirectory.';
        return [
            ['key' => 'affiliation',  'label' => __('user.affiliation'), 'sort' => 'text', 'default' => false],
            ['key' => 'country',      'label' => __('common.country'),   'sort' => 'text', 'default' => false],
            ['key' => 'orcid',        'label' => 'ORCID',                'sort' => 'text', 'default' => false],
            ['key' => 'username',     'label' => __('user.username'),    'sort' => 'text', 'default' => false],
            ['key' => 'email',        'label' => __('user.email'),       'sort' => 'text', 'default' => false],
            ['key' => 'interests',    'label' => __('user.interests'),   'sort' => 'text', 'default' => true],
            ['key' => 'completed',    'label' => __($p . 'completedShort'), 'title' => __($p . 'reviewsCompleted'), 'sort' => 'num', 'default' => true],
            ['key' => 'active',       'label' => __($p . 'activeShort'),    'title' => __($p . 'reviewsActive'),    'sort' => 'num', 'default' => true],
            ['key' => 'declined',     'label' => __($p . 'declinedShort'),  'title' => __($p . 'reviewsDeclined'),  'sort' => 'num', 'default' => true],
            ['key' => 'average',      'label' => __($p . 'averageShort'),   'title' => __($p . 'averageDays'),      'sort' => 'num', 'default' => true],
            ['key' => 'rating',        'label' => __($p . 'ratingShort'),    'title' => __($p . 'rating'),           'sort' => 'num', 'default' => true],
            ['key' => 'lastAssigned',  'label' => __($p . 'lastAssigned'),   'sort' => 'text', 'default' => true],
            ['key' => 'lastCompleted', 'label' => __($p . 'lastCompleted'),  'sort' => 'text', 'default' => true],
        ];
    }

    /**
     * Monta o array de avaliadores (perfil + estatísticas + submissões ativas).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function getReviewers($request, int $contextId): array
    {
        $collector = Repo::user()->getCollector()
            ->filterByContextIds([$contextId])
            ->filterByRoleIds([Role::ROLE_ID_REVIEWER])
            ->includeReviewerData();

        $userIds = $collector->getIds()->toArray();
        $interestsByUser = empty($userIds) ? [] : Repo::user()->preloadInterests($userIds);
        $activeByUser = $this->getActiveSubmissionsByReviewer($request, $contextId, $userIds);
        $lastCompletedByUser = $this->getLastCompletedByReviewer($contextId, $userIds);

        $reviewers = [];
        foreach ($collector->getMany() as $user) {
            $id = (int) $user->getId();
            $interests = array_values(array_filter(array_map(
                fn ($i) => is_array($i) ? ($i['interest'] ?? null) : $i,
                $interestsByUser[$id] ?? []
            )));
            $activeSubs = $activeByUser[$id] ?? [];
            $lastAssigned = $user->getData('lastAssigned');

            $reviewers[] = [
                'id' => $id,
                'fullName' => $user->getFullName(),
                'affiliation' => (string) $user->getLocalizedAffiliation(),
                'country' => (string) $user->getCountryLocalized(),
                'email' => (string) $user->getEmail(),
                'username' => (string) $user->getUsername(),
                'orcid' => (string) $user->getOrcid(),
                'orcidVerified' => (bool) $user->hasVerifiedOrcid(),
                'interests' => $interests,
                'interestsString' => implode(', ', $interests),
                'reviewsCompleted' => (int) $user->getData('completeCount'),
                'reviewsDeclined' => (int) $user->getData('declinedCount'),
                'averageDays' => (int) $user->getData('averageTime'),
                'rating' => $user->getData('reviewerRating') ? (int) $user->getData('reviewerRating') : null,
                'lastAssigned' => $lastAssigned ? substr((string) $lastAssigned, 0, 10) : '',
                'lastCompleted' => isset($lastCompletedByUser[$id]) ? substr((string) $lastCompletedByUser[$id], 0, 10) : '',
                'activeCount' => count($activeSubs),
                'activeSubs' => $activeSubs,
                'activeIdsString' => implode(' ', array_map(fn ($s) => '#' . $s['id'], $activeSubs)),
            ];
        }

        usort($reviewers, fn ($a, $b) => strcoll(
            Str::lower($a['fullName']),
            Str::lower($b['fullName'])
        ));

        return $reviewers;
    }

    /**
     * Para os avaliadores dados, os submission_ids com avaliação ATIVA
     * (notificada, não concluída/recusada/cancelada) no contexto atual.
     *
     * @return array<int, array<int, array{id:int,url:string}>>
     */
    protected function getActiveSubmissionsByReviewer($request, int $contextId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = DB::table('review_assignments as ra')
            ->whereIn('ra.reviewer_id', $userIds)
            ->whereIn('ra.submission_id', fn ($q) => $q
                ->select('s.submission_id')
                ->from('submissions as s')
                ->where('s.context_id', $contextId))
            ->whereNotNull('ra.date_notified')
            ->whereNull('ra.date_completed')
            ->where('ra.declined', '<>', 1)
            ->where('ra.cancelled', '<>', 1)
            ->orderBy('ra.submission_id')
            ->get(['ra.reviewer_id', 'ra.submission_id']);

        $dispatcher = $request->getDispatcher();
        $contextPath = $request->getContext()->getPath();
        $map = [];
        foreach ($rows as $r) {
            $submissionId = (int) $r->submission_id;
            $map[(int) $r->reviewer_id][] = [
                'id' => $submissionId,
                'url' => $dispatcher->url(
                    $request,
                    Application::ROUTE_PAGE,
                    $contextPath,
                    'dashboard',
                    'editorial',
                    null,
                    ['workflowSubmissionId' => $submissionId]
                ),
            ];
        }
        return $map;
    }

    /**
     * Data da última avaliação CONCLUÍDA por avaliador, no contexto atual.
     *
     * @return array<int, string> [reviewerId => 'YYYY-MM-DD HH:MM:SS']
     */
    protected function getLastCompletedByReviewer(int $contextId, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $rows = DB::table('review_assignments as ra')
            ->whereIn('ra.reviewer_id', $userIds)
            ->whereIn('ra.submission_id', fn ($q) => $q
                ->select('s.submission_id')
                ->from('submissions as s')
                ->where('s.context_id', $contextId))
            ->whereNotNull('ra.date_completed')
            ->where('ra.declined', '<>', 1)
            ->groupBy('ra.reviewer_id')
            ->selectRaw('ra.reviewer_id, MAX(ra.date_completed) as last_completed')
            ->get();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->reviewer_id] = (string) $r->last_completed;
        }
        return $map;
    }

    /**
     * Lista de séries da editora para o seletor da nominata.
     *
     * @return array<int, array{id:int,label:string}>
     */
    protected function getSeries(int $contextId): array
    {
        $series = [];
        foreach (Repo::section()->getCollector()->filterByContextIds([$contextId])->getMany() as $one) {
            $series[] = [
                'id' => (int) $one->getId(),
                'label' => $one->getLocalizedTitle(),
            ];
        }
        return $series;
    }

    /**
     * Nominata: avaliadores que CONCLUÍRAM avaliações no período e/ou série.
     *
     * @return array{rows: array, count: int, reviewsTotal: int, seriesLabel: string}
     */
    protected function getNominata(int $contextId, string $dateFrom, string $dateTo, int $seriesId): array
    {
        $query = DB::table('review_assignments as ra')
            ->whereIn('ra.submission_id', fn ($q) => $q
                ->select('s.submission_id')
                ->from('submissions as s')
                ->where('s.context_id', $contextId))
            ->whereNotNull('ra.date_completed')
            ->where('ra.declined', '<>', 1);

        if ($dateFrom !== '') {
            $query->whereDate('ra.date_completed', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $query->whereDate('ra.date_completed', '<=', $dateTo);
        }

        $seriesLabel = '';
        if ($seriesId > 0) {
            $submissionIds = DB::table('publications')
                ->where('series_id', $seriesId)
                ->distinct()
                ->pluck('submission_id')
                ->all();
            // Sem submissões na série: nominata vazia.
            $query->whereIn('ra.submission_id', empty($submissionIds) ? [0] : $submissionIds);

            $series = Repo::section()->get($seriesId, $contextId);
            $seriesLabel = $series ? $series->getLocalizedTitle() : '';
        }

        $rows = $query->get(['ra.reviewer_id', 'ra.submission_id', 'ra.date_completed']);

        // Agrupa por avaliador.
        $byReviewer = [];
        $reviewsTotal = 0;
        foreach ($rows as $r) {
            $rid = (int) $r->reviewer_id;
            $reviewsTotal++;
            if (!isset($byReviewer[$rid])) {
                $byReviewer[$rid] = ['count' => 0, 'subs' => [], 'firstDate' => null, 'lastDate' => null];
            }
            $byReviewer[$rid]['count']++;
            $byReviewer[$rid]['subs'][(int) $r->submission_id] = true;
            $date = substr((string) $r->date_completed, 0, 10);
            if ($byReviewer[$rid]['firstDate'] === null || $date < $byReviewer[$rid]['firstDate']) {
                $byReviewer[$rid]['firstDate'] = $date;
            }
            if ($byReviewer[$rid]['lastDate'] === null || $date > $byReviewer[$rid]['lastDate']) {
                $byReviewer[$rid]['lastDate'] = $date;
            }
        }

        $reviewerIds = array_keys($byReviewer);
        $users = [];
        if (!empty($reviewerIds)) {
            foreach (Repo::user()->getCollector()->filterByUserIds($reviewerIds)->getMany() as $u) {
                $users[(int) $u->getId()] = $u;
            }
        }

        $result = [];
        foreach ($byReviewer as $rid => $data) {
            $user = $users[$rid] ?? null;
            if (!$user) {
                continue;
            }
            $result[] = [
                'id' => $rid,
                'fullName' => $user->getFullName(),
                'affiliation' => (string) $user->getLocalizedAffiliation(),
                'country' => (string) $user->getCountryLocalized(),
                'orcid' => (string) $user->getOrcid(),
                'email' => (string) $user->getEmail(),
                'count' => $data['count'],
                'submissions' => array_keys($data['subs']),
                'submissionsString' => implode(', ', array_map(fn ($s) => '#' . $s, array_keys($data['subs']))),
                'firstDate' => $data['firstDate'] ?? '',
                'lastDate' => $data['lastDate'] ?? '',
            ];
        }

        usort($result, fn ($a, $b) => strcoll(Str::lower($a['fullName']), Str::lower($b['fullName'])));

        return [
            'rows' => $result,
            'count' => count($result),
            'reviewsTotal' => $reviewsTotal,
            'seriesLabel' => $seriesLabel,
        ];
    }

    /**
     * Valida uma data no formato YYYY-MM-DD; senão retorna string vazia.
     */
    protected function sanitizeDate(?string $value): string
    {
        $value = trim((string) $value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
    }

    /**
     * CSS inline. Colunas ocultas por padrão já saem com display:none para
     * evitar "flash" antes do JS aplicar a preferência salva.
     */
    protected function getInlineStyles(array $columns): string
    {
        // Colunas ocultas são controladas pela classe .rd-hidden (aplicada no
        // server para as default=false, e alternada via JS). Assim evitamos o
        // conflito entre display inline e regra de folha de estilo.
        return <<<'CSS'
        .rd-wrapper { margin-top: 1rem; }
        .rd-tabs { display: flex; gap: 0.25rem; border-bottom: 2px solid #ddd; margin-bottom: 1rem; }
        .rd-tab-btn { padding: 0.55rem 1.1rem; border: none; background: none; cursor: pointer; font-size: 0.95rem; color: #555; border-bottom: 3px solid transparent; margin-bottom: -2px; }
        .rd-tab-btn.rd-active { color: #1a2a3a; font-weight: 700; border-bottom-color: #14477d; }
        .rd-panel { display: none; }
        .rd-panel.rd-active { display: block; }
        .rd-toolbar { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-bottom: 0.75rem; }
        .rd-toolbar input[type="search"] { flex: 1 1 240px; min-width: 200px; padding: 0.5rem 0.75rem; border: 1px solid #b3b3b3; border-radius: 3px; font-size: 0.9rem; }
        .rd-toolbar label { font-size: 0.85rem; display: inline-flex; align-items: center; gap: 0.35rem; white-space: nowrap; }
        .rd-btn { padding: 0.5rem 0.9rem; border: 1px solid #14477d; background: #14477d; color: #fff; border-radius: 3px; cursor: pointer; font-size: 0.85rem; }
        .rd-btn:hover { background: #0f3760; }
        .rd-count { font-size: 0.85rem; color: #555; }
        .rd-colpicker { background: #f5f7fa; border: 1px solid #e0e4e8; border-radius: 4px; padding: 0.5rem 0.75rem; margin-bottom: 0.85rem; font-size: 0.82rem; }
        .rd-colpicker strong { margin-right: 0.5rem; }
        .rd-colpicker label { display: inline-flex; align-items: center; gap: 0.3rem; margin: 0.15rem 0.75rem 0.15rem 0; white-space: nowrap; }
        .rd-table-scroll { overflow-x: auto; border: 1px solid #ddd; border-radius: 3px; }
        table.rd-table { border-collapse: collapse; width: 100%; font-size: 0.82rem; background: #fff; }
        table.rd-table th, table.rd-table td { padding: 0.5rem 0.65rem; text-align: left; border-bottom: 1px solid #eee; vertical-align: top; }
        table.rd-table thead th { position: sticky; top: 0; background: #f5f5f5; border-bottom: 2px solid #ddd; cursor: pointer; white-space: nowrap; user-select: none; z-index: 1; }
        table.rd-table thead th[data-sort]:after { content: " \2195"; color: #aaa; font-size: 0.75em; }
        table.rd-table thead th.rd-asc:after { content: " \2191"; color: #333; }
        table.rd-table thead th.rd-desc:after { content: " \2193"; color: #333; }
        table.rd-table tbody tr:nth-child(even) { background: #fafafa; }
        table.rd-table tbody tr:hover { background: #eef5fb; }
        table.rd-table td.rd-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .rd-ids a { margin-right: 0.25rem; }
        .rd-orcid-badge { color: #a6ce39; font-weight: bold; }
        .rd-empty { padding: 1.5rem; text-align: center; color: #777; }
        .rd-name { font-weight: 600; }
        .rd-affil { color: #555; }
        .rd-nom-form { display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end; background: #f5f7fa; border: 1px solid #e0e4e8; border-radius: 4px; padding: 1rem; margin-bottom: 1rem; }
        .rd-field { display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.82rem; }
        .rd-field input, .rd-field select { padding: 0.45rem 0.6rem; border: 1px solid #b3b3b3; border-radius: 3px; font-size: 0.88rem; }
        .rd-field select { max-width: 320px; }
        .rd-hint { font-size: 0.82rem; color: #666; margin-bottom: 1rem; }
        .rd-nom-summary { font-size: 0.9rem; margin-bottom: 0.75rem; }
        .rd-hidden { display: none; }
        CSS;
    }

    /**
     * JS inline: abas, busca, filtro ORCID, seletor de colunas, ordenação e
     * exportação CSV/Excel. Roda no <head>, então usa delegação de eventos.
     */
    protected function getInlineScript(): string
    {
        return <<<'JS'
        (function () {
            var LS_COLS = 'rdVisibleCols';
            function $(sel, root) { return (root || document).querySelector(sel); }
            function all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }
            function dirRows() { return all('#rd-table-directory tr[data-rd-row]'); }

            // -------- Abas --------
            function activateTab(name) {
                all('.rd-tab-btn').forEach(function (b) { b.classList.toggle('rd-active', b.getAttribute('data-tab') === name); });
                all('.rd-panel').forEach(function (p) { p.classList.toggle('rd-active', p.getAttribute('data-panel') === name); });
            }

            // -------- Busca / filtro --------
            function applyFilter() {
                var box = $('#rd-search');
                var orcidOnly = $('#rd-orcid');
                var terms = (box ? box.value.trim().toLowerCase() : '').split(/\s+/).filter(Boolean);
                var wantOrcid = orcidOnly ? orcidOnly.checked : false;
                var shown = 0;
                dirRows().forEach(function (tr) {
                    var hay = tr.getAttribute('data-search') || '';
                    var hasOrcid = tr.getAttribute('data-orcid') === '1';
                    var match = terms.every(function (t) { return hay.indexOf(t) !== -1; });
                    if (wantOrcid && !hasOrcid) match = false;
                    tr.style.display = match ? '' : 'none';
                    if (match) shown++;
                });
                var counter = $('#rd-shown');
                if (counter) counter.textContent = shown;
            }

            // -------- Seletor de colunas --------
            function applyColumn(key, visible) {
                all('.rd-col-' + key).forEach(function (el) { el.classList.toggle('rd-hidden', !visible); });
            }
            function saveCols() {
                var state = {};
                all('.rd-colpicker input[data-col-key]').forEach(function (cb) { state[cb.getAttribute('data-col-key')] = cb.checked; });
                try { localStorage.setItem(LS_COLS, JSON.stringify(state)); } catch (e) {}
            }
            function loadCols() {
                var state = null;
                try { state = JSON.parse(localStorage.getItem(LS_COLS) || 'null'); } catch (e) {}
                all('.rd-colpicker input[data-col-key]').forEach(function (cb) {
                    var key = cb.getAttribute('data-col-key');
                    if (state && Object.prototype.hasOwnProperty.call(state, key)) {
                        cb.checked = !!state[key];
                    }
                    applyColumn(key, cb.checked);
                });
            }

            // -------- Ordenação --------
            function sortBy(th) {
                var table = th.closest('table');
                var idx = parseInt(th.getAttribute('data-col'), 10);
                var type = th.getAttribute('data-sort');
                var asc = !th.classList.contains('rd-asc');
                all('thead th', table).forEach(function (h) { h.classList.remove('rd-asc', 'rd-desc'); });
                th.classList.add(asc ? 'rd-asc' : 'rd-desc');
                var tbody = table.querySelector('tbody');
                var trs = all('tr[data-rd-row]', table);
                trs.sort(function (a, b) {
                    var ac = a.children[idx], bc = b.children[idx];
                    var av = ac.getAttribute('data-val'); if (av === null) av = ac.textContent.trim();
                    var bv = bc.getAttribute('data-val'); if (bv === null) bv = bc.textContent.trim();
                    if (type === 'num') { return asc ? (parseFloat(av) || 0) - (parseFloat(bv) || 0) : (parseFloat(bv) || 0) - (parseFloat(av) || 0); }
                    return asc ? String(av).localeCompare(bv) : String(bv).localeCompare(av);
                });
                trs.forEach(function (tr) { tbody.appendChild(tr); });
            }

            // -------- Exportação CSV (abre no Excel) --------
            function cellText(td) {
                var ids = td.querySelector('.rd-ids');
                var t = td.getAttribute('data-export');
                if (t === null) t = td.textContent.replace(/\s+/g, ' ').trim();
                return t;
            }
            function exportTable(table, filename) {
                var ths = all('thead th', table);
                var visibleIdx = [];
                ths.forEach(function (th, i) { if (!th.classList.contains('rd-hidden')) visibleIdx.push(i); });
                var lines = [];
                lines.push(visibleIdx.map(function (i) { return csv(ths[i].textContent.trim()); }).join(';'));
                all('tbody tr', table).forEach(function (tr) {
                    if (tr.style.display === 'none') return;
                    lines.push(visibleIdx.map(function (i) { return csv(cellText(tr.children[i])); }).join(';'));
                });
                var content = 'sep=;\r\n' + lines.join('\r\n');
                download(filename, '﻿' + content);
            }
            function csv(v) { v = String(v == null ? '' : v); return /[";\r\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
            function download(filename, text) {
                var blob = new Blob([text], { type: 'text/csv;charset=utf-8;' });
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                document.body.appendChild(a); a.click();
                setTimeout(function () { document.body.removeChild(a); URL.revokeObjectURL(a.href); }, 100);
            }

            // -------- Eventos (delegação) --------
            document.addEventListener('input', function (e) { if (e.target && e.target.id === 'rd-search') applyFilter(); });
            document.addEventListener('change', function (e) {
                if (e.target && e.target.id === 'rd-orcid') applyFilter();
                if (e.target && e.target.getAttribute && e.target.getAttribute('data-col-key')) {
                    applyColumn(e.target.getAttribute('data-col-key'), e.target.checked); saveCols();
                }
            });
            document.addEventListener('click', function (e) {
                var t = e.target;
                var th = t.closest ? t.closest('th[data-sort]') : null;
                if (th) { sortBy(th); return; }
                var tabBtn = t.closest ? t.closest('.rd-tab-btn') : null;
                if (tabBtn) { activateTab(tabBtn.getAttribute('data-tab')); return; }
                var exp = t.closest ? t.closest('[data-export-table]') : null;
                if (exp) {
                    var table = $('#' + exp.getAttribute('data-export-table'));
                    if (table) exportTable(table, exp.getAttribute('data-export-name') || 'export.csv');
                }
            });

            function init() {
                if (!$('.rd-wrapper')) return;
                loadCols();
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
                window.addEventListener('load', init);
            } else { init(); }
        })();
        JS;
    }
}
