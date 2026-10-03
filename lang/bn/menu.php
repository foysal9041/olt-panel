<?php

// Sidebar / top bar labels in Bangla, from the interface dictionary.
$dict = json_decode((string) @file_get_contents(lang_path('ui/bn.json')), true) ?: [];

// Menu keys go through Lang::get(), where a dot means nesting — keep only dot-free ones.
$dict = array_filter($dict, fn ($k) => ! str_contains($k, '.'), ARRAY_FILTER_USE_KEY);

return ['lang_switch' => 'English'] + $dict;
