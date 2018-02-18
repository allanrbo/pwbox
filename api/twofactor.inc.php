<?php

require("lib/base2n.inc.php");

function generateOtpKey() {
    $base32 = new Base2n(5, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', FALSE, TRUE, TRUE);
    $secretBin = openssl_random_pseudo_bytes(10);
    $secretHex = bin2hex($secretBin);
    $secretBase32 = $base32->encode($secretBin);
    $name = "PwBox";
    $otpUrl = 'otpauth://totp/'.$name.'?secret='.$secretBase32;
    return [$secretHex, $otpUrl];
}

function generateOtp($key) {
    // Determine the time window
    $time_window = 30;

    // Get the exact time from the server
    $exact_time = microtime(true);

    // Round the time down to the time window
    $rounded_time = floor($exact_time/$time_window);

    // Pack the counter into binary
    $packed_time = pack("N", $rounded_time);

    // Make sure the packed time is 8 characters long
    $padded_packed_time = str_pad($packed_time,8, chr(0), STR_PAD_LEFT);

    // Pack the secret seed into a binary string
    $packed_secret_seed = pack("H*", $key);

    // Generate the hash using the SHA1 algorithm
    $hash = hash_hmac ('sha1', $padded_packed_time, $packed_secret_seed, true);

    // Extract the 6 digit number from the hash as per RFC 6238
    $offset = ord($hash[19]) & 0xf;
    $otp = (
    ((ord($hash[$offset+0]) & 0x7f) << 24 ) |
    ((ord($hash[$offset+1]) & 0xff) << 16 ) |
    ((ord($hash[$offset+2]) & 0xff) << 8 ) |
    (ord($hash[$offset+3]) & 0xff)
    ) % pow(10, 6);

    // Add any missing zeros to the left of the numerical output
    $otp = str_pad($otp, 6, "0", STR_PAD_LEFT);
    return $otp;
}
