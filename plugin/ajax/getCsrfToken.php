<?php

// Direct access to file
if (strpos($_SERVER['PHP_SELF'], "getCsrfToken.php") !== false) {
    include '../inc/includes.php';
    header("Content-Type: text/plain; charset=UTF-8");
    Html::header_nocache();
}

Session::checkLoginUser();

// Mints a token for forms that stay on the page after having been submitted: GLPI consumes the token of
// every POST request (see Session::validateCSRF), so such a form has to replace its token after each submit.
// A GET request is used on purpose: bodyless requests are not CSRF checked, so no token is needed to get one.
// The token is standalone to leave the token of the page which asks for it untouched.
echo Session::getNewCSRFToken(true);
