<?php
// Detects fake / troll / disposable emails BEFORE an OTP is ever sent.

// Known disposable / temp-mail domains (dugangi kung makakita ka pa).
function disposableDomains() {
    return [
        'mailinator.com','tempmail.com','temp-mail.org','10minutemail.com',
        'guerrillamail.com','sharklasers.com','yopmail.com','trashmail.com',
        'getnada.com','dispostable.com','fakeinbox.com','maildrop.cc',
        'throwawaymail.com','mohmal.com','emailondeck.com','moakt.com',
        'tempmailo.com','minuteinbox.com','1secmail.com','tmpmail.org',
    ];
}

// Returns an error message if the email looks fake/troll, or '' if OK.
function checkEmailQuality($email) {
    $email = strtolower(trim($email));
    $at    = strrpos($email, '@');
    if ($at === false) return 'Please enter a valid email address.';

    $local  = substr($email, 0, $at);
    $domain = substr($email, $at + 1);

    // 1. Disposable / temp-mail domain?
    if (in_array($domain, disposableDomains(), true)) {
        return 'Temporary or disposable email addresses are not allowed. Please use a real email.';
    }

    // 2. Obvious gibberish local part (walay bokal, pulos same char, etc.)
    if (!preg_match('/[aeiou]/', $local) && strlen($local) >= 6) {
        return 'Please enter a valid, real email address.';
    }

    // 3. Does the domain actually accept mail? Catches asdasd@asdasd.com.
    //    Check MX first, fall back to A record. (Needs internet on XAMPP.)
    if (!checkdnsrr($domain, 'MX') && !checkdnsrr($domain, 'A')) {
        return 'This email domain does not exist. Please check your email address.';
    }

    return '';
}
