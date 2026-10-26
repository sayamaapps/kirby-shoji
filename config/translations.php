<?php

use Sayamaapps\Shoji\I18n;

// Shoji's visitor-facing text, also usable as t('shoji.<key>'). Shoji reads its
// own tables (I18n), so a missing Kirby translation never changes a text.
return I18n::pluginTranslations();
