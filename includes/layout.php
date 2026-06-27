<?php
declare(strict_types=1);

function recaptcha_widget(): string
{
    if (!recaptcha_is_enabled()) {
        return '';
    }
    return '<div class="g-recaptcha" data-sitekey="' . h(RECAPTCHA_SITE_KEY) . '"></div><script src="https://www.google.com/recaptcha/api.js" async defer></script>';
}
