<?php
$textToEncrypt = "My super secret information.";
$encryptionMethod = "AES-256-CBC";  // AES is used by the U.S. gov't to encrypt top secret documents.
$secretHash = "25c6c7ff35b9979b151f2136cd13b0ff";
//To encrypt
$encryptedMessage = openssl_encrypt($textToEncrypt, $encryptionMethod, $secretHash);
//To Decrypt
$decryptedMessage = openssl_decrypt($encryptedMessage, $encryptionMethod, $secretHash);
//Result
echo "Encrypted: $encryptedMessage <br>Decrypted: $decryptedMessage";