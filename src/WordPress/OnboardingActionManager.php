<?php
/**
 * OpenTT - Table Tennis Management Plugin
 * Copyright (C) 2026 Aleksa Dimitrijević
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 */

namespace OpenTT\Unified\WordPress;

final class OnboardingActionManager
{
    public static function resolveStateFromRequest($postKey, $defaultState = 'completed')
    {
        $action = isset($_POST[$postKey]) ? sanitize_key((string) $_POST[$postKey]) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ($action === 'skip') {
            return 'skipped';
        }
        return (string) $defaultState;
    }

    public static function persistStateAndClearRedirect($stateOptionKey, $stateValue, $redirectTransientKey)
    {
        update_option((string) $stateOptionKey, (string) $stateValue, false);
        delete_transient((string) $redirectTransientKey);
    }
}
