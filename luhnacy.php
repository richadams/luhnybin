<?php
// @author Rich Adams (http://richadams.me)

////////////////////////////////////////////////////////////////////////////////////////////////////

// Luhn check, returns boolean.
function luhn($s)
{
    $len = strlen($s);
    $sum = 0;

    // If empty, then fail.
    if ($len == 0) { return false; }

    // Move right to left, but do reverse counting since I need to get odd/even from right.
    for ($i = 1; $i <= $len; $i++)
    {
        // Double every second digit and sun the individual digits before adding to total
        if ($i % 2 == 0)
        {
            $sum += array_sum(str_split(($s[$len-$i] * 2)));
            continue;
        }

        // Otherwise just add the number to the total
        $sum += $s[$len-$i];
    }

    // If divisible by 10 then luhn check pass
    return ($sum % 10 == 0);
}

////////////////////////////////////////////////////////////////////////////////////////////////////

$in  = fopen("php://stdin", "r");
$out = fopen("php://stdout", "a");

$checkChars = str_split("1234567890- ");

$check_mode   = false; // A flag to determine if I need to buffer the output.
$check_buffer = "";    // Will keep just the digits for easy luhn checking, rolling window of 16 chars.
$full_buffer  = "";    // Keeps the raw input, so I can go back and mask any luhn check passing chars.
$matched      = "";    // The part of the check_buffer which matched the luhn check.

while (!feof($in))
{
    // Read one character at a time.
    $char = fgetc($in);

    // If the character is one we need to look out for then go into check mode.
    if (!$check_mode && in_array($char, $checkChars))
    {
        $check_mode = true;
    }

    // Add to the full buffer if we're in check mode
    if ($check_mode) { $full_buffer .= $char; }

    // If in check mode, but the next character isn't one we care about, then leave check and flush buffers.
    if ($check_mode && !in_array($char, $checkChars))
    {
        fwrite($out, $full_buffer);

        // Reset
        $full_buffer  = "";
        $check_buffer = "";
        $matched      = "";
        $check_mode   = false;
        $mask         = false;

        continue;
    }

    if ($check_mode)
    {
        // If it's numeric, add it to the check buffer.
        if (is_numeric($char)) { $check_buffer .= $char; }

        // If more than 16 chars in check buffer, remove front numbers
        if (strlen($check_buffer) > 16) { $check_buffer = substr($check_buffer, strlen($check_buffer) - 16); }

        // If there are 14 or more integers in the check buffer, it's a potential credit card number
        if (strlen($check_buffer) >= 14)
        {
            // Check all 14-16 digit sub-strings since credit card could be surrounded by valid numbers.
            for ($i = 0; $i <= strlen($check_buffer)-14; $i++)
            {
                // Do 16 first to match longer ones before sub-matches.
                if (luhn(substr($check_buffer, $i, 16))) { $matched = substr($check_buffer, $i, 16); break; }
                if (luhn(substr($check_buffer, $i, 15))) { $matched = substr($check_buffer, $i, 15); break; }
                if (luhn(substr($check_buffer, $i, 14))) { $matched = substr($check_buffer, $i, 14); break; }
            }
        }

        // If matched, then mask from full_buffer.
        if ($matched != "")
        {
            // Go over the check_buffer, replacing each number with an X in the full buffer.
            // Work backwards to account for overlapping values.
            $pos = strlen($matched) - 1;
            for ($i = strlen($full_buffer) - 1; $i >= 0; $i--)
            {
                // Skip over any already masked values.
                if ($full_buffer[$i] == "X") { $pos--; continue; }

                // End of matched value, so we're done.
                if ($pos == -1) { break; }

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
