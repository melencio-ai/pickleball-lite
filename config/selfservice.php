<?php

return [
    'enabled' => (bool) env('PICKLE_SELF_REGISTRATION_ENABLED', false),
    'trial_days' => 30,
    'extension_days' => 15,
    'max_extensions' => 3,
    'extension_window_days' => 7,
];
