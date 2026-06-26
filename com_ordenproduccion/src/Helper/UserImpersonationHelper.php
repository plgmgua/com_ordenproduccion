<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ordenproduccion
 *
 * @copyright   (C) 2026 Grimpsa. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Grimpsa\Component\Ordenproduccion\Site\Helper;

use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;

/**
 * Super-user impersonation: view the component as another user (filters / ACL).
 *
 * @since  3.119.192
 */
final class UserImpersonationHelper
{
    private const SESSION_ADMIN_ID   = 'com_ordenproduccion.impersonate_admin_id';
    private const SESSION_TARGET_ID  = 'com_ordenproduccion.impersonate_target_id';
    private const SESSION_TARGET_NAME = 'com_ordenproduccion.impersonate_target_name';

    /**
     * Apply active impersonation to the application identity (call once per request, before MVC).
     */
    public static function applyActiveImpersonation(): void
    {
        $app     = Factory::getApplication();
        $session = $app->getSession();
        $targetId = (int) $session->get(self::SESSION_TARGET_ID, 0);
        $adminId  = (int) $session->get(self::SESSION_ADMIN_ID, 0);

        if ($targetId < 1 || $adminId < 1) {
            return;
        }

        $currentUser = Factory::getUser();

        // Already applied earlier this request (e.g. system plugin + component dispatcher).
        if (!$currentUser->guest && (int) $currentUser->id === $targetId) {
            return;
        }

        if ($currentUser->guest || (int) $currentUser->id !== $adminId) {
            self::clearSessionKeys($session);
            return;
        }

        try {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $targetUser  = $userFactory->loadUserById($targetId);
        } catch (\Throwable $e) {
            self::clearSessionKeys($session);
            return;
        }

        if ($targetUser->guest || (int) $targetUser->id !== $targetId || (int) $targetUser->block === 1) {
            self::clearSessionKeys($session);
            return;
        }

        $app->loadIdentity($targetUser);
        self::refreshAuthorisationCaches();
    }

    /**
     * Clear Joomla ACL static caches so menu/modules use the impersonated identity.
     */
    private static function refreshAuthorisationCaches(): void
    {
        if (method_exists(Access::class, 'clearStatics')) {
            Access::clearStatics();
        }
    }

    /**
     * @return  bool
     */
    public static function isImpersonating(): bool
    {
        $session = Factory::getApplication()->getSession();

        return (int) $session->get(self::SESSION_TARGET_ID, 0) > 0
            && (int) $session->get(self::SESSION_ADMIN_ID, 0) > 0;
    }

    /**
     * Logged-in user (super user), not the impersonated identity.
     */
    public static function getRealUser(): User
    {
        $app     = Factory::getApplication();
        $session = $app->getSession();
        $adminId = (int) $session->get(self::SESSION_ADMIN_ID, 0);

        if ($adminId > 0) {
            try {
                $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
                $adminUser   = $userFactory->loadUserById($adminId);

                if (!$adminUser->guest && (int) $adminUser->id === $adminId) {
                    return $adminUser;
                }
            } catch (\Throwable $e) {
                // Fall through to session identity.
            }
        }

        return Factory::getUser();
    }

    /**
     * Whether the real logged-in user is a Joomla super user (core.admin).
     */
    public static function isRealSuperUser(): bool
    {
        return self::getRealUser()->authorise('core.admin');
    }

    /**
     * Start impersonating another user.
     *
     * @return  array{success:bool, message:string}
     */
    public static function start(int $targetUserId): array
    {
        self::ensureLanguageLoaded();

        if (!Session::checkToken('post')) {
            return ['success' => false, 'message' => Text::_('JINVALID_TOKEN')];
        }

        if (!self::isRealSuperUser()) {
            return ['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
        }

        $targetUserId = (int) $targetUserId;
        if ($targetUserId < 1) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_INVALID_USER')];
        }

        $realUser = self::getRealUser();
        if ((int) $realUser->id === $targetUserId) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_SELF')];
        }

        try {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $targetUser  = $userFactory->loadUserById($targetUserId);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_INVALID_USER')];
        }

        if ($targetUser->guest || (int) $targetUser->id !== $targetUserId) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_INVALID_USER')];
        }

        if ((int) $targetUser->block === 1) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_BLOCKED_USER')];
        }

        if ($targetUser->authorise('core.admin')) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_SUPER_USER')];
        }

        $session = Factory::getApplication()->getSession();
        $session->set(self::SESSION_ADMIN_ID, (int) $realUser->id);
        $session->set(self::SESSION_TARGET_ID, $targetUserId);
        $session->set(self::SESSION_TARGET_NAME, (string) $targetUser->name);

        self::logEvent('start', (int) $realUser->id, $targetUserId);

        return [
            'success' => true,
            'message' => Text::sprintf(
                'COM_ORDENPRODUCCION_IMPERSONATE_STARTED',
                (string) $targetUser->name
            ),
        ];
    }

    /**
     * Stop impersonating and restore the real user identity for this request.
     *
     * @return  array{success:bool, message:string}
     */
    public static function stop(): array
    {
        self::ensureLanguageLoaded();

        if (!Session::checkToken('post')) {
            return ['success' => false, 'message' => Text::_('JINVALID_TOKEN')];
        }

        if (!self::isImpersonating()) {
            return ['success' => false, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_NOT_ACTIVE')];
        }

        if (!self::isRealSuperUser()) {
            return ['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')];
        }

        $session  = Factory::getApplication()->getSession();
        $adminId  = (int) $session->get(self::SESSION_ADMIN_ID, 0);
        $targetId = (int) $session->get(self::SESSION_TARGET_ID, 0);

        self::clearSessionKeys($session);
        self::logEvent('stop', $adminId, $targetId);

        try {
            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
            $realUser    = $userFactory->loadUserById($adminId);
            if (!$realUser->guest && (int) $realUser->id === $adminId) {
                Factory::getApplication()->loadIdentity($realUser);
                self::refreshAuthorisationCaches();
            }
        } catch (\Throwable $e) {
            // Session cleared; next request loads real user from session.
        }

        return ['success' => true, 'message' => Text::_('COM_ORDENPRODUCCION_IMPERSONATE_STOPPED')];
    }

    /**
     * Active users that a super user may impersonate (excludes self, blocked, and super users).
     *
     * @return  array<int, string>  id => "Name (username)"
     */
    public static function getImpersonatableUserOptions(): array
    {
        $realUser = self::getRealUser();
        $realId   = (int) $realUser->id;

        if ($realId < 1 || !self::isRealSuperUser()) {
            return [];
        }

        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);

            // Exclude Joomla Super Users group (typically id 8) in SQL; authorise() checked below as fallback.
            $superUserSub = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName('m.user_id'))
                ->from($db->quoteName('#__user_usergroup_map', 'm'))
                ->innerJoin(
                    $db->quoteName('#__usergroups', 'g') . ' ON ' . $db->quoteName('g.id') . ' = ' . $db->quoteName('m.group_id')
                )
                ->where($db->quoteName('g.title') . ' = ' . $db->quote('Super Users'));

            $q  = $db->getQuery(true)
                ->select([
                    $db->quoteName('u.id'),
                    $db->quoteName('u.name'),
                    $db->quoteName('u.username'),
                ])
                ->from($db->quoteName('#__users', 'u'))
                ->where($db->quoteName('u.block') . ' = 0')
                ->where($db->quoteName('u.id') . ' > 0')
                ->where($db->quoteName('u.id') . ' != ' . $realId)
                ->where($db->quoteName('u.id') . ' NOT IN (' . (string) $superUserSub . ')')
                ->order($db->quoteName('u.name') . ' ASC');
            $db->setQuery($q);
            $rows = $db->loadObjectList() ?: [];
            $out  = [];

            $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);

            foreach ($rows as $row) {
                $id = (int) ($row->id ?? 0);
                if ($id < 1) {
                    continue;
                }

                try {
                    $user = $userFactory->loadUserById($id);
                    if ($user->authorise('core.admin')) {
                        continue;
                    }
                } catch (\Throwable $e) {
                    continue;
                }

                $name = trim((string) ($row->name ?? ''));
                $username = trim((string) ($row->username ?? ''));
                $label = $name !== '' ? $name : ('#' . $id);
                if ($username !== '') {
                    $label .= ' (' . $username . ')';
                }
                $out[$id] = $label;
            }

            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Whether the given user id may be impersonated by the current real super user.
     */
    public static function canImpersonateUserId(int $targetUserId): bool
    {
        $targetUserId = (int) $targetUserId;

        if ($targetUserId < 1) {
            return false;
        }

        return isset(self::getImpersonatableUserOptions()[$targetUserId]);
    }

    /**
     * Load component language (Dispatcher runs before views load .ini files).
     */
    private static function ensureLanguageLoaded(): void
    {
        $lang = Factory::getApplication()->getLanguage();
        $lang->load('com_ordenproduccion', JPATH_SITE);
        $lang->load('com_ordenproduccion', JPATH_SITE . '/components/com_ordenproduccion');
    }

    /**
     * Inject a fixed banner on HTML site pages while impersonating.
     */
    public static function registerDocumentBanner(): void
    {
        if (!self::isImpersonating()) {
            return;
        }

        $app = Factory::getApplication();
        if (!$app->isClient('site') || $app->input->getCmd('format', 'html') !== 'html') {
            return;
        }

        self::ensureLanguageLoaded();

        try {
            $doc = $app->getDocument();
        } catch (\Throwable $e) {
            return;
        }

        if ($doc->getType() !== 'html') {
            return;
        }

        $session    = $app->getSession();
        $targetName = trim((string) $session->get(self::SESSION_TARGET_NAME, ''));
        if ($targetName === '') {
            $targetName = Factory::getUser()->name;
        }

        $adminUser  = self::getRealUser();
        $adminLabel = trim((string) $adminUser->name);
        if ($adminLabel === '') {
            $adminLabel = '#' . (int) $adminUser->id;
        }

        $viewingAs = Text::sprintf(
            'COM_ORDENPRODUCCION_IMPERSONATE_BANNER_VIEWING',
            htmlspecialchars($targetName, ENT_QUOTES, 'UTF-8')
        );
        $asAdmin = Text::sprintf(
            'COM_ORDENPRODUCCION_IMPERSONATE_BANNER_ADMIN',
            htmlspecialchars($adminLabel, ENT_QUOTES, 'UTF-8')
        );
        $stopLabel = Text::_('COM_ORDENPRODUCCION_IMPERSONATE_STOP');
        $stopUrl   = Route::_('index.php?option=com_ordenproduccion&task=administracion.stopImpersonation', false);
        $token     = Session::getFormToken();

        $html = '<div id="com-ordenproduccion-impersonation-banner" role="alert" aria-live="polite">'
            . '<div class="cop-impersonation-inner">'
            . '<span class="cop-impersonation-icon" aria-hidden="true"><i class="fas fa-user-secret"></i></span>'
            . '<span class="cop-impersonation-text"><strong>' . $viewingAs . '</strong>'
            . ' <span class="cop-impersonation-admin">' . $asAdmin . '</span></span>'
            . '<form method="post" action="' . htmlspecialchars($stopUrl, ENT_QUOTES, 'UTF-8') . '" class="cop-impersonation-form">'
            . '<input type="hidden" name="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '" value="1" />'
            . '<button type="submit" class="btn btn-sm btn-light">' . htmlspecialchars($stopLabel, ENT_QUOTES, 'UTF-8') . '</button>'
            . '</form>'
            . '</div></div>';

        $css = '#com-ordenproduccion-impersonation-banner{position:fixed;top:0;left:0;right:0;z-index:10050;'
            . 'background:#b45309;color:#fff;font-size:0.875rem;box-shadow:0 2px 8px rgba(0,0,0,.2);}'
            . '#com-ordenproduccion-impersonation-banner .cop-impersonation-inner{max-width:1400px;margin:0 auto;'
            . 'padding:0.5rem 1rem;display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap;}'
            . '#com-ordenproduccion-impersonation-banner .cop-impersonation-icon{font-size:1.1rem;}'
            . '#com-ordenproduccion-impersonation-banner .cop-impersonation-text{flex:1 1 auto;}'
            . '#com-ordenproduccion-impersonation-banner .cop-impersonation-admin{opacity:.9;font-weight:400;}'
            . '#com-ordenproduccion-impersonation-banner .cop-impersonation-form{margin:0;}';

        $doc->addStyleDeclaration($css);

        $escapedHtml = json_encode($html, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        $doc->addScriptDeclaration(
            'document.addEventListener("DOMContentLoaded",function(){'
            . 'var b=' . $escapedHtml . ';'
            . 'document.body.insertAdjacentHTML("afterbegin",b);'
            . 'document.body.style.paddingTop=(document.getElementById("com-ordenproduccion-impersonation-banner").offsetHeight+4)+"px";'
            . '});'
        );
    }

    /**
     * Meta for session audit when impersonation is active.
     *
     * @return  array<string, mixed>
     */
    public static function getAuditMeta(): array
    {
        if (!self::isImpersonating()) {
            return [];
        }

        $session = Factory::getApplication()->getSession();
        $admin   = self::getRealUser();

        return [
            'impersonation_active' => true,
            'impersonator_id'      => (int) $admin->id,
            'impersonator_name'    => (string) $admin->name,
            'impersonator_username'=> (string) $admin->username,
            'impersonated_id'      => (int) $session->get(self::SESSION_TARGET_ID, 0),
            'impersonated_name'    => (string) $session->get(self::SESSION_TARGET_NAME, ''),
        ];
    }

    /**
     * @param   \Joomla\CMS\Session\Session  $session
     */
    private static function clearSessionKeys($session): void
    {
        $session->clear(self::SESSION_ADMIN_ID);
        $session->clear(self::SESSION_TARGET_ID);
        $session->clear(self::SESSION_TARGET_NAME);
    }

    private static function logEvent(string $action, int $adminId, int $targetId): void
    {
        Log::add(
            sprintf(
                'User impersonation %s: admin_id=%d target_id=%d ip=%s',
                $action,
                $adminId,
                $targetId,
                UserSessionAuditHelper::clientIp()
            ),
            Log::INFO,
            'com_ordenproduccion'
        );
    }
}
