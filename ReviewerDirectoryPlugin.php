<?php

/**
 * @file ReviewerDirectoryPlugin.php
 *
 * Copyright (c) 2026 OJSBR (https://ojsbr.com.br)
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerDirectoryPlugin
 *
 * @brief Diretório interno de avaliadores: página de backend, restrita a
 *  gerentes e editores, que lista e permite filtrar os usuários com papel de
 *  Avaliador cadastrados na editora.
 */

namespace APP\plugins\generic\reviewerDirectory;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\core\Registry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\RedirectAction;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\security\Role;

class ReviewerDirectoryPlugin extends GenericPlugin
{
    /** Nome da página (rota) servida por este plugin. */
    public const PAGE = 'reviewerdirectory';

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName()
    {
        return __('plugins.generic.reviewerDirectory.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription()
    {
        return __('plugins.generic.reviewerDirectory.description');
    }

    /**
     * @copydoc Plugin::register()
     *
     * @param null|mixed $mainContextId
     */
    public function register($category, $path, $mainContextId = null)
    {
        if (parent::register($category, $path, $mainContextId)) {
            if ($this->getEnabled($mainContextId)) {
                // Registra a rota da página do diretório.
                Hook::add('LoadHandler', $this->callbackLoadHandler(...));
                // Acrescenta um atalho ao fim do menu lateral do painel (backend).
                Hook::add('TemplateManager::setupBackendPage', $this->callbackSetupBackendPage(...));
            }
            return true;
        }
        return false;
    }

    /**
     * Intercepta o roteamento para servir a página do diretório de avaliadores.
     *
     * @param string $hookName
     * @param array $args [$page, $op, $sourceFile, $handler]
     *
     * @return bool
     */
    public function callbackLoadHandler($hookName, $args)
    {
        $page = $args[0];
        if ($page !== self::PAGE) {
            return false;
        }

        $handler = &$args[3];
        $handler = new ReviewerDirectoryHandler($this);
        return true;
    }

    /**
     * Acrescenta um atalho para o diretório ao FIM do menu lateral do painel
     * (backend), poupando o editor de abri-lo pela tela de plugins. Visível
     * apenas para gerentes, editores de seção e administradores — os mesmos
     * papéis que o handler da página autoriza.
     *
     * @param string $hookName
     * @param array $args [$templateMgr]
     *
     * @return bool
     */
    public function callbackSetupBackendPage($hookName, $args)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();

        // Sem contexto ou sem usuário não existe menu lateral para acrescentar.
        if (!$context || !$user) {
            return Hook::CONTINUE;
        }

        // O hook dispara sem argumentos: o template manager vem do request.
        $templateMgr = TemplateManager::getManager($request);
        $menu = $templateMgr->getState('menu');
        if (!is_array($menu) || !count($menu)) {
            return Hook::CONTINUE;
        }

        if (!$this->userCanAccess($user->getId(), $context->getId())) {
            return Hook::CONTINUE;
        }

        $menu['reviewerDirectory'] = [
            'name' => __('plugins.generic.reviewerDirectory.displayName'),
            'icon' => 'ReviewAssignments',
            'url' => $this->getDirectoryUrl($request),
            'isCurrent' => $request->getRequestedPage() === self::PAGE,
        ];
        $templateMgr->setState(['menu' => $menu]);

        return Hook::CONTINUE;
    }

    /**
     * O atalho só aparece para quem a página autoriza: gerentes, editores de
     * seção e administradores do site.
     */
    private function userCanAccess(int $userId, int $contextId): bool
    {
        $allowedRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_SITE_ADMIN];

        // Papéis na editora atual.
        foreach (Repo::userGroup()->userUserGroups($userId, $contextId) as $userGroup) {
            if (in_array($userGroup->roleId, $allowedRoles)) {
                return true;
            }
        }

        // Administrador do site é atribuído fora do contexto da editora.
        foreach (Repo::userGroup()->userUserGroups($userId) as $userGroup) {
            if ($userGroup->roleId == Role::ROLE_ID_SITE_ADMIN) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL da página do diretório no contexto atual.
     */
    public function getDirectoryUrl($request): string
    {
        return $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            self::PAGE,
            'index'
        );
    }

    /**
     * @copydoc Plugin::getActions()
     *
     * Adiciona um atalho "Abrir diretório" na listagem de plugins.
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        array_unshift(
            $actions,
            new LinkAction(
                'openDirectory',
                new RedirectAction($this->getDirectoryUrl($request)),
                __('plugins.generic.reviewerDirectory.openDirectory'),
                null
            )
        );
        return $actions;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\reviewerDirectory\ReviewerDirectoryPlugin', '\ReviewerDirectoryPlugin');
}
