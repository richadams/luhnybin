<?php
// @author Rich Adams (http://richadams.me)

////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * Checks to see if a number passes the Luhn check.
 *
 * @param  string $s The string to check
 * @return boolean
 */
function luhn($s)
{
    $len = strlen($s);
    $sum = 0;

    // If empty, then fail.
    if ($len == 0) { return false; }

    // Move right to left, but do count from 0 since I need to get odd/even from right.
    for ($i = 1; $i <= $len; $i++)
    {
        // Double every second character and sum the digits before adding to total
        if ($i % 2 == 0)
        {
            $sum += array_sum(str_split(($s[$len-$i] * 2)));
            continue;
        }

        // Otherwise just add the number to the total
        $sum += $s[$len-$i];
    }

    // If sum is divisible by 10 then luhn check passes
    return ($sum % 10 == 0);
}

////////////////////////////////////////////////////////////////////////////////////////////////////

$in  = fopen("php://stdin", "r");
$out = fopen("php://stdout", "a");

$_validChars = str_split("1234567890- "); // Characters that could be part of a credit card number.
define("MIN_LENGTH", 14); // Min length of any credit card number expected.
define("MAX_LENGTH", 16); // Max length "   "   "

$check_mode   = false; // A flag to determine if I need to buffer the output.
$check_buffer = "";    // Will keep just the digits for easy luhn checking, rolling window of 16 chars.
$full_buffer  = "";    // Keeps the raw input, so I can go back and mask any luhn check passing chars.
$matched      = "";    // The part of the check_buffer which matched the luhn check.

while (!feof($in))
{
    // Read one character at a time.
    $char = fgetc($in);

    // If the character is one we need to look out for then go into check mode.
    if (!$check_mode && in_array($char, $_validChars))
    {
        $check_mode = true;
    }

    // Add to the full buffer if we're in check mode
    if ($check_mode) { $full_buffer .= $char; }

    // If in check mode, but the next character isn't one we care about, then leave check mode
    // and flush buffers.
    if ($check_mode && !in_array($char, $_validChars))
    {
        fwrite($out, $full_buffer);

        // Reset
        $full_buffer  = "";
        $check_buffer = "";
        $matched      = "";
        $check_mode   = false;

        continue;
    }

    if ($check_mode)
    {
        // If character is numeric, add it to the check buffer.
        if (is_numeric($char)) { $check_buffer .= $char; }

        // If more than max chars in check buffer, remove front values to keep it within max length
        if (strlen($check_buffer) > MAX_LENGTH)
        {
            $check_buffer = substr($check_buffer, strlen($check_buffer) - MAX_LENGTH);
        }

        // If there are more than min integers in the check buffer, it's a potential credit card number
        if (strlen($check_buffer) >= MIN_LENGTH)
        {
            // Check all min-max digit sub-strings since credit card could be surrounded by valid numbers.
            // Do max first to match longer ones before sub-matches.
            for ($i = 0; $i <= strlen($check_buffer) - MIN_LENGTH; $i++)
            {
                $to_check = substr($check_buffer, 0, MAX_LENGTH);
                while (strlen($to_check) >= MIN_LENGTH)
                {
                    if (luhn($to_check)) { $matched = $to_check; break 2; }
                    $to_check = substr($to_check, $i, -1);
                }

                //if (luhn(substr($check_buffer, $i, 16))) { $matched = substr($check_buffer, $i, 16); break; }
                //if (luhn(substr($check_buffer, $i, 15))) { $matched = substr($check_buffer, $i, 15); break; }
                //if (luhn(substr($check_buffer, $i, 14))) { $matched = substr($check_buffer, $i, 14); break; }
            }
        }

        // If matched, then mask from full_buffer.
        if ($matched != "")
        {
            // Go over the check_buffer, replacing each number with an X in the full buffer.
            // Work backwards to account for overlapping values.
            $pos = strlen($matched) - 1;
            for ($i = strlen($full_buffer) - 1;
                 ($i >= 0) && ($pos != -1);
                 $i--)
            {
                // Skip over any already masked values.
                if ($full_buffer[$i] == "X") { $pos--; continue; }

                // If it matches the expected value, mask it.
                if ($full_buffer[$i] == $matched[$pos])
                {
                    $full_buffer[$i] = "X";
                    $matched = substr($matched, 0, $pos);
                    $pos--;
                }
            }

            // Reset vars
            $matched = "";
        }

        continue;
    }

    // If we get to here, then it wasn't a character that is cared about. Write it straight back out.
    fwrite($out, $char);
}

// Finally, close all streams and do any tidyup.
fclose($in);
fclose($out);
