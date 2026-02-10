<?php

/**
 * @defgroup plugins_generic_registrationFields Registration Fields Plugin
 */

/**
 * @file plugins/generic/registrationFields/index.php
 *
 * Copyright (c) 2025 OJS Services
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup plugins_generic_registrationFields
 * @brief Wrapper for Registration Fields plugin.
 */

require_once('RegistrationFieldsPlugin.inc.php');
return new RegistrationFieldsPlugin();
