<?php

/**
 * @file plugins/generic/registrationFields/RegistrationFieldsPlugin.inc.php
 *
 * Copyright (c) 2025 OJS Services
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RegistrationFieldsPlugin
 * @ingroup plugins_generic_registrationFields
 *
 * @brief Adds ORCID iD and Phone Number fields to the OJS 3.3 registration form.
 *
 * Hook strategy:
 *   - TemplateManager::display      → inject fields via output filter
 *   - registrationform::readuserdata → capture POST data
 *   - registrationform::validate     → server-side validation
 *   - registrationform::execute      → persist data via deferred save
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class RegistrationFieldsPlugin extends GenericPlugin {

    /** @var string ORCID iD validation pattern (bare, https, http) */
    const ORCID_PATTERN = '/^(https?:\/\/orcid\.org\/)?(\d{4}-\d{4}-\d{4}-\d{3}[\dX])$/i';

    /** @var string Phone number validation pattern */
    const PHONE_PATTERN = '/^[\d\s\+\-\(\)\.]{5,24}$/';

    /** @var int Maximum length for ORCID input (https://orcid.org/ + 19 chars) */
    const ORCID_MAXLENGTH = 37;

    /** @var int Maximum length for phone input */
    const PHONE_MAXLENGTH = 24;

    /** @var string This plugin's locale keys start with this */
    const LOCALE_PREFIX = 'plugins.generic.registrationFields.';

    /** @var bool English fallback already registered in this request */
    private static $_englishFallbackRegistered = false;

    /**
     * @copydoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null) {
        $success = parent::register($category, $path, $mainContextId);

        // Before the "enabled" check: the plugin list shows the name and the
        // description of a plugin that is switched off as well.
        if ($success) {
            $this->_registerEnglishFallback();
        }

        if ($success && $this->getEnabled($mainContextId)) {
            HookRegistry::register('TemplateManager::display', array($this, 'handleTemplateDisplay'));
            HookRegistry::register('registrationform::readuserdata', array($this, 'readUserData'));
            HookRegistry::register('registrationform::validate', array($this, 'validateForm'));
            HookRegistry::register('registrationform::execute', array($this, 'executeForm'));
        }

        return $success;
    }

    /**
     * @copydoc Plugin::getDisplayName()
     */
    public function getDisplayName() {
        return __('plugins.generic.registrationFields.displayName');
    }

    /**
     * @copydoc Plugin::getDescription()
     */
    public function getDescription() {
        return __('plugins.generic.registrationFields.description');
    }

    // =========================================================================
    // Locale: English fallback and compiled-copy refresh
    // =========================================================================

    /**
     * English fallback for this plugin's own texts (OJS 3.3 shows ##key##
     * for a missing key). Only keys with this plugin's prefix are touched;
     * the English file is read directly, not registered for the language,
     * so OJS's own translations are never replaced.
     */
    private function _registerEnglishFallback() {
        if (self::$_englishFallbackRegistered) return; // the plugin may be set up more than once in a request
        self::$_englishFallbackRegistered = true;
        $this->_refreshLocaleCache();
        $englishPath = $this->getPluginPath() . '/locale/en_US/locale.po';
        HookRegistry::register('PKPLocale::translate', function ($hookName, $args) use ($englishPath) {
            $key = (string) $args[0];
            $locale = (string) $args[2];
            $value =& $args[4];
            if ($locale === 'en_US' || strpos($key, self::LOCALE_PREFIX) !== 0) return false;
            static $english = null;
            if ($english === null) {
                import('lib.pkp.classes.i18n.LocaleFile');
                $english = new LocaleFile('en_US', $englishPath);
            }
            $text = $english->isValid() ? $english->translate($key, (array) $args[1]) : null;
            if ($text === null) return false; // not in English either: stays ##key##
            $value = $text;
            return true;
        });
    }

    /**
     * OJS 3.3 recompiles a plugin's locale file only when the .po file is
     * newer than the compiled copy. Package files carry the time the package
     * was built, so an update installed later could keep old texts. When the
     * size or time of this plugin's own .po files changes, delete only this
     * plugin's compiled copies; OJS rebuilds them on the next lookup.
     */
    private function _refreshLocaleCache() {
        $dir = $this->getPluginPath() . '/locale';
        $files = array();
        foreach ((array) @scandir($dir) as $locale) {
            // The same path string OJS uses (Plugin::getLocaleFilename()), so the same cache name.
            if ($locale !== '.' && $locale !== '..' && is_file("$dir/$locale/locale.po")) $files[] = "$dir/$locale/locale.po";
        }
        $signature = '';
        foreach ($files as $file) $signature .= $file . ':' . filesize($file) . ':' . filemtime($file) . ';';
        $signature = md5($signature);
        import('lib.pkp.classes.cache.CacheManager');
        $cacheDir = CacheManager::getFileCachePath();
        $marker = $cacheDir . DIRECTORY_SEPARATOR . 'registrationFields' . DIRECTORY_SEPARATOR . 'locale-files.txt';
        if (@file_get_contents($marker) === $signature) return;
        foreach ($files as $file) @unlink($cacheDir . DIRECTORY_SEPARATOR . 'fc-locale-' . md5($file) . '.php');
        if (!is_dir(dirname($marker))) @mkdir(dirname($marker), 0775, true);
        @file_put_contents($marker, $signature);
    }

    // =========================================================================
    // Settings
    // =========================================================================

    /**
     * @copydoc Plugin::getActions()
     */
    public function getActions($request, $actionArgs) {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) {
            return $actions;
        }

        $router = $request->getRouter();
        import('lib.pkp.classes.linkAction.request.AjaxModal');
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, array(
                    'verb' => 'settings',
                    'plugin' => $this->getName(),
                    'category' => 'generic',
                )),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        ));
        return $actions;
    }

    /**
     * @copydoc Plugin::manage()
     */
    public function manage($args, $request) {
        switch ($request->getUserVar('verb')) {
            case 'settings':
                return $this->_showSettingsForm($request);
            case 'saveSettings':
                return $this->_saveSettings($request);
        }
        return parent::manage($args, $request);
    }

    /**
     * Render settings modal.
     *
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function _showSettingsForm($request) {
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : 0;

        $templateMgr = TemplateManager::getManager($request);
        $router = $request->getRouter();

        $templateMgr->assign(array(
            'saveUrl'      => $router->url($request, null, null, 'manage', null, array(
                'verb' => 'saveSettings',
                'plugin' => $this->getName(),
                'category' => 'generic',
            )),
            'enableOrcid'  => $this->getSetting($contextId, 'enableOrcid'),
            'requireOrcid' => $this->getSetting($contextId, 'requireOrcid'),
            'enablePhone'  => $this->getSetting($contextId, 'enablePhone'),
            'requirePhone' => $this->getSetting($contextId, 'requirePhone'),
            'debugMode'    => $this->getSetting($contextId, 'debugMode'),
            'pluginName'   => $this->getName(),
        ));

        return new JSONMessage(true, $templateMgr->fetch($this->getTemplateResource('settingsForm.tpl')));
    }

    /**
     * Persist settings from modal form.
     *
     * @param PKPRequest $request
     * @return JSONMessage
     */
    private function _saveSettings($request) {
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : 0;

        $enableOrcid  = (bool) $request->getUserVar('enableOrcid');
        $requireOrcid = (bool) $request->getUserVar('requireOrcid');
        $enablePhone  = (bool) $request->getUserVar('enablePhone');
        $requirePhone = (bool) $request->getUserVar('requirePhone');
        $debugMode    = (bool) $request->getUserVar('debugMode');

        // Enforce logical consistency
        if (!$enableOrcid) $requireOrcid = false;
        if (!$enablePhone) $requirePhone = false;

        $this->updateSetting($contextId, 'enableOrcid', $enableOrcid);
        $this->updateSetting($contextId, 'requireOrcid', $requireOrcid);
        $this->updateSetting($contextId, 'enablePhone', $enablePhone);
        $this->updateSetting($contextId, 'requirePhone', $requirePhone);
        $this->updateSetting($contextId, 'debugMode', $debugMode);

        $notificationManager = new NotificationManager();
        $notificationManager->createTrivialNotification(
            $request->getUser()->getId(),
            NOTIFICATION_TYPE_SUCCESS,
            array('contents' => __('common.changesSaved'))
        );

        return new JSONMessage(true);
    }

    // =========================================================================
    // Output Filter – Field Injection
    // =========================================================================

    /**
     * Hook: TemplateManager::display
     * Register output filter for the registration page.
     */
    public function handleTemplateDisplay($hookName, $args) {
        $template = $args[1];

        if ($template !== 'frontend/pages/userRegister.tpl') {
            return false;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) return false;

        $contextId = $context->getId();
        if ($this->getSetting($contextId, 'enableOrcid') || $this->getSetting($contextId, 'enablePhone')) {
            $args[0]->registerFilter('output', array($this, 'registrationFormFilter'));
        }

        return false;
    }

    /**
     * Output filter: inject custom fields into the rendered registration form.
     *
     * Uses flexible regex patterns with word boundaries to support themes that
     * add inline styles, multiple CSS classes, or extra attributes to fieldsets.
     * Falls back to inserting before </form> if no known landmark is found.
     *
     * @param string $output Rendered HTML
     * @param Smarty_Internal_Template $smarty
     * @return string Modified HTML
     */
    public function registrationFormFilter($output, $smarty) {
        if (!preg_match('/<form[^>]+id="register"/', $output)) {
            return $output;
        }

        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) return $output;

        $contextId = $context->getId();
        $debug = (bool) $this->getSetting($contextId, 'debugMode');

        if ($debug) {
            error_log('RegistrationFields: Output filter triggered, form id="register" found');
        }

        $fieldsHtml = $this->_buildFieldsHtml($contextId, $request);

        if (!empty($fieldsHtml)) {
            // Flexible patterns: match class attribute regardless of inline styles,
            // multiple classes, or additional HTML attributes on the element.
            // \b word boundary prevents false positives (e.g. "my-login" won't match "login").
            $injectionPoints = array(
                '/(<fieldset[^>]*class="[^"]*\blogin\b[^"]*"[^>]*>)/i',
                '/(<fieldset[^>]*class="[^"]*\bconsent\b[^"]*"[^>]*>)/i',
                '/(<div[^>]*class="[^"]*\bbuttons\b[^"]*"[^>]*>)/i',
            );

            $injected = false;
            foreach ($injectionPoints as $i => $pattern) {
                if (preg_match($pattern, $output)) {
                    $output = preg_replace($pattern, $fieldsHtml . '$1', $output, 1);
                    $injected = true;

                    if ($debug) {
                        error_log('RegistrationFields: Injected at point ' . $i . ' using pattern: ' . $pattern);
                    }
                    break;
                }
            }

            // Fallback: if no known landmark found, insert before </form>
            if (!$injected) {
                $output = preg_replace('/(<\/form>)/i', $fieldsHtml . '$1', $output, 1);

                if ($debug) {
                    error_log('RegistrationFields: No standard injection point found, used fallback (before </form>)');
                }
            }
        }

        // Unregister after first use to prevent double injection
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->unregisterFilter('output', array($this, 'registrationFormFilter'));

        return $output;
    }

    /**
     * Build escaped HTML markup for the enabled registration fields.
     *
     * @param int $contextId
     * @param PKPRequest $request
     * @return string HTML
     */
    private function _buildFieldsHtml($contextId, $request) {
        $enableOrcid  = $this->getSetting($contextId, 'enableOrcid');
        $enablePhone  = $this->getSetting($contextId, 'enablePhone');
        $requireOrcid = $this->getSetting($contextId, 'requireOrcid');
        $requirePhone = $this->getSetting($contextId, 'requirePhone');

        // Re-populate values on validation failure (all output is escaped)
        $orcidValue = htmlspecialchars($request->getUserVar('orcidId') ?? '', ENT_QUOTES, 'UTF-8');
        $phoneValue = htmlspecialchars($request->getUserVar('phone') ?? '', ENT_QUOTES, 'UTF-8');

        $html = '';

        if ($enableOrcid) {
            $html .= $this->_fieldsetHtml(
                'registrationFields_orcid',
                __('plugins.generic.registrationFields.orcidId'),
                __('plugins.generic.registrationFields.orcidId.description'),
                'text', 'orcidId', 'orcidId', $orcidValue,
                self::ORCID_MAXLENGTH, '0000-0000-0000-0000',
                (bool) $requireOrcid
            );
        }

        if ($enablePhone) {
            $html .= $this->_fieldsetHtml(
                'registrationFields_phone',
                __('plugins.generic.registrationFields.phone'),
                __('plugins.generic.registrationFields.phone.description'),
                'tel', 'phone', 'regPhone', $phoneValue,
                self::PHONE_MAXLENGTH, __('plugins.generic.registrationFields.phone.placeholder'),
                (bool) $requirePhone
            );
        }

        return $html;
    }

    /**
     * Render a single fieldset with label, input and description.
     *
     * @param string $cssClass  CSS class for the fieldset
     * @param string $label     Translated label text (already safe from __())
     * @param string $desc      Translated description text
     * @param string $type      Input type attribute
     * @param string $name      Input name attribute
     * @param string $id        Input id attribute
     * @param string $value     Pre-escaped value
     * @param int    $maxlength Maximum input length
     * @param string $placeholder
     * @param bool   $required
     * @return string HTML
     */
    private function _fieldsetHtml($cssClass, $label, $desc, $type, $name, $id, $value, $maxlength, $placeholder, $required) {
        $reqStar = $required
            ? ' <span class="required" aria-hidden="true">*</span>'
              . '<span class="pkp_screen_reader">' . __('common.required') . '</span>'
            : '';
        $reqAttr = $required ? ' required aria-required="true"' : '';

        // All dynamic values are pre-escaped; static strings are safe
        return '<fieldset class="' . htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8') . '">'
            . '<legend>' . $label . '</legend>'
            . '<div class="fields">'
            . '<div class="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '">'
            . '<label>'
            . '<span class="label">' . $label . $reqStar . '</span>'
            . '<input type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"'
            . ' name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
            . ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"'
            . ' value="' . $value . '"'
            . ' maxlength="' . (int) $maxlength . '"'
            . ' placeholder="' . htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8') . '"'
            . $reqAttr . '>'
            . '</label>'
            . '<span class="description" style="display:block;font-size:0.85em;color:#666;margin-top:4px;">'
            . $desc . '</span>'
            . '</div>'
            . '</div>'
            . '</fieldset>' . "\n";
    }

    // =========================================================================
    // Data Handling Hooks
    // =========================================================================

    /**
     * Hook: registrationform::readuserdata
     * Capture custom field values from POST.
     */
    public function readUserData($hookName, $args) {
        $form = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) return false;

        $contextId = $context->getId();

        if ($this->getSetting($contextId, 'enableOrcid')) {
            $form->setData('orcidId', $request->getUserVar('orcidId'));
        }
        if ($this->getSetting($contextId, 'enablePhone')) {
            $form->setData('phone', $request->getUserVar('phone'));
        }

        return false;
    }

    /**
     * Hook: registrationform::validate
     * Server-side validation for ORCID iD and phone number.
     */
    public function validateForm($hookName, $args) {
        $form = $args[0];
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) return false;

        $contextId = $context->getId();

        // ORCID iD validation
        if ($this->getSetting($contextId, 'enableOrcid')) {
            $orcidId = trim($request->getUserVar('orcidId') ?? '');

            if ($this->getSetting($contextId, 'requireOrcid') && $orcidId === '') {
                $form->addError('orcidId', __('plugins.generic.registrationFields.orcidId.required'));
            } elseif ($orcidId !== '' && !preg_match(self::ORCID_PATTERN, $orcidId)) {
                $form->addError('orcidId', __('plugins.generic.registrationFields.orcidId.invalid'));
            }
        }

        // Phone number validation
        if ($this->getSetting($contextId, 'enablePhone')) {
            $phone = trim($request->getUserVar('phone') ?? '');

            if ($this->getSetting($contextId, 'requirePhone') && $phone === '') {
                $form->addError('phone', __('plugins.generic.registrationFields.phone.required'));
            } elseif ($phone !== '' && !preg_match(self::PHONE_PATTERN, $phone)) {
                $form->addError('phone', __('plugins.generic.registrationFields.phone.invalid'));
            }
        }

        return false;
    }

    /**
     * Hook: registrationform::execute
     *
     * This hook fires inside Form::execute() (parent::execute) at the
     * BEGINNING of RegistrationForm::execute(), before the user row is
     * inserted.  We therefore capture the values and write them in a
     * register_shutdown_function callback, which runs after the full
     * request – by that time the user record exists in the database.
     */
    public function executeForm($hookName, $args) {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        if (!$context) return false;

        $contextId = $context->getId();
        $orcid = null;
        $phone = null;

        if ($this->getSetting($contextId, 'enableOrcid')) {
            $raw = trim($request->getUserVar('orcidId') ?? '');
            if ($raw !== '') {
                $orcid = $this->_normalizeOrcid($raw);
            }
        }

        if ($this->getSetting($contextId, 'enablePhone')) {
            $raw = trim($request->getUserVar('phone') ?? '');
            if ($raw !== '') {
                $phone = $raw;
            }
        }

        if ($orcid !== null || $phone !== null) {
            $email = trim($request->getUserVar('email') ?? '');
            $username = trim($request->getUserVar('username') ?? '');
            if ($email !== '') {
                register_shutdown_function(
                    array($this, 'deferredSave'),
                    $email, $username, $orcid, $phone
                );
            }
        }

        return false;
    }

    /**
     * Deferred save callback – runs after the HTTP response when the user
     * record is guaranteed to exist in the database.
     *
     * Safety layers:
     *  1. Email + username cross-verification
     *  2. Freshness check – only touch users registered within the last 2 minutes
     *  3. No-overwrite policy – never replace existing ORCID or phone values
     *
     * @param string      $email    Registration email
     * @param string      $username Registration username
     * @param string|null $orcid    Normalised ORCID URL or null
     * @param string|null $phone    Phone number or null
     */
    public function deferredSave($email, $username, $orcid, $phone) {
        $userDao = DAORegistry::getDAO('UserDAO');
        $user = $userDao->getUserByEmail($email);
        if (!$user || !$user->getId()) {
            return;
        }

        // Safety layer 1: cross-verify username
        if ($username !== '' && $user->getUsername() !== $username) {
            return;
        }

        // Safety layer 2: only update freshly registered accounts (≤120 seconds)
        $registered = strtotime($user->getDateRegistered());
        if ($registered === false || $registered < time() - 120) {
            return;
        }

        $changed = false;

        // Safety layer 3: no-overwrite – only set if the field is currently empty
        if ($orcid !== null && !$user->getOrcid()) {
            $user->setOrcid($orcid);
            $changed = true;
        }
        if ($phone !== null && !$user->getPhone()) {
            // Sanitize: strip invisible unicode / control characters
            $phone = preg_replace('/[^\d\s\+\-\(\)\.]/', '', $phone);
            $phone = substr($phone, 0, self::PHONE_MAXLENGTH);
            $user->setPhone($phone);
            $changed = true;
        }

        if ($changed) {
            $userDao->updateObject($user);
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Normalise any accepted ORCID input to canonical https://orcid.org/… URL.
     * Returns null if the bare identifier does not match the expected pattern.
     *
     * @param string $input Raw user input (bare digits, http or https URL)
     * @return string|null Canonical ORCID URL, or null if malformed
     */
    private function _normalizeOrcid($input) {
        $bare = preg_replace('/^https?:\/\/orcid\.org\//i', '', $input);
        // Final safety check on the bare identifier
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/i', $bare)) {
            return null;
        }
        return 'https://orcid.org/' . $bare;
    }
}
